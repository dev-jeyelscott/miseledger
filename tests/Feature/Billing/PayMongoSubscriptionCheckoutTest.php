<?php

use App\Enums\BillingProvider;
use App\Enums\OrganizationRole;
use App\Models\BillingCustomer;
use App\Models\BillingSubscription;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Support\Billing\OrganizationSubscriptionAccessResolver;
use App\Support\Billing\Providers\PayMongoBillingProvider;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

beforeEach(function (): void {
    Http::preventStrayRequests();
    Cache::flush();

    Config::set(
        'billing.provider',
        'paymongo',
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

function fakePayMongoCheckout(
    string $customerId = 'cus_paymongo_123',
): void {
    Http::fake(
        function (
            Request $request,
        ) use ($customerId): mixed {
            if ($request->method() === 'POST'
                && str_ends_with(
                    $request->url(),
                    '/customers',
                )) {
                return Http::response([
                    'data' => [
                        'id' => $customerId,
                        'type' => 'customer',
                        'attributes' => [
                            'livemode' => false,
                        ],
                    ],
                ]);
            }

            if ($request->method() === 'POST'
                && str_ends_with(
                    $request->url(),
                    '/subscriptions',
                )) {
                return Http::response([
                    'data' => [
                        'id' => 'subs_paymongo_123',
                        'type' => 'subscription',
                        'attributes' => [
                            'customer_id' => $customerId,
                            'status' => 'incomplete',
                            'livemode' => false,
                            'plan' => [
                                'id' => 'plan_starter_monthly',
                            ],
                            'latest_invoice' => [
                                'payment_intent' => [
                                    'id' => 'pi_paymongo_123',
                                ],
                            ],
                            'next_billing_schedule' => '2026-09-01',
                            'cancelled_at' => null,
                        ],
                    ],
                ]);
            }

            if ($request->method() === 'GET'
                && str_ends_with(
                    $request->url(),
                    '/payment_intents/pi_paymongo_123',
                )) {
                return Http::response([
                    'data' => [
                        'id' => 'pi_paymongo_123',
                        'type' => 'payment_intent',
                        'attributes' => [
                            'client_key' => 'pi_paymongo_123_client_safe',
                        ],
                    ],
                ]);
            }

            return Http::response([], 404);
        },
    );
}

/**
 * @return array{0: User, 1: Organization}
 */
function payMongoBillingOwner(): array
{
    $user = User::factory()->create([
        'name' => 'Jane Customer',
    ]);

    $organization =
        Organization::factory()->create([
            'trial_ends_at' => null,
        ]);

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create([
            'role' => OrganizationRole::Owner,
        ]);

    return [
        $user,
        $organization,
    ];
}

test(
    'it creates a PayMongo customer with a normalized Philippine contact number',
    function (): void {
        fakePayMongoCheckout();

        [$user, $organization] =
            payMongoBillingOwner();

        $customer =
            app(PayMongoBillingProvider::class)
                ->ensureCustomer(
                    $organization,
                    $user,
                );

        expect($customer->provider)
            ->toBe(BillingProvider::PayMongo)
            ->and(
                $customer->external_customer_id,
            )
            ->toBe('cus_paymongo_123');

        Http::assertSent(
            fn (Request $request): bool => $request->method() === 'POST'
                && str_ends_with(
                    $request->url(),
                    '/customers',
                )
                && data_get(
                    $request->data(),
                    'data.attributes.phone',
                ) === '+639171234567'
                && data_get(
                    $request->data(),
                    'data.attributes.default_device',
                ) === 'email'
                && str_starts_with(
                    (string) (
                        $request->header(
                            'Idempotency-Key',
                        )[0]
                        ?? ''
                    ),
                    "miseledger:paymongo:customer:{$organization->getKey()}:v3:",
                ),
        );
    },
);

test(
    'creates a pending PayMongo projection without granting access or leaking private provider identities',
    function (): void {
        fakePayMongoCheckout();

        [$user, $organization] =
            payMongoBillingOwner();

        $this->actingAs($user)
            ->post(
                route(
                    'organizations.billing.checkout',
                    $organization,
                ),
                [
                    'plan' => 'starter',
                    'interval' => 'monthly',
                ],
            )
            ->assertRedirect(
                route(
                    'organizations.billing.checkout.success',
                    $organization,
                ),
            );

        $customer =
            BillingCustomer::query()->sole();

        $subscription =
            BillingSubscription::query()->sole();

        expect($customer->provider)
            ->toBe(BillingProvider::PayMongo)
            ->and(
                $subscription
                    ->billing_customer_id,
            )
            ->toBe($customer->id)
            ->and(
                $subscription
                    ->external_subscription_id,
            )
            ->toBe('subs_paymongo_123')
            ->and(
                $subscription
                    ->external_plan_id,
            )
            ->toBe('plan_starter_monthly')
            ->and(
                $subscription->plan_code,
            )
            ->toBe('starter')
            ->and(
                $subscription->interval,
            )
            ->toBe('monthly')
            ->and(
                $subscription
                    ->provider_status,
            )
            ->toBe('incomplete')
            ->and(
                OrganizationSubscriptionAccessResolver::resolve(
                    $organization,
                )->accessMode->value,
            )
            ->toBe('read_only');

        $response =
            $this->actingAs($user)->get(
                route(
                    'organizations.billing.checkout.success',
                    $organization,
                ),
            );

        $response->assertInertia(
            fn ($page) => $page
                ->where(
                    'payment.paymentIntentId',
                    'pi_paymongo_123',
                )
                ->missing(
                    'payment.externalCustomerId',
                )
                ->missing(
                    'payment.externalPlanId',
                ),
        );

        expect($response->getContent())
            ->not->toContain(
                'cus_paymongo_123',
            )
            ->not->toContain(
                'plan_starter_monthly',
            )
            ->not->toContain(
                'sk_test_never_leak',
            );

        Http::assertSent(
            fn (Request $request): bool => $request->method() === 'POST'
                && str_ends_with(
                    $request->url(),
                    '/customers',
                )
                && data_get(
                    $request->data(),
                    'data.attributes.phone',
                ) === '+639171234567'
                && data_get(
                    $request->data(),
                    'data.attributes.default_device',
                ) === 'email'
                && str_starts_with(
                    (string) (
                        $request->header(
                            'Idempotency-Key',
                        )[0]
                        ?? ''
                    ),
                    "miseledger:paymongo:customer:{$organization->getKey()}:v3:",
                ),
        );
    },
);

test(
    'reuses the organization customer and pending checkout outcome without duplicate PayMongo customer creation',
    function (): void {
        [$user, $organization] =
            payMongoBillingOwner();

        BillingCustomer::factory()
            ->for($organization)
            ->create([
                'provider' => BillingProvider::PayMongo,
                'external_customer_id' => 'cus_existing_123',
            ]);

        fakePayMongoCheckout(
            'cus_existing_123',
        );

        $this->actingAs($user)->post(
            route(
                'organizations.billing.checkout',
                $organization,
            ),
            [
                'plan' => 'starter',
                'interval' => 'monthly',
            ],
        );

        $this->actingAs($user)->post(
            route(
                'organizations.billing.checkout',
                $organization,
            ),
            [
                'plan' => 'starter',
                'interval' => 'monthly',
            ],
        );

        Http::assertNotSent(
            fn (Request $request): bool => $request->method() === 'POST'
                && str_ends_with(
                    $request->url(),
                    '/customers',
                ),
        );

        Http::assertSentCount(2);

        expect(
            BillingCustomer::query()->count(),
        )
            ->toBe(1)
            ->and(
                BillingSubscription::query()
                    ->count(),
            )
            ->toBe(1);
    },
);

test(
    'rejects a new PayMongo checkout when a durable subscription projection already exists',
    function (): void {
        [$user, $organization] = payMongoBillingOwner();

        $customer = BillingCustomer::factory()
            ->for($organization)
            ->create([
                'provider' => BillingProvider::PayMongo,
                'external_customer_id' => 'cus_existing_123',
            ]);

        BillingSubscription::factory()
            ->for($customer, 'billingCustomer')
            ->create([
                'organization_id' => $organization->getKey(),
                'provider' => BillingProvider::PayMongo,
                'type' => config('billing.subscription_type'),
                'external_plan_id' => 'plan_starter_monthly',
                'plan_code' => 'starter',
                'interval' => 'monthly',
                'provider_status' => 'incomplete',
                'ends_at' => null,
                'cancelled_at' => null,
            ]);

        fakePayMongoCheckout();

        $this->actingAs($user)
            ->post(
                route('organizations.billing.checkout', $organization),
                [
                    'plan' => 'starter',
                    'interval' => 'monthly',
                ],
            )
            ->assertInvalid(['organization']);

        Http::assertNothingSent();
    },
);

test(
    'rejects a concurrent PayMongo checkout attempt while the lock lease outlives the old short lease',
    function (): void {
        [$user, $organization] = payMongoBillingOwner();

        Sleep::fake(true, true);

        $nestedAttempted = false;

        Http::fake(
            function (Request $request) use ($organization, $user, &$nestedAttempted): mixed {
                if ($request->method() === 'POST'
                    && str_ends_with($request->url(), '/customers')) {
                    if (! $nestedAttempted) {
                        $nestedAttempted = true;

                        // Simulate the first checkout's provider work taking
                        // longer than the old 10s lock lease, but well within
                        // the new lease, while the durable subscription and
                        // pending-checkout cache entry are not yet written.
                        Carbon::setTestNow(Carbon::now()->addSeconds(15));

                        $concurrentResponse = $this->actingAs($user)->post(
                            route('organizations.billing.checkout', $organization),
                            [
                                'plan' => 'starter',
                                'interval' => 'monthly',
                            ],
                        );

                        $concurrentResponse->assertInvalid(['organization']);
                    }

                    return Http::response([
                        'data' => [
                            'id' => 'cus_paymongo_123',
                            'type' => 'customer',
                            'attributes' => [
                                'livemode' => false,
                            ],
                        ],
                    ]);
                }

                if ($request->method() === 'POST'
                    && str_ends_with($request->url(), '/subscriptions')) {
                    return Http::response([
                        'data' => [
                            'id' => 'subs_paymongo_123',
                            'type' => 'subscription',
                            'attributes' => [
                                'customer_id' => 'cus_paymongo_123',
                                'status' => 'incomplete',
                                'livemode' => false,
                                'plan' => [
                                    'id' => 'plan_starter_monthly',
                                ],
                                'latest_invoice' => [
                                    'payment_intent' => [
                                        'id' => 'pi_paymongo_123',
                                    ],
                                ],
                                'next_billing_schedule' => '2026-09-01',
                                'cancelled_at' => null,
                            ],
                        ],
                    ]);
                }

                if ($request->method() === 'GET'
                    && str_ends_with($request->url(), '/payment_intents/pi_paymongo_123')) {
                    return Http::response([
                        'data' => [
                            'id' => 'pi_paymongo_123',
                            'type' => 'payment_intent',
                            'attributes' => [
                                'client_key' => 'pi_paymongo_123_client_safe',
                            ],
                        ],
                    ]);
                }

                return Http::response([], 404);
            },
        );

        $this->actingAs($user)
            ->post(
                route('organizations.billing.checkout', $organization),
                [
                    'plan' => 'starter',
                    'interval' => 'monthly',
                ],
            )
            ->assertRedirect(
                route('organizations.billing.checkout.success', $organization),
            );

        Carbon::setTestNow();
        Sleep::fake(false);

        expect($nestedAttempted)->toBeTrue()
            ->and(BillingCustomer::query()->count())->toBe(1)
            ->and(BillingSubscription::query()->count())->toBe(1);
    },
);

test(
    'rethrows a PayMongo subscription insert failure when the race-resolution lookup finds no row',
    function (): void {
        fakePayMongoCheckout();

        [$user, $organization] = payMongoBillingOwner();

        $this->withoutExceptionHandling();

        $insertAttempts = 0;
        $shouldFailInsert = true;

        DB::connection()->beforeExecuting(
            function (string $query, array $bindings) use (&$insertAttempts, &$shouldFailInsert): void {
                if (! $shouldFailInsert || ! str_starts_with(strtolower(trim($query)), 'insert') || ! str_contains($query, 'billing_subscriptions')) {
                    return;
                }

                $insertAttempts++;

                throw new QueryException(
                    DB::getDefaultConnection(),
                    $query,
                    $bindings,
                    new RuntimeException('forced PayMongo subscription insert failure'),
                );
            },
        );

        expect(fn (): mixed => $this->actingAs($user)->post(
            route('organizations.billing.checkout', $organization),
            [
                'plan' => 'starter',
                'interval' => 'monthly',
            ],
        ))->toThrow(QueryException::class);

        $shouldFailInsert = false;

        expect($insertAttempts)->toBe(1)
            ->and(BillingSubscription::query()->count())->toBe(0);
    },
);
