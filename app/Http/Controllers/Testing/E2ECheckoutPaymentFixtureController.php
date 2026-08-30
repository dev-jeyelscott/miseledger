<?php

namespace App\Http\Controllers\Testing;

use App\Enums\OrganizationPermission;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Deterministically populate the session-scoped PayMongo checkout payload
 * consumed by checkout-success, without contacting any payment provider.
 * Registered only when `APP_ENV=testing`, so the Playwright harness can
 * exercise the card-collection form's pending/error/retry behavior against
 * an isolated test database.
 */
class E2ECheckoutPaymentFixtureController extends Controller
{
    public function show(Organization $organization): RedirectResponse
    {
        Gate::authorize(
            OrganizationPermission::BillingManage->value,
            $organization,
        );

        return to_route(
            'organizations.billing.checkout.success',
            $organization,
        )->with('billing.checkout.payment', [
            'payment_intent_id' => 'pi_e2e_fixture',
            'client_key' => 'ck_e2e_fixture',
            'public_key' => 'pk_e2e_fixture',
            'api_base_url' => 'https://e2e-paymongo.invalid',
        ]);
    }
}
