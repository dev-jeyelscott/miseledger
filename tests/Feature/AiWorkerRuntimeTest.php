<?php

use App\Models\User;
use App\Support\Ai\Providers\CodexProfileLocator;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Redis;
use Tests\Support\ReadsCodexProfileMarkerForTest;
use Tests\Support\WritesCodexProfileMarkerForTest;

/**
 * A real `queue:work --once` subprocess against a real Redis connection can
 * occasionally observe an empty queue immediately after this process's push
 * under CI resource contention. Retrying the dispatch+consume pair a few
 * times keeps this a genuine cross-process integration check without
 * flaking on that infra timing rather than the topology under test.
 */
function runOnceUntil(callable $dispatch, array $workerArgs, callable $isDone): void
{
    for ($attempt = 0; $attempt < 3; $attempt++) {
        $dispatch();

        $result = Process::timeout(15)->path(base_path())->run($workerArgs);

        expect($result->successful())->toBeTrue($result->errorOutput());

        if ($isDone()) {
            return;
        }
    }

    expect($isDone())->toBeTrue('Job was not processed by the worker after 3 attempts.');
}

test('a Codex profile written by a real ai-login queue consumer is visible to an independently booted ai queue consumer', function () {
    $userId = random_int(1_000_000, 9_999_999);
    $marker = 'authenticated-'.bin2hex(random_bytes(8));
    $resultPath = storage_path('app/private/ai-worker-runtime-test-result-'.$marker.'.txt');

    Redis::connection('default')->del(
        'queues:ai-login', 'queues:ai-login:reserved', 'queues:ai-login:delayed', 'queues:ai-login:notify',
        'queues:ai', 'queues:ai:reserved', 'queues:ai:delayed', 'queues:ai:notify',
    );

    $profiles = app(CodexProfileLocator::class);
    $profilePath = $profiles->path((new User)->forceFill(['id' => $userId]));

    try {
        // Simulate the login worker: dispatch onto the real ai-login queue
        // over the real ai redis connection (not Queue::fake()) and consume
        // it with a genuinely separate `queue:work` process, matching the
        // documented ai-login-worker command.
        runOnceUntil(
            fn () => WritesCodexProfileMarkerForTest::dispatch($userId, $marker)->onConnection('ai')->onQueue('ai-login'),
            ['php', 'artisan', 'queue:work', 'ai', '--queue=ai-login', '--once', '--timeout=10'],
            fn () => file_exists($profilePath.'/marker'),
        );

        expect(file_get_contents($profilePath.'/marker'))->toBe($marker);

        // A worker consuming only the ai queue must not also drain ai-login.
        WritesCodexProfileMarkerForTest::dispatch($userId, 'should-not-run')
            ->onConnection('ai')
            ->onQueue('ai-login');

        $isolationResult = Process::timeout(15)->path(base_path())
            ->run(['php', 'artisan', 'queue:work', 'ai', '--queue=ai', '--once', '--stop-when-empty']);

        expect($isolationResult->successful())->toBeTrue($isolationResult->errorOutput())
            ->and(file_get_contents($profilePath.'/marker'))->toBe($marker);

        // Simulate the chat/turn worker: an entirely separate process, on
        // the ai queue, reading the profile the login worker persisted.
        runOnceUntil(
            fn () => ReadsCodexProfileMarkerForTest::dispatch($userId, $resultPath)->onConnection('ai')->onQueue('ai'),
            ['php', 'artisan', 'queue:work', 'ai', '--queue=ai', '--once', '--timeout=10'],
            fn () => file_exists($resultPath),
        );

        expect(file_get_contents($resultPath))->toBe($marker);
    } finally {
        Redis::connection('default')->del(
            'queues:ai-login', 'queues:ai-login:reserved', 'queues:ai-login:delayed', 'queues:ai-login:notify',
            'queues:ai', 'queues:ai:reserved', 'queues:ai:delayed', 'queues:ai:notify',
        );
        app(Filesystem::class)->deleteDirectory($profilePath);
        @unlink($resultPath);
    }
})->group('ai-redis-integration');

