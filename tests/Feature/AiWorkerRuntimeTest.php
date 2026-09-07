<?php

test('the dedicated AI queue has a bounded retry window that exceeds its worker timeout', function () {
    expect(config('queue.connections.ai'))->toMatchArray([
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => 'ai',
        'retry_after' => 120,
    ])->and(config('queue.connections.ai.retry_after'))->toBeGreaterThan(90);
});

test('the production web image excludes Codex and the isolated AI worker target consumes only the AI queue', function () {
    $dockerfile = file_get_contents(base_path('Dockerfile'));
    $compose = file_get_contents(base_path('compose.yaml'));
    [, $productionAndAiWorker] = explode('FROM runtime-base AS production', (string) $dockerfile, 2);
    [$productionTarget] = explode('# Dedicated, non-HTTP AI runtime.', $productionAndAiWorker);
    [, $aiWorkerService] = explode("\n  ai-worker:", (string) $compose, 2);
    [$aiWorkerService] = explode("\n  scheduler:", $aiWorkerService, 2);

    expect($dockerfile)->toContain('FROM php:${PHP_VERSION}-cli-bookworm AS ai-worker')
        ->toContain('COPY --from=codex /usr/local/bin/node /usr/local/bin/node')
        ->toContain('CMD ["php", "artisan", "queue:work", "ai", "--sleep=1", "--tries=3", "--timeout=90"]')
        ->and($productionTarget)->not->toContain('COPY --from=codex')
        ->not->toContain('/usr/local/bin/node')
        ->and($compose)->toContain('  ai-worker:')
        ->and($aiWorkerService)->toContain("        'ai',")
        ->toContain('read_only: true')
        ->toContain('pids_limit: 64')
        ->toContain('mem_limit: 2g')
        ->not->toContain("'.:/var/www/html:ro'")
        ->not->toContain('ports:');
});
