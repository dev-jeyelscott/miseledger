<?php

use App\Enums\BillingCollectionMethod;
use App\Enums\BillingProvider;
use App\Enums\OrganizationRole;
use App\Models\BillingCustomer;
use App\Models\BillingRenewalReminder;
use App\Models\BillingSubscription;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Notifications\ManualRenewalReminderNotification;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;

$manualRenewalReminderBillingConfig = null;

beforeEach(function () use (&$manualRenewalReminderBillingConfig): void {
    $manualRenewalReminderBillingConfig = config('billing');

    Config::set('billing.currency', 'PHP');
    Config::set('billing.plans.starter.manual_amounts', ['monthly' => 49_900, 'yearly' => null]);
});

afterEach(function () use (&$manualRenewalReminderBillingConfig): void {
    Config::set('billing', $manualRenewalReminderBillingConfig);
});

test('it queues each manual renewal reminder once with a server-calculated invoice', function (): void {
    Notification::fake();
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    OrganizationMembership::factory()->for($organization)->for($user)->create(['role' => OrganizationRole::Owner]);
    $customer = BillingCustomer::factory()->for($organization)->create(['provider' => BillingProvider::PayMongo, 'livemode' => false]);
    $subscription = BillingSubscription::factory()->for($customer, 'billingCustomer')->create([
        'organization_id' => $organization->getKey(),
        'provider' => BillingProvider::PayMongo,
        'external_subscription_id' => null,
        'type' => config('billing.subscription_type'),
        'collection_method' => BillingCollectionMethod::Manual,
        'plan_code' => 'starter',
        'interval' => 'monthly',
        'provider_status' => 'active',
        'current_period_ends_at' => now()->addDays(7)->startOfDay(),
        'livemode' => false,
    ]);

    $this->artisan('billing:send-renewal-reminders')
        ->expectsOutputToContain('Queued 1 manual renewal reminder')
        ->assertExitCode(0);
    $this->artisan('billing:send-renewal-reminders')
        ->expectsOutputToContain('Queued 0 manual renewal reminder')
        ->assertExitCode(0);

    expect(BillingRenewalReminder::query()->count())->toBe(1)
        ->and($subscription->invoices()->count())->toBe(1)
        ->and($subscription->invoices()->sole()->amount)->toBe(49_900);
    Notification::assertSentTo($user, ManualRenewalReminderNotification::class, function (ManualRenewalReminderNotification $notification): bool {
        return $notification->daysBeforeDue === 7
            && $notification->invoice->amount === 49_900;
    });
});
