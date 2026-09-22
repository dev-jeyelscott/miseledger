<?php

use App\Actions\Platform\Alerts\RecordPlatformAlertObservation;
use App\Actions\Platform\Alerts\ResolvePlatformAlertObservation;
use App\Enums\PlatformAlertSeverity;
use App\Enums\PlatformAlertState;
use App\Enums\PlatformAlertType;
use App\Models\PlatformAlert;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
});

function recordTestAlert(
    string $fingerprint = 'test.fingerprint',
    PlatformAlertSeverity $severity = PlatformAlertSeverity::Warning,
): PlatformAlert {
    return app(RecordPlatformAlertObservation::class)->handle(
        fingerprint: $fingerprint,
        type: PlatformAlertType::DatabaseHealth,
        source: 'test-source',
        severity: $severity,
        title: 'Test alert',
        summary: 'Test summary',
        context: ['probe' => 'value'],
    );
}

test('recording a new observation opens exactly one alert', function () {
    $alert = recordTestAlert();

    expect($alert->state)->toBe(PlatformAlertState::Open)
        ->and($alert->occurrence_count)->toBe(1)
        ->and(PlatformAlert::query()->count())->toBe(1);
});

test('repeated observation of the same fingerprint updates last_seen_at and occurrence_count instead of duplicating', function () {
    $first = recordTestAlert();
    $firstSeenAt = $first->first_seen_at;

    $this->travel(1)->hours();

    $second = recordTestAlert();

    expect(PlatformAlert::query()->count())->toBe(1)
        ->and($second->id)->toBe($first->id)
        ->and($second->occurrence_count)->toBe(2)
        ->and($second->first_seen_at->equalTo($firstSeenAt))->toBeTrue()
        ->and($second->last_seen_at->greaterThan($firstSeenAt))->toBeTrue();
});

test('a higher severity observation escalates the open alert instead of downgrading a lower one', function () {
    recordTestAlert(severity: PlatformAlertSeverity::Warning);
    $escalated = recordTestAlert(severity: PlatformAlertSeverity::Critical);

    expect($escalated->severity)->toBe(PlatformAlertSeverity::Critical);

    $downgraded = recordTestAlert(severity: PlatformAlertSeverity::Info);

    expect($downgraded->severity)->toBe(PlatformAlertSeverity::Critical);
});

test('the database enforces at most one open alert per fingerprint even bypassing the action', function () {
    recordTestAlert('race.fingerprint');

    expect(function () {
        DB::table('platform_alerts')->insert([
            'fingerprint' => 'race.fingerprint',
            'type' => PlatformAlertType::DatabaseHealth->value,
            'source' => 'test-source',
            'severity' => PlatformAlertSeverity::Warning->value,
            'state' => PlatformAlertState::Open->value,
            'title' => 'Duplicate',
            'summary' => 'Duplicate',
            'context' => json_encode([]),
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'occurrence_count' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    })->toThrow(UniqueConstraintViolationException::class);
});

test('resolving an open alert preserves its history instead of deleting it', function () {
    $alert = recordTestAlert('resolve.fingerprint');

    app(ResolvePlatformAlertObservation::class)->handle('resolve.fingerprint');

    $alert->refresh();

    expect($alert->state)->toBe(PlatformAlertState::Resolved)
        ->and($alert->resolved_at)->not->toBeNull()
        ->and(PlatformAlert::query()->count())->toBe(1);
});

test('resolving a fingerprint with no open alert is an idempotent no-op', function () {
    app(ResolvePlatformAlertObservation::class)->handle('never-opened.fingerprint');

    expect(PlatformAlert::query()->count())->toBe(0);
});

test('recurrence after resolution opens a new occurrence while resolved history remains visible', function () {
    $first = recordTestAlert('recurring.fingerprint');
    app(ResolvePlatformAlertObservation::class)->handle('recurring.fingerprint');

    $second = recordTestAlert('recurring.fingerprint');

    expect($second->id)->not->toBe($first->id)
        ->and($second->state)->toBe(PlatformAlertState::Open)
        ->and(PlatformAlert::query()->where('fingerprint', 'recurring.fingerprint')->count())->toBe(2);

    $first->refresh();
    expect($first->state)->toBe(PlatformAlertState::Resolved);
});
