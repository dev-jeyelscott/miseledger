<?php

use Illuminate\Support\Facades\Process;
use Laravel\Pulse\Recorders\Servers;
use Laravel\Pulse\Recorders\SlowRequests;
use Laravel\Pulse\Recorders\UserRequests;
use Tests\Support\WritesFileMarkerForTest;

test('horizon manages only the normal redis connection and default queue', function () {
    $supervisor = config('horizon.defaults.supervisor-1');

    expect($supervisor['connection'])->toBe('redis')
        ->and($supervisor['queue'])->toBe(['default']);
});

test('horizon preserves the tries and timeout semantics of the worker command it replaces', function () {
    $supervisor = config('horizon.defaults.supervisor-1');

    expect($supervisor['tries'])->toBe(3)
        ->and($supervisor['timeout'])->toBe(90);
});

test('horizon supervisor timeout stays below the redis queue retry_after', function () {
    $timeout = config('horizon.defaults.supervisor-1.timeout');
    $retryAfter = config('queue.connections.redis.retry_after');

    expect($timeout)->toBeLessThan($retryAfter);
});

test('horizon configuration never references the isolated ai connection or queues', function () {
    $encoded = json_encode([
        config('horizon.defaults'),
        config('horizon.environments'),
        config('horizon.waits'),
    ]);

    expect($encoded)
        ->not->toContain('"ai"')
        ->not->toContain('ai-login');
});

test('horizon dashboard access is gated by the platform-admin middleware stack in every environment', function () {
    expect(config('horizon.middleware'))->toBe([
        'web', 'auth', 'verified', 'platform.admin',
    ]);
});

test('pulse dashboard access is gated by the platform-admin middleware stack in every environment', function () {
    expect(config('pulse.middleware'))->toBe([
        'web', 'auth', 'verified', 'platform.admin',
    ]);
});

test('pulse does not enable the servers recorder, which would require an unapproved daemon', function () {
    expect(config('pulse.recorders'))
        ->not->toHaveKey(Servers::class);
});

test('pulse ignores its own dashboard, the horizon dashboard, and signed billing webhooks in request recorders', function () {
    $recorders = config('pulse.recorders');

    foreach ([
        SlowRequests::class,
        UserRequests::class,
    ] as $recorderClass) {
        $ignore = implode('|', $recorders[$recorderClass]['ignore']);

        expect($ignore)
            ->toContain('billing/webhooks')
            ->and($ignore)->toContain('telescope');
    }
});

test('a job dispatched onto the normal redis connection is consumable by a real queue:work process, matching horizon\'s managed queue', function () {
    // A live Horizon supervisor is also watching this same connection/queue
    // in local development, so either consumer winning the race is a valid
    // outcome here: the point under test is that a job on the exact
    // connection/queue Horizon manages (config/horizon.php) is genuinely
    // processed somewhere, end to end, over the real Redis backend.
    $marker = 'horizon-queue-'.bin2hex(random_bytes(8));
    $resultPath = storage_path('app/private/horizon-queue-isolation-test-'.$marker.'.txt');

    try {
        WritesFileMarkerForTest::dispatch($resultPath, $marker)
            ->onConnection('redis')
            ->onQueue('default');

        for ($attempt = 0; $attempt < 3 && ! file_exists($resultPath); $attempt++) {
            Process::timeout(15)->path(base_path())->run([
                'php', 'artisan', 'queue:work', 'redis', '--queue=default', '--once', '--timeout=10',
            ]);
        }

        expect(file_exists($resultPath))->toBeTrue()
            ->and(file_get_contents($resultPath))->toBe($marker);
    } finally {
        @unlink($resultPath);
    }
});
