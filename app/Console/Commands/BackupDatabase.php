<?php

namespace App\Console\Commands;

use App\Support\Backup\ResolvesBackupHosts;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Throwable;

final class BackupDatabase extends Command
{
    protected $signature = 'backup:database';

    protected $description = 'Create an encrypted, off-host PostgreSQL archive and apply the configured retention policy.';

    /**
     * The validated alert webhook URL and the single public IP address its
     * host was confirmed to resolve to, captured once in handle() and
     * reused by reportFailure() so the alert request is pinned to the
     * address that was actually validated instead of re-resolving DNS at
     * delivery time (which would reopen a rebinding window).
     */
    private ?string $alertWebhookUrl = null;

    private ?string $alertPinnedAddress = null;

    public function __construct(private readonly ResolvesBackupHosts $hostResolver)
    {
        parent::__construct();
    }

    /**
     * Restic repository URL prefixes for the approved off-host, restic-supported
     * backends (S3-compatible object storage, B2, Azure, GCS, SFTP, a REST
     * server). A repository without one of these prefixes is a local or
     * on-host path and is rejected: it cannot satisfy the off-host requirement.
     */
    private const OFF_HOST_SCHEMES = ['s3:', 'b2:', 'azure:', 'gs:', 'sftp:', 'rest:'];

    /**
     * Hostnames that are never a legitimate off-host backup or alert
     * destination: localhost aliases and the application's own Compose
     * service names. A restic repository or alert webhook resolving to one
     * of these still passes an approved-scheme prefix check, so the host
     * itself must also be rejected to guarantee genuine off-host storage
     * and alerting. Loopback and other reserved IP ranges (127.0.0.0/8,
     * ::1, link-local, etc.) are rejected separately via filter_var, since
     * they cannot be fully enumerated as literal strings.
     */
    private const DISALLOWED_HOSTS = [
        'localhost', 'host.docker.internal',
        'app', 'pgsql', 'redis', 'scheduler', 'worker', 'vite',
    ];

