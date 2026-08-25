<?php

use App\Actions\Billing\NotifyOrganizationBillingLifecycle;
use App\Actions\Billing\ProcessOrganizationBillingWebhookEffect;
use App\Enums\BillingLifecycleEvent;
use App\Enums\OrganizationRole;
use App\Jobs\SendOrganizationBillingLifecycleNotification;
use App\Models\BillingWebhookEffect;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Notifications\BillingLifecycleNotification;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

function billingQueueRecipient(string $stripeCustomerId): array
{
    $organization = Organization::factory()->create(['stripe_id' => $stripeCustomerId]);
    $user = User::factory()->create();

    OrganizationMembership::factory()->for($organization)->for($user)->create([
        'role' => OrganizationRole::Owner,
    ]);

    return compact('organization', 'user');
}

test('billing reconciliation is scheduler-owned and protected from concurrent runs', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event): bool => str_contains($event->command, 'billing:reconcile'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 * * * *')
        ->and($event->withoutOverlapping)->toBeTrue()
        ->and($event->onOneServer)->toBeTrue()
        ->and($event->runInBackground)->toBeTrue();
});

test('billing lifecycle delivery is uniquely queued and idempotent for duplicate webhook effects', function () {
    config()->set('queue.default', 'database');
    Notification::fake();

    $recipient = billingQueueRecipient('cus_queue_duplicate');

    $process = app(ProcessOrganizationBillingWebhookEffect::class);

    $process->handle(
        'evt_queue_duplicate',
        $recipient['organization']->stripe_id,
        BillingLifecycleEvent::PaymentFailed,
        'billing.subscription.past_due',
    );
    $process->handle(
        'evt_queue_duplicate',
        $recipient['organization']->stripe_id,
        BillingLifecycleEvent::PaymentFailed,
        'billing.subscription.past_due',
    );

    $effect = BillingWebhookEffect::query()->sole();

    expect($this->app['db']->table('jobs')->count())->toBe(1);

    $job = new SendOrganizationBillingLifecycleNotification(
        $effect->getKey(),
        $recipient['organization']->getKey(),
        'evt_queue_duplicate',
        $recipient['organization']->stripe_id,
    );

    $job->handle(app(NotifyOrganizationBillingLifecycle::class));
    $job->handle(app(NotifyOrganizationBillingLifecycle::class));

    Notification::assertSentTo($recipient['user'], BillingLifecycleNotification::class, 1);
    expect($effect->fresh()->notification_dispatched_at)->not->toBeNull();
});

test('billing jobs have bounded retries and report terminal failures with organization context', function () {
    $recipient = billingQueueRecipient('cus_queue_failure');
    $effect = BillingWebhookEffect::query()->create([
        'organization_id' => $recipient['organization']->getKey(),
        'stripe_event_id' => 'evt_queue_failure',
        'lifecycle_event' => BillingLifecycleEvent::PaymentFailed,
    ]);
    $job = new SendOrganizationBillingLifecycleNotification(
        $effect->getKey(),
        $recipient['organization']->getKey(),
        'evt_queue_failure',
        $recipient['organization']->stripe_id,
    );
    $exception = new RuntimeException('mail transport unavailable');

    Notification::shouldReceive('sendNow')->once()->andThrow($exception);

    expect(fn (): mixed => $job->handle(app(NotifyOrganizationBillingLifecycle::class)))
        ->toThrow(RuntimeException::class);

    Log::shouldReceive('channel')->once()->andReturnSelf();
    Log::shouldReceive('error')->once()->withArgs(
        fn (string $message, array $context): bool => $message === 'Billing operational signal emitted.'
            && $context['event'] === 'billing.queue.failure'
            && $context['organization_id'] === $recipient['organization']->getKey()
            && $context['job'] === SendOrganizationBillingLifecycleNotification::class
            && $context['exception'] === RuntimeException::class,
    );

    $job->failed($exception);

    expect($job->tries)->toBe(3)
        ->and($job->backoff)->toBe([60, 300, 900])
        ->and($job->timeout)->toBe(60)
        ->and($job->uniqueId())->toBe('evt_queue_failure')
        ->and(config('queue.connections.database.retry_after'))->toBeGreaterThan($job->timeout)
        ->and(config('queue.connections.redis.retry_after'))->toBeGreaterThan($job->timeout)
        ->and(config('queue.failed.driver'))->toBe('database-uuids')
        ->and($effect->fresh()->notification_dispatched_at)->toBeNull();
});

test('a caught delivery failure leaves the dispatch marker unset so a retry delivers exactly once', function () {
    $recipient = billingQueueRecipient('cus_queue_crash_boundary');
    $effect = BillingWebhookEffect::query()->create([
        'organization_id' => $recipient['organization']->getKey(),
        'stripe_event_id' => 'evt_queue_crash_boundary',
        'lifecycle_event' => BillingLifecycleEvent::PaymentFailed,
    ]);
    $job = new SendOrganizationBillingLifecycleNotification(
        $effect->getKey(),
        $recipient['organization']->getKey(),
        'evt_queue_crash_boundary',
        $recipient['organization']->stripe_id,
    );

    Notification::shouldReceive('sendNow')->once()->andThrow(new RuntimeException('mail transport unavailable'));

    expect(fn (): mixed => $job->handle(app(NotifyOrganizationBillingLifecycle::class)))
        ->toThrow(RuntimeException::class);

    expect($effect->fresh()->notification_dispatched_at)->toBeNull()
        ->and($effect->fresh()->notification_claimed_at)->not->toBeNull();

    Notification::fake();

    $job->handle(app(NotifyOrganizationBillingLifecycle::class));
    $job->handle(app(NotifyOrganizationBillingLifecycle::class));

    Notification::assertSentTo($recipient['user'], BillingLifecycleNotification::class, 1);
    expect($effect->fresh()->notification_dispatched_at)->not->toBeNull();
});

test('a worker crash after intent is claimed but before delivery completes is recoverable without duplicate delivery', function () {
    $recipient = billingQueueRecipient('cus_queue_worker_crash');
    $effect = BillingWebhookEffect::query()->create([
        'organization_id' => $recipient['organization']->getKey(),
        'stripe_event_id' => 'evt_queue_worker_crash',
        'lifecycle_event' => BillingLifecycleEvent::PaymentFailed,
    ]);

    // Simulates a worker that crashed uncaught after the durable claim was committed but
    // before delivery was attempted or the completion marker was stamped.
    $effect->update(['notification_claimed_at' => now()]);

    Notification::fake();

    $job = new SendOrganizationBillingLifecycleNotification(
        $effect->getKey(),
        $recipient['organization']->getKey(),
        'evt_queue_worker_crash',
        $recipient['organization']->stripe_id,
    );

    $job->handle(app(NotifyOrganizationBillingLifecycle::class));
    $job->handle(app(NotifyOrganizationBillingLifecycle::class));

    Notification::assertSentTo($recipient['user'], BillingLifecycleNotification::class, 1);
    expect($effect->fresh()->notification_dispatched_at)->not->toBeNull();
});

test('local subscription authorization succeeds without resolving a queue connection', function () {
    $recipient = billingQueueRecipient('cus_queue_independent_authorization');

    $this->withoutExceptionHandling();
    Queue::shouldReceive('connection')->never();

    $this->actingAs($recipient['user'])
        ->put(route('organizations.settings.update', $recipient['organization']), [
            'name' => $recipient['organization']->name,
            'slug' => $recipient['organization']->slug,
            'timezone' => $recipient['organization']->timezone,
            'currency' => $recipient['organization']->currency,
            'active' => true,
        ])
        ->assertRedirect(route('dashboard'));
});
