<?php

use App\Enums\BillingProvider;
use App\Enums\OrganizationRole;
use App\Models\BillingCustomer;
use App\Models\BillingSubscription;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Support\Billing\OrganizationSubscriptionAccessResolver;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Http::preventStrayRequests();
    Cache::flush();
    Config::set('billing.provider', 'paymongo');
    Config::set('billing.providers.paymongo.api_base_url', 'https://api.paymongo.test/v1');
    Config::set('billing.providers.paymongo.secret_key', 'sk_test_never_leak');
    Config::set('billing.providers.paymongo.public_key', 'pk_test_browser_safe');
    Config::set('billing.providers.paymongo.customer_phone', '09171234567');
    Config::set('billing.plans', ['starter' => ['name' => 'Starter', 'providers' => ['stripe' => ['monthly' => 'price_starter_monthly', 'yearly' => null], 'paymongo' => ['monthly' => 'plan_starter_monthly', 'yearly' => null]], 'features' => [], 'limits' => []]]);
});

function fakePayMongoCheckout(string $customerId = 'cus_paymongo_123'): void
{
    Http::fake(function (Request $request) use ($customerId): mixed {
        if ($request->method() === 'POST' && str_ends_with($request->url(), '/customers')) {
            return Http::response(['data' => ['id' => $customerId, 'type' => 'customer', 'attributes' => ['livemode' => false]]]);
        }

        if ($request->method() === 'POST' && str_ends_with($request->url(), '/subscriptions')) {
            return Http::response(['data' => ['id' => 'subs_paymongo_123', 'type' => 'subscription', 'attributes' => ['customer_id' => $customerId, 'status' => 'incomplete', 'livemode' => false, 'plan' => ['id' => 'plan_starter_monthly'], 'latest_invoice' => ['payment_intent' => ['id' => 'pi_paymongo_123']], 'next_billing_schedule' => '2026-09-01', 'cancelled_at' => null]]]);
        }

        if ($request->method() === 'GET' && str_ends_with($request->url(), '/payment_intents/pi_paymongo_123')) {
            return Http::response(['data' => ['id' => 'pi_paymongo_123', 'type' => 'payment_intent', 'attributes' => ['client_key' => 'pi_paymongo_123_client_safe']]]);
        }

        return Http::response([], 404);
    });
}

function payMongoBillingOwner(): array
{
    $user = User::factory()->create(['name' => 'Jane Customer']);
    $organization = Organization::factory()->create(['trial_ends_at' => null]);
    OrganizationMembership::factory()->for($organization)->for($user)->create(['role' => OrganizationRole::Owner]);

    return [$user, $organization];
}

test('creates a pending PayMongo projection without granting access or leaking private provider identities', function () {
    fakePayMongoCheckout();
    [$user, $organization] = payMongoBillingOwner();

    $this->actingAs($user)->post(route('organizations.billing.checkout', $organization), ['plan' => 'starter', 'interval' => 'monthly'])
        ->assertRedirect(route('organizations.billing.checkout.success', $organization));

    $customer = BillingCustomer::query()->sole();
    $subscription = BillingSubscription::query()->sole();
    expect($customer->provider)->toBe(BillingProvider::PayMongo)
        ->and($subscription->billing_customer_id)->toBe($customer->id)
        ->and($subscription->external_subscription_id)->toBe('subs_paymongo_123')
        ->and($subscription->external_plan_id)->toBe('plan_starter_monthly')
        ->and($subscription->plan_code)->toBe('starter')
        ->and($subscription->interval)->toBe('monthly')
        ->and($subscription->provider_status)->toBe('incomplete')
        ->and(OrganizationSubscriptionAccessResolver::resolve($organization)->accessMode->value)->toBe('read_only');

    $response = $this->actingAs($user)->get(route('organizations.billing.checkout.success', $organization));
    $response->assertInertia(fn ($page) => $page->where('payment.paymentIntentId', 'pi_paymongo_123')->missing('payment.externalCustomerId')->missing('payment.externalPlanId'));
    expect($response->getContent())->not->toContain('cus_paymongo_123')->not->toContain('plan_starter_monthly')->not->toContain('sk_test_never_leak');

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && str_ends_with($request->url(), '/customers')
        && data_get($request->data(), 'data.attributes.phone') === '09171234567');
});

test('reuses the organization customer and pending checkout outcome without duplicate PayMongo customer creation', function () {
    [$user, $organization] = payMongoBillingOwner();
    BillingCustomer::factory()->for($organization)->create(['provider' => BillingProvider::PayMongo, 'external_customer_id' => 'cus_existing_123']);
    fakePayMongoCheckout('cus_existing_123');

    $this->actingAs($user)->post(route('organizations.billing.checkout', $organization), ['plan' => 'starter', 'interval' => 'monthly']);
    $this->actingAs($user)->post(route('organizations.billing.checkout', $organization), ['plan' => 'starter', 'interval' => 'monthly']);

    Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST' && str_ends_with($request->url(), '/customers'));
    Http::assertSentCount(2);
    expect(BillingCustomer::query()->count())->toBe(1)->and(BillingSubscription::query()->count())->toBe(1);
});