    public function handle(): int
    {
        $repository = config('backup.restic_repository');
        $password = config('backup.restic_password');

        if (! is_string($repository) || $repository === ''
            || ! is_string($password) || $password === '') {
            $this->error('Backup destination is not configured. Set RESTIC_REPOSITORY and RESTIC_PASSWORD at runtime before scheduling backups.');

            return self::FAILURE;
        }

        if (! Str::startsWith(Str::lower($repository), self::OFF_HOST_SCHEMES)) {
            $this->error('RESTIC_REPOSITORY must target an approved off-host backend (s3:, b2:, azure:, gs:, sftp:, or rest:). Local or on-host repository paths are not permitted.');

            return self::FAILURE;
        }

        $repositoryHost = $this->extractRepositoryHost($repository);

        if ($repositoryHost !== null && $this->resolvePublicAddress($repositoryHost) === null) {
            $this->error('RESTIC_REPOSITORY resolves to a local, application-host, or unresolvable address, which is not permitted. The repository must be a genuinely off-host destination.');

            return self::FAILURE;
        }

        $alertWebhookUrl = config('backup.alert_webhook_url');

        if (! is_string($alertWebhookUrl) || $alertWebhookUrl === '') {
            $this->error('Backup failure alerting is not configured. Set BACKUP_ALERT_WEBHOOK_URL at runtime before scheduling backups.');

            return self::FAILURE;
        }

        if (Str::lower((string) parse_url($alertWebhookUrl, PHP_URL_SCHEME)) !== 'https') {
            $this->error('BACKUP_ALERT_WEBHOOK_URL must use https://. Plaintext HTTP alert delivery is not permitted.');

            return self::FAILURE;
        }

        $alertPinnedAddress = $this->resolvePublicAddress(parse_url($alertWebhookUrl, PHP_URL_HOST) ?: null);

        if ($alertPinnedAddress === null) {
            $this->error('BACKUP_ALERT_WEBHOOK_URL resolves to a local, application-host, or unresolvable address, which is not permitted. The alert destination must be off-host.');

            return self::FAILURE;
        }

        $this->alertWebhookUrl = $alertWebhookUrl;
        $this->alertPinnedAddress = $alertPinnedAddress;

        $env = [
            'RESTIC_REPOSITORY' => $repository,
            'RESTIC_PASSWORD' => $password,
            'PGPASSWORD' => (string) config('database.connections.pgsql.password'),
        ];

        // A transient /tmp path only, never a persisted volume or tracked
        // storage path: it is uploaded and removed within this run.
        $dumpPath = sys_get_temp_dir().'/miseledger-backup-'.Str::uuid()->toString().'.dump';
        $snapshotName = 'miseledger-'.now()->utc()->format('Ymd-His').'.dump';

        try {
            $dump = Process::env($env)->timeout(3600)->run([
                'pg_dump',
                '--format=custom',
                '--no-owner',
                '--no-privileges',
                '--file='.$dumpPath,
                '--host='.config('database.connections.pgsql.host'),
                '--port='.config('database.connections.pgsql.port'),
                '--username='.config('database.connections.pgsql.username'),
                (string) config('database.connections.pgsql.database'),
            ]);

            if ($dump->failed() || ! is_file($dumpPath) || filesize($dumpPath) === 0) {
                $this->reportFailure('pg_dump failed to produce a non-empty archive.');

                return self::FAILURE;
            }

            $upload = Process::env($env)->timeout(3600)->run([
                'restic',
                'backup',
                $dumpPath,
                '--tag', 'miseledger-postgresql',
            ]);

            if ($upload->failed()) {
                $this->reportFailure('restic failed to upload the encrypted archive to the off-host repository.');

                return self::FAILURE;
            }
        } catch (Throwable) {
            $this->reportFailure('The PostgreSQL backup pipeline threw an unexpected exception.');

            return self::FAILURE;
        } finally {
            if (is_file($dumpPath)) {
                unlink($dumpPath);
            }
        }

        $forget = Process::env($env)->timeout(600)->run([
            'restic',
            'forget',
            '--tag', 'miseledger-postgresql',
            '--keep-daily', (string) config('backup.retention.daily'),
            '--keep-weekly', (string) config('backup.retention.weekly'),
            '--keep-monthly', (string) config('backup.retention.monthly'),
            '--prune',
        ]);

        if ($forget->failed()) {
            $this->reportFailure('Backup retention pruning (restic forget) failed.');

            return self::FAILURE;
        }

        $this->info('PostgreSQL backup and retention pruning completed: '.$snapshotName);

        return self::SUCCESS;
    }

    /**
     * Extract the network host embedded in a restic repository string, for
     * the backends whose syntax carries one (s3, sftp, rest). B2, Azure, and
     * GCS repositories only carry an account-scoped bucket/container name,
     * never a network host, so no host is extracted for them.
     */
    private function extractRepositoryHost(string $repository): ?string
    {
        [$scheme, $rest] = array_pad(explode(':', $repository, 2), 2, '');
        $scheme = Str::lower($scheme).':';

        return match ($scheme) {
            'rest:' => parse_url($rest, PHP_URL_HOST) ?: null,
            's3:' => Str::startsWith(Str::lower($rest), ['http://', 'https://'])
                ? (parse_url($rest, PHP_URL_HOST) ?: null)
                : $this->extractAuthorityHost(Str::before($rest, '/')),
            'sftp:' => Str::startsWith($rest, '//')
                ? (parse_url('sftp:'.$rest, PHP_URL_HOST) ?: null)
                : $this->extractAuthorityHost(
                    Str::contains($rest, '@') ? Str::after($rest, '@') : $rest,
                ),
            default => null,
        };
    }

    /**
     * Extract a bare host from a "host", "host:port", "[ipv6]", or
     * "[ipv6]:port" authority string, stopping at the first unbracketed
     * ':' (port) or '/' (path). A bracketed IPv6 literal is unwrapped, and
     * anything following its closing bracket (port or path) is discarded.
     */
    private function extractAuthorityHost(string $authority): ?string
    {
        if ($authority === '') {
            return null;
        }

        if ($authority[0] === '[') {
            $end = strpos($authority, ']');

            return $end === false || $end === 1 ? null : substr($authority, 1, $end - 1);
        }

        $end = strcspn($authority, ':/');

        return $end === 0 ? null : substr($authority, 0, $end);
    }

