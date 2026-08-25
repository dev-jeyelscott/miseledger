<?php

namespace App\Http\Controllers\Billing;

use App\Enums\OrganizationPermission;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Support\Billing\OrganizationSubscriptionAccess;
use App\Support\Billing\OrganizationSubscriptionAccessResolver;
use App\Support\Billing\OrganizationUsageOverview;
use App\Support\Billing\OrganizationUsageOverviewResolver;
use App\Support\Billing\PlanCatalog;
use App\Support\Billing\Providers\BillingProviderManager;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationBillingController extends Controller
{
    /**
     * Show the organization's billing workspace, deriving plan, subscription
     * status, trial, warning, usage, and limits entirely from the shared P2
     * subscription/entitlement context. Available even when the
     * organization is commercially read-only, so the billing administrator
     * can always reach the applicable subscription-recovery action.
     */
    public function show(
        Organization $organization,
        PlanCatalog $planCatalog,
        BillingProviderManager $providerManager,
    ): Response {
        Gate::authorize(
            OrganizationPermission::BillingManage->value,
            $organization,
        );

        $access = OrganizationSubscriptionAccessResolver::resolve($organization, $planCatalog);

        return Inertia::render('organizations/billing/index', [
            'organization' => $this->organizationData($organization),
            'subscription' => $this->subscriptionData($access),
            'entitlements' => $this->entitlementData($access, $organization, $planCatalog),
            'availablePlans' => $this->availablePlansData($planCatalog, $providerManager),
        ]);
    }

    /**
     * @return array{id: int, name: string, slug: string}
     */
    private function organizationData(Organization $organization): array
    {
        return [
            'id' => $organization->id,
            'name' => $organization->name,
            'slug' => $organization->slug,
        ];
    }

    /**
     * Serialize only safe commercial state, matching the shared Inertia
     * subscription context shape. Never exposes Stripe secrets, customer
     * identifiers, payment-method tokens, or raw Cashier/Stripe objects.
     *
     * @return array{
     *     plan: string|null,
     *     status: string|null,
     *     accessMode: string,
     *     onTrial: bool,
     *     trialEndsAt: string|null,
     *     endsAt: string|null,
     *     billingWarning: bool
     * }
     */
    private function subscriptionData(OrganizationSubscriptionAccess $access): array
    {
        return [
            'plan' => $access->plan?->value,
            'status' => $access->subscriptionStatus,
            'accessMode' => $access->accessMode->value,
            'onTrial' => $access->onTrial,
            'trialEndsAt' => $access->trialEndsAt?->toISOString(),
            'endsAt' => $access->endsAt?->toISOString(),
            'billingWarning' => $access->billingWarning,
        ];
    }

    /**
     * Serialize only the resolved plan's declared feature codes and
     * quantitative limits, using the configuration-owned plan catalog
     * rather than any Stripe usage/metering data.
     *
     * @return array{features: list<string>, limits: array<string, int|null>, usage: array<string, array{current: int, limit: int|null, isUnlimited: bool, atLimit: bool}>}
     */
    private function entitlementData(OrganizationSubscriptionAccess $access, Organization $organization, PlanCatalog $planCatalog): array
    {
        $definition = $access->plan !== null ? $planCatalog->get($access->plan) : null;

        $usage = $this->usageData(
            OrganizationUsageOverviewResolver::forOrganization($organization, $access, $planCatalog),
        );

        if ($definition === null) {
            return ['features' => [], 'limits' => [], 'usage' => $usage];
        }

        return [
            'features' => $definition->features,
            'limits' => $definition->limits,
            'usage' => $usage,
        ];
    }

    /**
     * Serialize current-usage-versus-limit guidance for display only. The
     * server remains the sole enforcement boundary for creation requests.
     *
     * @param  array<string, OrganizationUsageOverview>  $overview
     * @return array<string, array{current: int, limit: int|null, isUnlimited: bool, atLimit: bool}>
     */
    private function usageData(array $overview): array
    {
        return array_map(
            static fn (OrganizationUsageOverview $item): array => [
                'current' => $item->current,
                'limit' => $item->limit,
                'isUnlimited' => $item->isUnlimited,
                'atLimit' => $item->atLimit,
            ],
            $overview,
        );
    }

    /**
     * Serialize only internal plan metadata and availability for the
     * effective acquisition provider, never its external plan identifiers.
     *
     * @return list<array{code: string, name: string, monthly: bool, yearly: bool, features: list<string>, limits: array<string, int|null>}>
     */
    private function availablePlansData(PlanCatalog $planCatalog, BillingProviderManager $providerManager): array
    {
        $provider = $providerManager->defaultProvider();

        return array_values(array_map(
            static fn ($definition) => [
                'code' => $definition->code->value,
                'name' => $definition->name,
                'monthly' => $definition->externalPlanId($provider, 'monthly') !== null,
                'yearly' => $definition->externalPlanId($provider, 'yearly') !== null,
                'features' => $definition->features,
                'limits' => $definition->limits,
            ],
            $planCatalog->all(),
        ));
    }
}
