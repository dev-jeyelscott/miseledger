<?php

use App\Enums\BillingProvider;
use App\Enums\OrganizationRole;
use App\Models\BillingCustomer;
use App\Models\BillingSubscription;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

function managedPayMongoSubscription(): array
{
    Config::set('billing.providers.paymongo.api_base_url', 'https://paymongo.test/v1');
    Config::set('billing.providers.paymongo.secret_key', 'sk_test_paymongo');

    $organization = Organization::factory()->create();
    $customer = BillingCustomer::factory()->for($organization)->create([
        'provider' => BillingProvider::PayMongo,
        'external_customer_id' => 'cus_paymongo_management',
    ]);
    $subscription = BillingSubscription::factory()->for($customer, 'billingCustomer')->create([
        'organization_id' => $organization->getKey(),
        'provider' => BillingProvider::PayMongo,
        'type' => config('billing.subscription_type'),
        'external_subscription_id' => 'subs_paymongo_management',
        'plan_code' => 'starter',
        'interval' => 'monthly',
        'provider_status' => 'active',
        'current_period_ends_at' => now()->addMonth(),
        'next_billing_at' => now()->addMonth(),
    ]);

    return compact('organization', 'subscription');
}

test('an authorized owner can cancel a PayMongo subscription while commercially read-only and keeps paid access', function () {
    $context = managedPayMongoSubscription();
    $user = User::factory()->create();
    OrganizationMembership::factory()->for($context['organization'])->for($user)->create(['role' => OrganizationRole::Owner]);
    $context['subscription']->update(['provider_status' => 'past_due']);

    Http::fake([
        'https://paymongo.test/v1/subscriptions/subs_paymongo_management/cancel' => Http::response([
            'data' => ['id' => 'subs_paymongo_management', 'type' => 'subscription', 'attributes' => ['status' => 'cancelled']],
        ]),
    ]);

    $this->actingAs($user)->post(route('organizations.billing.cancel', $context['organization']))
        ->assertRedirect(route('organizations.billing.show', $context['organization']));

    $subscription = $context['subscription']->fresh();
    expect($subscription->provider_status)->toBe('cancelled')
        ->and($subscription->cancelled_at)->not->toBeNull()
        ->and($subscription->ends_at)->not->toBeNull();
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://paymongo.test/v1/subscriptions/subs_paymongo_management/cancel');
});

test('PayMongo cancellation is organization-scoped and rejects an owner of another organization', function () {
    $context = managedPayMongoSubscription();
    $user = User::factory()->create();
    OrganizationMembership::factory()->for(Organization::factory())->for($user)->create(['role' => OrganizationRole::Owner]);

    $this->actingAs($user)->post(route('organizations.billing.cancel', $context['organization']))->assertForbidden();
});
