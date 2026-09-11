<?php

use App\Enums\BillingCollectionMethod;
use App\Enums\BillingPaymentStatus;
use App\Enums\BillingProvider;
use App\Models\BillingCustomer;
use App\Models\BillingInvoice;
use App\Models\BillingPayment;
use App\Models\BillingSubscription;
use App\Models\Organization;
use App\Models\PlatformAdmin;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

test('platform billing pages preserve the existing administrator boundary and expose only read routes', function () {
    $normalUser = User::factory()->create();
    $platformUser = User::factory()->create();

    PlatformAdmin::query()->create([
        'user_id' => $platformUser->getKey(),
    ]);

    $routes = [
        'admin.billing.index',
        'admin.billing.subscriptions.index',
        'admin.billing.payments.index',
    ];

    $this->get(route('admin.billing.index'))
        ->assertRedirect(route('login'));

    foreach ($routes as $routeName) {
        $this->actingAs($normalUser)
            ->get(route($routeName))
            ->assertForbidden();

        $this->actingAs($platformUser)
            ->get(route($routeName))
            ->assertOk();

        $route = Route::getRoutes()->getByName($routeName);

        expect($route)->not->toBeNull()
            ->and($route?->methods())->toBe(['GET', 'HEAD'])
            ->and($route?->gatherMiddleware())
            ->toContain('auth')
            ->toContain('verified')
            ->toContain('platform.admin');
    }

    expect(Route::has('admin.billing.store'))->toBeFalse()
        ->and(Route::has('admin.billing.update'))->toBeFalse()
        ->and(Route::has('admin.billing.destroy'))->toBeFalse()
        ->and(Route::has('admin.billing.subscriptions.store'))->toBeFalse()
        ->and(Route::has('admin.billing.payments.store'))->toBeFalse();
});