test('the dedicated AI queue has a bounded retry window that exceeds its tool-backed run timeout', function () {
    expect(config('queue.connections.ai'))->toMatchArray([
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => 'ai',
        'retry_after' => 960,
    ])->and(config('queue.connections.ai.retry_after'))->toBeGreaterThan(930)
        ->and(config('ai.codex.timeout_seconds'))->toBe(75);
});

test('the production web image excludes Codex and isolated workers separate interactive and login queues', function () {
    $dockerfile = file_get_contents(base_path('Dockerfile'));
    $compose = file_get_contents(base_path('compose.yaml'));
    [, $productionAndAiWorker] = explode('FROM runtime-base AS production', (string) $dockerfile, 2);
    [$productionTarget] = explode('# Dedicated, non-HTTP AI runtime.', $productionAndAiWorker);
    [, $aiWorkerService] = explode("\n    ai-worker:", (string) $compose, 2);
    [$aiWorkerService, $aiLoginWorkerService] = explode("\n    ai-login-worker:", $aiWorkerService, 2);
    [$aiLoginWorkerService] = explode("\n    scheduler:", $aiLoginWorkerService, 2);
    $codexClient = file_get_contents(base_path('app/Support/Ai/Providers/CodexJsonRpcClient.php'));

    expect($dockerfile)->toContain('FROM php:${PHP_VERSION}-cli-bookworm AS ai-worker')
        ->toContain('COPY --from=codex /usr/local/bin/node /usr/local/bin/node')
        ->toContain('CMD ["php", "artisan", "queue:work", "ai", "--sleep=1", "--tries=3", "--timeout=190"]')
        ->and($productionTarget)->not->toContain('COPY --from=codex')
        ->not->toContain('/usr/local/bin/node')
        ->and($compose)->toContain('    ai-worker:')
        ->and($aiWorkerService)->toContain("                'ai',")
        ->toContain("'--timeout=190'")
        ->not->toContain("'--queue=ai-login'")
        ->toContain("'codex-profiles:/var/lib/miseledger/codex/profiles'")
        ->and($compose)->toContain('    ai-login-worker:')
        ->and($aiLoginWorkerService)->toContain("'--queue=ai-login'")
        ->toContain('/tmp:mode=1777')
        ->toContain("'codex-profiles:/var/lib/miseledger/codex/profiles'")
        ->toContain('read_only: true')
        ->toContain('no-new-privileges:true')
        ->toContain('pids_limit: 256')
        ->toContain('mem_limit: 2g')
        ->not->toContain("'.:/var/www/html:ro'")
        ->not->toContain('ports:')
        ->not->toContain('apparmor=unconfined')
        ->not->toContain('seccomp=')
        ->and($compose)->toContain('    codex-profiles:')
        ->and($codexClient)->toContain('features.use_legacy_landlock=true');
});

test('the ai-worker and ai-login-worker services share one Codex profile store instead of independent per-worker filesystems', function () {
    $compose = file_get_contents(base_path('compose.yaml'));
    [, $aiWorkerService] = explode("\n    ai-worker:", (string) $compose, 2);
    [$aiWorkerService, $aiLoginWorkerService] = explode("\n    ai-login-worker:", $aiWorkerService, 2);
    [$aiLoginWorkerService] = explode("\n    scheduler:", $aiLoginWorkerService, 2);

    // A device-code login exchanged by the login worker is only visible to
    // Codex processes reading the same CODEX_HOME. If each worker mounted
    // its own profile filesystem (e.g. two independent tmpfs), a completed
    // login would never be visible to the worker that runs chat turns.
    expect($aiWorkerService)
        ->not->toContain('/var/lib/miseledger/codex/profiles:mode=')
        ->and($aiLoginWorkerService)
        ->not->toContain('/var/lib/miseledger/codex/profiles:mode=')
        ->and($aiWorkerService)->toContain("volumes:\n            - 'codex-profiles:/var/lib/miseledger/codex/profiles'")
        ->and($aiLoginWorkerService)->toContain("volumes:\n            - 'codex-profiles:/var/lib/miseledger/codex/profiles'");
});