    /**
     * Normalize a repository or webhook host for comparison: lowercase,
     * strip IPv6 brackets (parse_url() keeps them, e.g. "[::1]"), and strip
     * a trailing dot (a valid DNS root-label alias for "localhost").
     */
    private function normalizeHost(?string $host): ?string
    {
        if ($host === null || $host === '') {
            return null;
        }

        return rtrim(trim(Str::lower($host), '[]'), '.') ?: null;
    }

    /**
     * Resolve a repository or alert-webhook host to a single verified
     * public IP address, or null if the host is disallowed, unresolvable,
     * or resolves to any non-public address. A literal IP is validated
     * directly, without DNS. A hostname is resolved via the injected
     * resolver, and every A/AAAA address it returns is checked: if even
     * one resolved address is non-public, the whole host is rejected,
     * since an attacker (or a rebinding DNS record) only needs one
     * internal-pointing record to defeat the off-host guarantee. A
     * resolution failure yields no addresses, which fails closed rather
     * than being treated as an implicitly safe host.
     */
    private function resolvePublicAddress(?string $host): ?string
    {
        $host = $this->normalizeHost($host);

        if ($host === null || in_array($host, self::DISALLOWED_HOSTS, true)) {
            return null;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return $this->isPublicAddress($host) ? $host : null;
        }

        $addresses = $this->hostResolver->resolve($host);

        if ($addresses === []) {
            return null;
        }

        foreach ($addresses as $address) {
            if (! $this->isPublicAddress($address)) {
                return null;
            }
        }

        return $addresses[0];
    }

    /**
     * Reject loopback, link-local/metadata, and other non-routable IP
     * ranges (127.0.0.0/8, ::1, 0.0.0.0/8, fe80::/10, 169.254.0.0/16,
     * etc.) as well as RFC1918/IPv6-ULA private ranges (10.0.0.0/8,
     * 172.16.0.0/12, 192.168.0.0/16, fc00::/7). FILTER_FLAG_NO_RES_RANGE
     * alone does not reject private ranges despite its documentation
     * implying otherwise, so it must be combined with
     * FILTER_FLAG_NO_PRIV_RANGE: any address that fails either is not a
     * genuine off-host network address.
     */
    private function isPublicAddress(string $address): bool
    {
        return filter_var(
            $address,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_RES_RANGE | FILTER_FLAG_NO_PRIV_RANGE,
        ) !== false;
    }

    private function reportFailure(string $message): void
    {
        $this->error($message);

        if ($this->alertWebhookUrl === null || $this->alertPinnedAddress === null) {
            return;
        }

        $host = parse_url($this->alertWebhookUrl, PHP_URL_HOST);
        $port = parse_url($this->alertWebhookUrl, PHP_URL_PORT) ?? 443;

        if ($host === null || $host === false) {
            return;
        }

        // CURLOPT_RESOLVE expects a bare host, but parse_url() keeps IPv6
        // brackets (e.g. "[::1]"); strip them so the pinned entry matches.
        $host = trim($host, '[]');

        try {
            // Pin the connection to the IP address already verified as
            // public, instead of letting curl re-resolve the host at
            // connect time: without this, a DNS record that changes
            // between validation and delivery (rebinding) could still
            // route this privileged, scheduled request to an internal
            // address even though the earlier check passed.
            Http::withOptions([
                'curl' => [
                    CURLOPT_RESOLVE => [sprintf('%s:%d:%s', $host, $port, $this->alertPinnedAddress)],
                ],
            ])
                ->withoutRedirecting()
                ->connectTimeout(5)
                ->timeout(10)
                ->post($this->alertWebhookUrl, [
                    'text' => 'MiseLedger PostgreSQL backup failure: '.$message,
                ]);
        } catch (Throwable) {
            // Alerting is best-effort; the command's own non-zero exit code
            // and Coolify's process/log observability remain authoritative.
        }
    }
}