test('billing overview counts captures only from paid rows with paid timestamps and keeps currency and mode separate', function () {
    $platformUser = User::factory()->create();

    PlatformAdmin::query()->create([
        'user_id' => $platformUser->getKey(),
    ]);

    $largeAmount = 9_007_199_254_740_993;

    $livePhpInvoice = BillingInvoice::factory()->create([
        'currency' => 'PHP',
        'amount' => $largeAmount,
    ]);

    BillingPayment::factory()->create([
        'billing_invoice_id' => $livePhpInvoice->id,
        'currency' => 'PHP',
        'amount' => $largeAmount,
        'status' => BillingPaymentStatus::Paid,
        'livemode' => true,
        'paid_at' => now(),
    ]);

    $unconfirmedPaidInvoice = BillingInvoice::factory()->create([
        'currency' => 'PHP',
        'amount' => 90_000,
    ]);

    BillingPayment::factory()->create([
        'billing_invoice_id' => $unconfirmedPaidInvoice->id,
        'currency' => 'PHP',
        'amount' => 90_000,
        'status' => BillingPaymentStatus::Paid,
        'livemode' => true,
        'paid_at' => null,
    ]);

    $failedInvoice = BillingInvoice::factory()->create([
        'currency' => 'PHP',
        'amount' => 49_900,
    ]);

    BillingPayment::factory()->create([
        'billing_invoice_id' => $failedInvoice->id,
        'currency' => 'PHP',
        'amount' => 49_900,
        'status' => BillingPaymentStatus::Failed,
        'livemode' => true,
        'paid_at' => now(),
        'failed_at' => now(),
        'provider_error_code' => 'declined',
    ]);

    $testPhpInvoice = BillingInvoice::factory()->create([
        'currency' => 'PHP',
        'amount' => 20_000,
    ]);

    BillingPayment::factory()->create([
        'billing_invoice_id' => $testPhpInvoice->id,
        'currency' => 'PHP',
        'amount' => 20_000,
        'status' => BillingPaymentStatus::Paid,
        'livemode' => false,
        'paid_at' => now(),
    ]);

    $liveUsdInvoice = BillingInvoice::factory()->create([
        'currency' => 'USD',
        'amount' => 1_250,
    ]);

    BillingPayment::factory()->create([
        'billing_invoice_id' => $liveUsdInvoice->id,
        'currency' => 'USD',
        'amount' => 1_250,
        'status' => BillingPaymentStatus::Paid,
        'livemode' => true,
        'paid_at' => now(),
    ]);

    $this->actingAs($platformUser)
        ->get(route('admin.billing.index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('admin/billing/index')
                ->where('metrics.failedPaymentAttempts.live', 1)
                ->where('metrics.failedPaymentAttempts.test', 0)
                ->has('paymentSignals', 3)
                ->where('paymentSignals.0.currency', 'PHP')
                ->where('paymentSignals.0.livemode', true)
                ->where('paymentSignals.0.capturedCount', 1)
                ->where(
                    'paymentSignals.0.capturedAmountMinor',
                    (string) $largeAmount,
                )
                ->where('paymentSignals.0.failedCount', 1)
                ->where('paymentSignals.1.currency', 'PHP')
                ->where('paymentSignals.1.livemode', false)
                ->where('paymentSignals.1.capturedCount', 1)
                ->where('paymentSignals.1.capturedAmountMinor', '20000')
                ->where('paymentSignals.2.currency', 'USD')
                ->where('paymentSignals.2.livemode', true)
                ->where('paymentSignals.2.capturedCount', 1)
                ->where('paymentSignals.2.capturedAmountMinor', '1250'),
        );
});

test('billing overview groups plan projections by stable internal plan code without exposing external plan identifiers', function () {
    $platformUser = User::factory()->create();

    PlatformAdmin::query()->create([
        'user_id' => $platformUser->getKey(),
    ]);

    $stripeOrganization = Organization::factory()->create();
    $stripeCustomer = BillingCustomer::factory()->create([
        'organization_id' => $stripeOrganization->id,
        'provider' => BillingProvider::Stripe,
    ]);

    BillingSubscription::factory()->create([
        'billing_customer_id' => $stripeCustomer->id,
        'plan_code' => 'starter',
        'external_plan_id' => 'price_private_a',
        'livemode' => true,
    ]);

    BillingSubscription::factory()->create([
        'billing_customer_id' => $stripeCustomer->id,
        'plan_code' => 'starter',
        'external_plan_id' => 'price_private_b',
        'livemode' => true,
    ]);

    $payMongoOrganization = Organization::factory()->create();
    $payMongoCustomer = BillingCustomer::factory()->create([
        'organization_id' => $payMongoOrganization->id,
        'provider' => BillingProvider::PayMongo,
        'external_customer_id' => 'cus_paymongo_private',
    ]);

    BillingSubscription::factory()->create([
        'billing_customer_id' => $payMongoCustomer->id,
        'plan_code' => 'starter',
        'external_plan_id' => 'plan_private_a',
        'livemode' => true,
    ]);

    $this->actingAs($platformUser)
        ->get(route('admin.billing.index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->where('metrics.subscriptionProjections.live', 3)
                ->has('planMix', 2)
                ->where('planMix.0.planCode', 'starter')
                ->where('planMix.0.provider', 'paymongo')
                ->where('planMix.0.subscriptionCount', 1)
                ->missing('planMix.0.externalPlanId')
                ->where('planMix.1.planCode', 'starter')
                ->where('planMix.1.provider', 'stripe')
                ->where('planMix.1.subscriptionCount', 2)
                ->missing('planMix.1.externalPlanId'),
        );
});

test('subscription index is paginated filterable and exposes only minimized local projection fields', function () {
    $platformUser = User::factory()->create();

    PlatformAdmin::query()->create([
        'user_id' => $platformUser->getKey(),
    ]);

    BillingSubscription::factory()->count(26)->create([
        'plan_code' => 'starter',
        'livemode' => false,
    ]);

    $targetOrganization = Organization::factory()->create([
        'name' => 'Billing Search Target',
    ]);
    $targetCustomer = BillingCustomer::factory()->create([
        'organization_id' => $targetOrganization->id,
        'provider' => BillingProvider::PayMongo,
        'external_customer_id' => 'cus_private_target',
    ]);
    $target = BillingSubscription::factory()->create([
        'billing_customer_id' => $targetCustomer->id,
        'plan_code' => 'growth',
        'external_subscription_id' => 'sub_private_target',
        'external_plan_id' => 'plan_private_target',
        'collection_method' => BillingCollectionMethod::Manual,
        'provider_status' => 'active',
        'livemode' => true,
    ]);

    $this->actingAs($platformUser)
        ->get(route('admin.billing.subscriptions.index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->has('subscriptions', 25)
                ->where('pagination.per_page', 25)
                ->where('pagination.total', 27),
        );

    $this->actingAs($platformUser)
        ->get(route('admin.billing.subscriptions.index', [
            'search' => 'Billing Search Target',
            'provider' => BillingProvider::PayMongo->value,
            'plan' => 'growth',
            'mode' => 'live',
            'per_page' => 15,
        ]))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->has('subscriptions', 1)
                ->where('subscriptions.0.id', $target->id)
                ->where(
                    'subscriptions.0.organization.id',
                    $targetOrganization->id,
                )
                ->where(
                    'subscriptions.0.organization.name',
                    'Billing Search Target',
                )
                ->where('subscriptions.0.provider', 'paymongo')
                ->where('subscriptions.0.planCode', 'growth')
                ->where('subscriptions.0.collectionMethod', 'manual')
                ->where('subscriptions.0.providerStatus', 'active')
                ->where('subscriptions.0.livemode', true)
                ->missing('subscriptions.0.billing_customer_id')
                ->missing('subscriptions.0.billingCustomerId')
                ->missing('subscriptions.0.external_subscription_id')
                ->missing('subscriptions.0.externalSubscriptionId')
                ->missing('subscriptions.0.external_plan_id')
                ->missing('subscriptions.0.externalPlanId')
                ->where('filters.search', 'Billing Search Target')
                ->where('filters.provider', 'paymongo')
                ->where('filters.plan', 'growth')
                ->where('filters.mode', 'live')
                ->where('filters.perPage', 15),
        );
});

test('payment index preserves failed attempts supports filters and keeps provider-sensitive fields server-only', function () {
    $platformUser = User::factory()->create();

    PlatformAdmin::query()->create([
        'user_id' => $platformUser->getKey(),
    ]);

    BillingPayment::factory()->count(26)->create();

    $targetInvoice = BillingInvoice::factory()->create([
        'currency' => 'USD',
        'amount' => 12_345,
    ]);

    Organization::query()
        ->whereKey($targetInvoice->organization_id)
        ->update([
            'name' => 'Payment Search Target',
        ]);

    $target = BillingPayment::factory()->create([
        'billing_invoice_id' => $targetInvoice->id,
        'provider_request_key' => 'private-provider-request-key',
        'external_payment_intent_id' => 'pi_private_target',
        'external_payment_id' => 'pay_private_target',
        'currency' => 'USD',
        'amount' => 12_345,
        'status' => BillingPaymentStatus::Failed,
        'livemode' => true,
        'qr_code_url' => 'https://private.example.test/qr.png',
        'failed_at' => now(),
        'provider_error_code' => 'processor_declined',
    ]);

    $this->actingAs($platformUser)
        ->get(route('admin.billing.payments.index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->has('payments', 25)
                ->where('pagination.per_page', 25)
                ->where('pagination.total', 27),
        );

    $this->actingAs($platformUser)
        ->get(route('admin.billing.payments.index', [
            'search' => 'Payment Search Target',
            'status' => BillingPaymentStatus::Failed->value,
            'provider' => BillingProvider::Stripe->value,
            'currency' => 'USD',
            'mode' => 'live',
            'per_page' => 15,
        ]))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->has('payments', 1)
                ->where('payments.0.id', $target->id)
                ->where('payments.0.organization.name', 'Payment Search Target')
                ->where('payments.0.status', 'failed')
                ->where('payments.0.captured', false)
                ->where('payments.0.currency', 'USD')
                ->where('payments.0.amountMinor', '12345')
                ->where('payments.0.livemode', true)
                ->where(
                    'payments.0.providerErrorCode',
                    'processor_declined',
                )
                ->missing('payments.0.provider_request_key')
                ->missing('payments.0.providerRequestKey')
                ->missing('payments.0.qr_code_url')
                ->missing('payments.0.qrCodeUrl')
                ->missing('payments.0.external_payment_intent_id')
                ->missing('payments.0.externalPaymentIntentId')
                ->missing('payments.0.external_payment_id')
                ->missing('payments.0.externalPaymentId')
                ->where('filters.search', 'Payment Search Target')
                ->where('filters.status', 'failed')
                ->where('filters.provider', 'stripe')
                ->where('filters.currency', 'USD')
                ->where('filters.mode', 'live')
                ->where('filters.perPage', 15),
        );
});

test('billing indexes keep query counts bounded as page size grows', function () {
    $platformUser = User::factory()->create();

    PlatformAdmin::query()->create([
        'user_id' => $platformUser->getKey(),
    ]);

    BillingSubscription::factory()->count(30)->create([
        'plan_code' => 'starter',
    ]);
    BillingPayment::factory()->count(30)->create();

    $queries = [];

    DB::listen(
        function (QueryExecuted $query) use (&$queries): void {
            $queries[] = $query->sql;
        },
    );

    $subscriptionStart = count($queries);

    $this->actingAs($platformUser)
        ->get(route('admin.billing.subscriptions.index', ['per_page' => 15]))
        ->assertOk();

    $subscriptionSmall = collect(array_slice($queries, $subscriptionStart))
        ->filter(
            static fn (string $sql): bool => str_contains(
                $sql,
                'billing_subscriptions',
            ) || str_contains($sql, 'organizations'),
        )
        ->count();

    $subscriptionStart = count($queries);

    $this->actingAs($platformUser)
        ->get(route('admin.billing.subscriptions.index', ['per_page' => 50]))
        ->assertOk();

    $subscriptionLarge = collect(array_slice($queries, $subscriptionStart))
        ->filter(
            static fn (string $sql): bool => str_contains(
                $sql,
                'billing_subscriptions',
            ) || str_contains($sql, 'organizations'),
        )
        ->count();

    $paymentStart = count($queries);

    $this->actingAs($platformUser)
        ->get(route('admin.billing.payments.index', ['per_page' => 15]))
        ->assertOk();

    $paymentSmall = collect(array_slice($queries, $paymentStart))
        ->filter(
            static fn (string $sql): bool => str_contains(
                $sql,
                'billing_payments',
            ) || str_contains($sql, 'organizations'),
        )
        ->count();

    $paymentStart = count($queries);

    $this->actingAs($platformUser)
        ->get(route('admin.billing.payments.index', ['per_page' => 50]))
        ->assertOk();

    $paymentLarge = collect(array_slice($queries, $paymentStart))
        ->filter(
            static fn (string $sql): bool => str_contains(
                $sql,
                'billing_payments',
            ) || str_contains($sql, 'organizations'),
        )
        ->count();

    expect($subscriptionLarge)
        ->toBe($subscriptionSmall)
        ->toBeLessThanOrEqual(7)
        ->and($paymentLarge)
        ->toBe($paymentSmall)
        ->toBeLessThanOrEqual(7);
});

test('platform billing navigation uses generated Wayfinder routes without hardcoded admin links', function () {
    $layout = (string) file_get_contents(
        resource_path('js/layouts/platform-layout.tsx'),
    );
    $overview = (string) file_get_contents(
        resource_path('js/pages/admin/billing/index.tsx'),
    );
    $subscriptions = (string) file_get_contents(
        resource_path('js/pages/admin/billing/subscriptions.tsx'),
    );
    $payments = (string) file_get_contents(
        resource_path('js/pages/admin/billing/payments.tsx'),
    );

    expect($layout)
        ->toContain('PlatformBillingController.index()')
        ->not->toContain('href="/admin/billing"')
        ->and($overview)
        ->toContain('PlatformBillingController.subscriptions(')
        ->toContain('PlatformBillingController.payments(')
        ->not->toContain('href="/admin/')
        ->and($subscriptions)
        ->toContain('PlatformOrganizationController.show(')
        ->not->toContain('href="/admin/')
        ->and($payments)
        ->toContain('PlatformOrganizationController.show(')
        ->not->toContain('href="/admin/');
});
