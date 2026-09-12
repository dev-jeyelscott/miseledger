<?php

use App\Enums\BillingProvider;
use App\Enums\OrganizationRole;
use App\Models\BillingCustomer;
use App\Models\BillingSubscription;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Support\Billing\Providers\PayMongoBillingProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Http::preventStrayRequests();
    Cache::flush();

    Config::set(
        'billing.provider',
        'paymongo',
    );

    Config::set(
        'billing.providers.paymongo.enabled',
        true,
    );

    Config::set(
        'billing.providers.paymongo.mode',
        'test',
    );

    Config::set(
        'billing.providers.paymongo.api_base_url',
        'https://api.paymongo.test/v1',
    );

    Config::set(
        'billing.providers.paymongo.secret_key',
        'sk_test_never_leak',
    );

    Config::set(
        'billing.providers.paymongo.public_key',
        'pk_test_browser_safe',
    );

    Config::set(
        'billing.providers.paymongo.customer_phone',
        '09171234567',
    );

    Config::set('billing.plans', [
        'starter' => [
            'name' => 'Starter',
            'tier' => 1,
            'providers' => [
                'stripe' => [
                    'monthly' => 'price_starter_monthly',
                    'yearly' => null,
                ],
                'paymongo' => [
                    'monthly' => 'plan_starter_monthly',
                    'yearly' => null,
                ],
            ],
            'features' => [],
            'limits' => [],
        ],
    ]);
});

/**
 * @return array{0: User, 1: Organization}
 */
function phaseSixBillingOwner(): array
{
    $user = User::factory()->create([
        'name' => 'Jane Customer',
    ]);

    $organization = Organization::factory()->create([
        'trial_ends_at' => null,
    ]);

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create([
            'role' => OrganizationRole::Owner,
        ]);

    return [$user, $organization];
}

test(
    'PayMongo checkout remains pending until its provider-neutral lifecycle projection is synchronized',
    function (): void {
        [$user, $organization] = phaseSixBillingOwner();

        $customer = BillingCustomer::factory()
            ->for($organization)
            ->create([
                'provider' => BillingProvider::PayMongo,
                'external_customer_id' => 'cus_phase_six_123',
                'livemode' => false,
            ]);

        $subscription = BillingSubscription::factory()
            ->for($customer, 'billingCustomer')
            ->create([
                'organization_id' => $organization->getKey(),
                'provider' => BillingProvider::PayMongo,
                'type' => config('billing.subscription_type'),
                'external_subscription_id' => 'subs_phase_six_123',
                'external_plan_id' => 'plan_starter_monthly',
                'plan_code' => 'starter',
                'interval' => 'monthly',
                'provider_status' => 'incomplete',
                'livemode' => false,
                'ends_at' => null,
                'cancelled_at' => null,
            ]);

        $this->actingAs($user)
            ->get(
                route(
                    'organizations.billing.checkout.success',
                    $organization,
                ),
            )
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->component(
                        'organizations/billing/checkout-success',
                    )
                    ->where('synchronized', false)
                    ->where('subscription.status', null)
                    ->where(
                        'subscription.accessMode',
                        'read_only',
                    ),
            );

        $subscription->update([
            'provider_status' => 'active',
        ]);

        $this->actingAs($user)
            ->get(
                route(
                    'organizations.billing.checkout.success',
                    $organization,
                ),
            )
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->component(
                        'organizations/billing/checkout-success',
                    )
                    ->where('synchronized', true)
                    ->where(
                        'subscription.status',
                        'active',
                    )
                    ->where(
                        'subscription.accessMode',
                        'writable',
                    ),
            );
    },
);

test(
    'PayMongo rejects a subscription whose livemode conflicts with its local customer',
    function (): void {
        [$user, $organization] = phaseSixBillingOwner();

        BillingCustomer::factory()
            ->for($organization)
            ->create([
                'provider' => BillingProvider::PayMongo,
                'external_customer_id' => 'cus_phase_six_123',
                'livemode' => false,
            ]);

        Http::fake(
            function (Request $request): mixed {
                if ($request->method() === 'POST'
                    && str_ends_with(
                        $request->url(),
                        '/subscriptions',
                    )) {
                    return Http::response([
                        'data' => [
                            'id' => 'subs_phase_six_123',
                            'type' => 'subscription',
                            'attributes' => [
                                'customer_id' => 'cus_phase_six_123',
                                'status' => 'incomplete',
                                'livemode' => true,
                                'plan' => [
                                    'id' => 'plan_starter_monthly',
                                ],
                                'latest_invoice' => [
                                    'payment_intent' => [
                                        'id' => 'pi_phase_six_123',
                                    ],
                                ],
                                'next_billing_schedule' => '2026-10-12',
                                'cancelled_at' => null,
                            ],
                        ],
                    ]);
                }

                return Http::response([], 404);
            },
        );

        expect(
            fn () => app(PayMongoBillingProvider::class)
                ->startCheckout(
                    $organization,
                    'plan_starter_monthly',
                    'https://miseledger.test/success',
                    'https://miseledger.test/cancel',
                    [],
                    $user,
                ),
        )->toThrow(
            RuntimeException::class,
            'PayMongo returned an invalid subscription response.',
        );

        expect(
            BillingSubscription::query()->count(),
        )->toBe(0);

        Http::assertSentCount(1);
    },
);
