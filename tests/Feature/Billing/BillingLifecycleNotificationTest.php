<?php

use App\Enums\BillingLifecycleEvent;
use App\Enums\OrganizationAccessMode;
use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\StockMovement;
use App\Models\User;
use App\Notifications\BillingLifecycleNotification;
use App\Support\Billing\OrganizationSubscriptionAccessResolver;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;

function postBillingLifecycleWebhook(array $payload, string $secret = 'whsec_billing_lifecycle'): TestResponse
{
    Config::set('cashier.webhook.secret', $secret);

    $body = json_encode($payload);
    $timestamp = time();
    $signature = hash_hmac('sha256', "{$timestamp}.{$body}", $secret);

    return test()->call(
        'POST',
        route('cashier.webhook'),
        [],
        [],
        [],
        [
            'HTTP_STRIPE-SIGNATURE' => "t={$timestamp},v1={$signature}",
            'CONTENT_TYPE' => 'application/json',
        ],
        $body,
    );
}

function billingLifecycleSubscriptionPayload(
    string $customerId,
    string $subscriptionId,
    array $overrides = [],
): array {
    return array_replace_recursive([
        'id' => 'evt_'.str()->random(16),
        'type' => 'customer.subscription.updated',
        'data' => [
            'object' => [
                'id' => $subscriptionId,
                'customer' => $customerId,
                'status' => 'active',
                'cancel_at_period_end' => false,
                'items' => [
                    'data' => [[
                        'id' => 'si_'.str()->random(14),
                        'price' => [
                            'id' => 'price_billing_lifecycle',
                            'product' => 'prod_billing_lifecycle',
                        ],
                        'quantity' => 1,
                    ]],
                ],
            ],
        ],
    ], $overrides);
}

/**
 * @return array{organization: Organization, billingAdministrator: User, nonBillingMember: User, bystander: User}
 */
function billingLifecycleRecipients(string $stripeCustomerId): array
{
    $organization = Organization::factory()->create(['stripe_id' => $stripeCustomerId]);
    $billingAdministrator = User::factory()->create();
    $nonBillingMember = User::factory()->create();
    $bystander = User::factory()->create();

    OrganizationMembership::factory()->for($organization)->for($billingAdministrator)->create([
        'role' => OrganizationRole::Owner,
    ]);
    OrganizationMembership::factory()->for($organization)->for($nonBillingMember)->create([
        'role' => OrganizationRole::Manager,
    ]);
    OrganizationMembership::factory()
        ->for(Organization::factory()->create(['stripe_id' => 'cus_bystander']))
        ->for($bystander)
        ->create(['role' => OrganizationRole::Owner]);

    return compact('organization', 'billingAdministrator', 'nonBillingMember', 'bystander');
}

test('billing-authorized members receive each lifecycle notification without notifying other tenants', function (
    array $payload,
    BillingLifecycleEvent $expectedEvent,
) {
    Notification::fake();

    $recipients = billingLifecycleRecipients('cus_billing_lifecycle');

    if ($expectedEvent === BillingLifecycleEvent::SubscriptionEnded) {
        $recipients['organization']->subscriptions()->create([
            'type' => config('billing.subscription_type'),
            'stripe_id' => 'sub_billing_lifecycle',
            'stripe_status' => 'active',
            'stripe_price' => 'price_billing_lifecycle',
            'quantity' => 1,
        ]);
    }

    postBillingLifecycleWebhook($payload)->assertOk();

    Notification::assertSentTo(
        $recipients['billingAdministrator'],
        BillingLifecycleNotification::class,
        fn (BillingLifecycleNotification $notification): bool => $notification->organization->is($recipients['organization'])
            && $notification->event === $expectedEvent,
    );
    Notification::assertNotSentTo($recipients['nonBillingMember'], BillingLifecycleNotification::class);
    Notification::assertNotSentTo($recipients['bystander'], BillingLifecycleNotification::class);
})->with([
    'trial ending' => [
        [
            'id' => 'evt_trial_ending',
            'type' => 'customer.subscription.trial_will_end',
            'data' => ['object' => ['customer' => 'cus_billing_lifecycle']],
        ],
        BillingLifecycleEvent::TrialEnding,
    ],
    'payment failure' => [
        [
            'id' => 'evt_payment_failure',
            'type' => 'invoice.payment_failed',
            'data' => ['object' => ['customer' => 'cus_billing_lifecycle', 'payment_intent' => 'pi_secret_value']],
        ],
        BillingLifecycleEvent::PaymentFailed,
    ],
    'scheduled cancellation' => [
        billingLifecycleSubscriptionPayload('cus_billing_lifecycle', 'sub_billing_lifecycle', [
            'data' => ['object' => ['cancel_at_period_end' => true, 'current_period_end' => now()->addWeek()->timestamp]],
        ]),
        BillingLifecycleEvent::ScheduledCancellation,
    ],
    'subscription end' => [
        billingLifecycleSubscriptionPayload('cus_billing_lifecycle', 'sub_billing_lifecycle', [
            'type' => 'customer.subscription.deleted',
        ]),
        BillingLifecycleEvent::SubscriptionEnded,
    ],
    'recovery' => [
        billingLifecycleSubscriptionPayload('cus_billing_lifecycle', 'sub_billing_lifecycle', [
            'data' => ['previous_attributes' => ['status' => 'past_due']],
        ]),
        BillingLifecycleEvent::Recovered,
    ],
]);

test('billing lifecycle notification content excludes payment secrets', function () {
    $organization = Organization::factory()->create(['name' => 'Example Organization']);
    $notification = new BillingLifecycleNotification($organization, BillingLifecycleEvent::PaymentFailed);

    $message = $notification->toMail(User::factory()->make());
    $content = $message->subject.' '.implode(' ', $message->introLines);

    expect($content)->toContain('billing settings')
        ->not->toContain('pi_secret_value')
        ->not->toContain('card')
        ->not->toContain('payment method');
});

test('a notification dispatch failure does not change synchronized commercial access or ledger data', function () {
    $recipients = billingLifecycleRecipients('cus_notification_failure');
    $organization = $recipients['organization'];

    Notification::shouldReceive('send')->once()->andThrow(new RuntimeException('mail transport unavailable'));

    postBillingLifecycleWebhook(billingLifecycleSubscriptionPayload(
        'cus_notification_failure',
        'sub_notification_failure',
        ['data' => ['object' => ['status' => 'past_due']]],
    ))->assertOk();

    expect($organization->fresh()->subscription(config('billing.subscription_type'))?->stripe_status)
        ->toBe('past_due')
        ->and(OrganizationSubscriptionAccessResolver::resolve($organization->fresh())->accessMode)
        ->toBe(OrganizationAccessMode::Writable)
        ->and(StockMovement::query()->where('organization_id', $organization->id)->count())
        ->toBe(0);
});
