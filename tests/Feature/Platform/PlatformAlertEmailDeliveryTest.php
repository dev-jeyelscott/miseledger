<?php

use App\Actions\Platform\Alerts\RecordPlatformAlertObservation;
use App\Enums\PlatformAlertSeverity;
use App\Enums\PlatformAlertType;
use App\Jobs\SendPlatformAlertEmail;
use App\Models\PlatformAdmin;
use App\Models\PlatformAlertDelivery;
use App\Models\User;
use App\Notifications\PlatformAlertNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

function recordDeliveryTestAlert(PlatformAlertSeverity $severity = PlatformAlertSeverity::Warning)
{
    return app(RecordPlatformAlertObservation::class)->handle(
        fingerprint: 'delivery.test.fingerprint',
        type: PlatformAlertType::DatabaseHealth,
        source: 'test-source',
        severity: $severity,
        title: 'Delivery test alert',
        summary: 'Delivery test summary',
        context: [],
    );
}

test('opening an alert queues exactly one email job per current platform admin', function () {
    Queue::fake();

    $admin1 = User::factory()->create();
    PlatformAdmin::query()->create(['user_id' => $admin1->getKey()]);
    $admin2 = User::factory()->create();
    PlatformAdmin::query()->create(['user_id' => $admin2->getKey()]);
    $nonAdmin = User::factory()->create();

    recordDeliveryTestAlert();

    Queue::assertPushed(SendPlatformAlertEmail::class, 2);
    expect(PlatformAlertDelivery::query()->count())->toBe(2);
    expect(
        PlatformAlertDelivery::query()->where('recipient_user_id', $nonAdmin->getKey())->exists(),
    )->toBeFalse();
});

test('a routine evaluator rerun before escalation does not resend an identical email', function () {
    Queue::fake();

    $admin = User::factory()->create();
    PlatformAdmin::query()->create(['user_id' => $admin->getKey()]);

    recordDeliveryTestAlert();
    recordDeliveryTestAlert();
    recordDeliveryTestAlert();

    Queue::assertPushed(SendPlatformAlertEmail::class, 1);
    expect(PlatformAlertDelivery::query()->count())->toBe(1);
});

test('a material severity escalation queues a second, distinct email', function () {
    Queue::fake();

    $admin = User::factory()->create();
    PlatformAdmin::query()->create(['user_id' => $admin->getKey()]);

    recordDeliveryTestAlert(PlatformAlertSeverity::Warning);
    recordDeliveryTestAlert(PlatformAlertSeverity::Critical);

    Queue::assertPushed(SendPlatformAlertEmail::class, 2);
    expect(PlatformAlertDelivery::query()->count())->toBe(2);
});

test('a platform admin revoked before the alert opens receives no email', function () {
    Queue::fake();

    $admin = User::factory()->create();
    $grant = PlatformAdmin::query()->create(['user_id' => $admin->getKey()]);
    $grant->delete();

    recordDeliveryTestAlert();

    Queue::assertNotPushed(SendPlatformAlertEmail::class);
    expect(PlatformAlertDelivery::query()->count())->toBe(0);
});

test('the queued job sends the notification and marks the delivery sent', function () {
    Queue::fake();
    Notification::fake();

    $admin = User::factory()->create();
    PlatformAdmin::query()->create(['user_id' => $admin->getKey()]);

    recordDeliveryTestAlert();

    $delivery = PlatformAlertDelivery::query()->firstOrFail();

    (new SendPlatformAlertEmail($delivery->id))->handle();

    $delivery->refresh();
    expect($delivery->sent_at)->not->toBeNull();

    Notification::assertSentTo($admin, PlatformAlertNotification::class);
});

test('the job does not resend once a delivery is already marked sent', function () {
    Queue::fake();
    Notification::fake();

    $admin = User::factory()->create();
    PlatformAdmin::query()->create(['user_id' => $admin->getKey()]);

    recordDeliveryTestAlert();
    $delivery = PlatformAlertDelivery::query()->firstOrFail();
    $delivery->forceFill(['sent_at' => now()])->save();

    (new SendPlatformAlertEmail($delivery->id))->handle();

    Notification::assertNothingSent();
});

test('alert persistence is not rolled back when email delivery fails', function () {
    Queue::fake();

    $admin = User::factory()->create();
    PlatformAdmin::query()->create(['user_id' => $admin->getKey()]);

    $alert = recordDeliveryTestAlert();

    Notification::shouldReceive('send')->andThrow(new Exception('mail down'));

    $delivery = PlatformAlertDelivery::query()->firstOrFail();

    expect(function () use ($delivery) {
        (new SendPlatformAlertEmail($delivery->id))->handle();
    })->toThrow(Exception::class);

    expect($alert->fresh())->not->toBeNull();
    $delivery->refresh();
    expect($delivery->sent_at)->toBeNull();
});
