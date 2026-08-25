<?php

namespace App\Http\Controllers\Billing;

use App\Enums\OrganizationPermission;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Support\Billing\BillingObservability;
use App\Support\Billing\Providers\BillingProviderManager;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class OrganizationBillingPortalController extends Controller
{
    public function __construct(
        private readonly BillingObservability $observability,
        private readonly BillingProviderManager $providerManager,
    ) {}

    /**
     * Redirect the authorized member to the hosted billing portal for the
     * requested organization, resolving the servicing provider from the
     * organization's persisted subscription ownership rather than the
     * currently configured acquisition provider. Only reachable for
     * organizations with an existing provider customer; commercial state
     * itself is never read, mutated, or duplicated here.
     */
    public function store(Organization $organization): Response
    {
        Gate::authorize(
            OrganizationPermission::BillingManage->value,
            $organization,
        );

        try {
            $provider = $this->providerManager->providerForOrganization($organization);

            $portalUrl = $provider->billingPortalUrl(
                $organization,
                route('organizations.billing.show', $organization),
            );
        } catch (\Throwable $exception) {
            $this->observability->portalFailure($organization, $exception);

            throw $exception;
        }

        return Inertia::location($portalUrl);
    }
}
