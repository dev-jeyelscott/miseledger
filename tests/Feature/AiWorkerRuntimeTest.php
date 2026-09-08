<?php

test('the dedicated AI queue has a bounded retry window that exceeds its tool-backed run timeout', function () {
    expect(config('queue.connections.ai'))->toMatchArray([
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => 'ai',
        'retry_after' => 210,
    ])->and(config('queue.connections.ai.retry_after'))->toBeGreaterThan(180)
        ->and(config('ai.codex.timeout_seconds'))->toBe(75);
});

test('the production web image excludes Codex and isolated workers separate interactive and login queues', function () {
    $dockerfile = file_get_contents(base_path('Dockerfile'));
    $compose = file_get_contents(base_path('compose.yaml'));
    [, $productionAndAiWorker] = explode('FROM runtime-base AS production', (string) $dockerfile, 2);
    [$productionTarget] = explode('# Dedicated, non-HTTP AI runtime.', $productionAndAiWorker);
    [, $aiWorkerService] = explode("\n  ai-worker:", (string) $compose, 2);
    [$aiWorkerService, $aiLoginWorkerService] = explode("\n  ai-login-worker:", $aiWorkerService, 2);
    [$aiLoginWorkerService] = explode("\n  scheduler:", $aiLoginWorkerService, 2);
    $codexClient = file_get_contents(base_path('app/Support/Ai/Providers/CodexJsonRpcClient.php'));

    expect($dockerfile)->toContain('FROM php:${PHP_VERSION}-cli-bookworm AS ai-worker')
        ->toContain('COPY --from=codex /usr/local/bin/node /usr/local/bin/node')
        ->toContain('CMD ["php", "artisan", "queue:work", "ai", "--sleep=1", "--tries=3", "--timeout=90"]')
        ->and($productionTarget)->not->toContain('COPY --from=codex')
        ->not->toContain('/usr/local/bin/node')
        ->and($compose)->toContain('  ai-worker:')
        ->and($aiWorkerService)->toContain("        'ai',")
        ->toContain("'--timeout=190'")
        ->not->toContain("'--queue=ai-login'")
        ->and($compose)->toContain('  ai-login-worker:')
        ->and($aiLoginWorkerService)->toContain("'--queue=ai-login'")
        ->toContain('/tmp:mode=1777')
        ->toContain("'ai-codex-profiles:/var/lib/miseledger/codex/profiles'")
        ->toContain('read_only: true')
        ->toContain('no-new-privileges:true')
        ->toContain('pids_limit: 256')
        ->toContain('mem_limit: 2g')
        ->not->toContain("'.:/var/www/html:ro'")
        ->not->toContain('ports:')
        ->not->toContain('apparmor=unconfined')
        ->not->toContain('seccomp=')
        ->and($codexClient)->toContain('features.use_legacy_landlock=true');
});
