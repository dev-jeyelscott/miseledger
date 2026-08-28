<?php

namespace App\Console\Commands;

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

        if ($this->isDisallowedHost($this->extractRepositoryHost($repository))) {
            $this->error('RESTIC_REPOSITORY resolves to a local or application-host address, which is not permitted. The repository must be a genuinely off-host destination.');

            return self::FAILURE;
        }

        $alertWebhookUrl = config('backup.alert_webhook_url');

        if (! is_string($alertWebhookUrl) || $alertWebhookUrl === '') {
            $this->error('Backup failure alerting is not configured. Set BACKUP_ALERT_WEBHOOK_URL at runtime before scheduling backups.');

            return self::FAILURE;
        }

        if ($this->isDisallowedHost(parse_url($alertWebhookUrl, PHP_URL_HOST) ?: null)) {
            $this->error('BACKUP_ALERT_WEBHOOK_URL resolves to a local or application-host address, which is not permitted. The alert destination must be off-host.');

            return self::FAILURE;
        }

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
                : (Str::before($rest, '/') ?: null),
            'sftp:' => Str::of($rest)->after('@')->before(':')->toString() ?: null,
            default => null,
        };
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

    private function isDisallowedHost(?string $host): bool
    {
        $host = $this->normalizeHost($host);

        if ($host === null) {
            return false;
        }

        if (in_array($host, self::DISALLOWED_HOSTS, true)) {
            return true;
        }

        // Reject loopback and other reserved/non-routable IP ranges
        // (127.0.0.0/8, ::1, 0.0.0.0/8, link-local, etc.): any literal IP
        // that fails FILTER_FLAG_NO_RES_RANGE is not a genuine off-host
        // network address.
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_RES_RANGE) === false;
        }

        return false;
    }

    private function reportFailure(string $message): void
    {
        $this->error($message);

        $webhookUrl = config('backup.alert_webhook_url');

        if (! is_string($webhookUrl) || $webhookUrl === '') {
            return;
        }

        try {
            Http::timeout(10)->post($webhookUrl, [
                'text' => 'MiseLedger PostgreSQL backup failure: '.$message,
            ]);
        } catch (Throwable) {
            // Alerting is best-effort; the command's own non-zero exit code
            // and Coolify's process/log observability remain authoritative.
        }
    }
}
