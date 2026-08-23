<?php

namespace App\Http\Controllers\Billing;

use App\Enums\OrganizationPermission;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class OrganizationBillingPortalController extends Controller
{
    /**
     * Redirect the authorized member to Stripe's hosted billing portal for
     * the requested organization, using Cashier's built-in portal session
     * creation. Only reachable for organizations with an existing Stripe
     * customer; commercial state itself is never read, mutated, or
     * duplicated here.
     */
    public function store(Organization $organization): Response
    {
        Gate::authorize(
            OrganizationPermission::BillingManage->value,
            $organization,
        );

        $portalUrl = $organization->billingPortalUrl(
            route('organizations.billing.show', $organization),
        );

        return Inertia::location($portalUrl);
    }
}
