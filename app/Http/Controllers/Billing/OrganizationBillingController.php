<?php

namespace App\Http\Controllers\Billing;

use App\Enums\BillingProvider;
use App\Enums\OrganizationPermission;
use App\Http\Controllers\Controller;
use App\Models\BillingSubscription;
use App\Models\Organization;
use App\Support\Billing\OrganizationSubscriptionAccess;
use App\Support\Billing\OrganizationSubscriptionAccessResolver;
use App\Support\Billing\OrganizationUsageOverview;
use App\Support\Billing\OrganizationUsageOverviewResolver;
use App\Support\Billing\PlanCatalog;
use App\Support\Billing\PlanUpgradePolicy;
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
        PlanUpgradePolicy $upgradePolicy,
    ): Response {
        Gate::authorize(
            OrganizationPermission::BillingManage->value,
            $organization,
        );

        $access = OrganizationSubscriptionAccessResolver::resolve($organization, $planCatalog);
        $subscriptions = $organization->billingSubscriptions()
            ->where('type', (string) config('billing.subscription_type'))
            ->get();
        $subscription = $subscriptions->count() === 1 ? $subscriptions->sole() : null;

        return Inertia::render('organizations/billing/index', [
            'organization' => $this->organizationData($organization),
            'subscription' => $this->subscriptionData($access, $subscription, $planCatalog),
            'entitlements' => $this->entitlementData($access, $organization, $planCatalog),
            'availablePlans' => $this->availablePlansData($planCatalog, $providerManager, $upgradePolicy, $access, $subscription),
            'manualQrPhEnabled' => config('billing.provider') === BillingProvider::PayMongo->value
                && config('billing.providers.paymongo.manual_qrph') === true,
        ]);
    }

    /**
     * @return array{id: int, name: string, slug: string, timezone: string}
     */
    private function organizationData(Organization $organization): array
    {
        return [
            'id' => $organization->id,
            'name' => $organization->name,
            'slug' => $organization->slug,
            'timezone' => $organization->timezone,
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
     *     billingWarning: bool,
     *     planName: string|null,
     *     interval: string|null,
     *     nextBillingAt: string|null,
     *     management: 'portal'|'cancel'|'none',
     *     collectionMethod: string|null
     * }
     */
    private function subscriptionData(OrganizationSubscriptionAccess $access, ?BillingSubscription $subscription, PlanCatalog $planCatalog): array
    {
        $plan = $access->plan !== null ? $planCatalog->get($access->plan) : null;
        $paidAccessEndsAt = $subscription === null
            ? null
            : ($subscription->ends_at
                ?? $subscription->current_period_ends_at
                ?? $subscription->next_billing_at);

        return [
            'plan' => $access->plan?->value,
            'status' => $access->subscriptionStatus,
            'accessMode' => $access->accessMode->value,
            'onTrial' => $access->onTrial,
            'trialEndsAt' => $access->trialEndsAt?->toISOString(),
            'endsAt' => $access->endsAt?->toISOString(),
            'billingWarning' => $access->billingWarning,
            'planName' => $plan?->name,
            'interval' => $subscription?->interval,
            'nextBillingAt' => $subscription?->next_billing_at?->toISOString(),
            'management' => $this->managementType($subscription, $paidAccessEndsAt?->isFuture() === true),
            'collectionMethod' => $subscription?->collection_method->value,
        ];
    }

    /** @return 'portal'|'cancel'|'none' */
    private function managementType(?BillingSubscription $subscription, bool $hasPaidAccessEnd): string
    {
        if ($subscription === null) {
            return 'none';
        }

        return match ($subscription->provider) {
            BillingProvider::Stripe => 'portal',
            BillingProvider::PayMongo => $subscription->collection_method->value === 'manual'
                ? 'none'
                : ($subscription->cancelled_at === null && $hasPaidAccessEnd ? 'cancel' : 'none'),
        };
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
     * `eligibleUpgrade` is only ever true for a manual PayMongo subscription
     * (the only upgrade path currently supported), at the subscription's
     * existing interval -- never inferred for Stripe or any other state.
     *
     * @return list<array{code: string, name: string, monthly: bool, yearly: bool, features: list<string>, limits: array<string, int|null>, current: bool, eligibleUpgrade: bool}>
     */
    private function availablePlansData(
        PlanCatalog $planCatalog,
        BillingProviderManager $providerManager,
        PlanUpgradePolicy $upgradePolicy,
        OrganizationSubscriptionAccess $access,
        ?BillingSubscription $subscription,
    ): array {
        $provider = $providerManager->defaultProvider();
        $usesManualQrPh = $provider === BillingProvider::PayMongo
            && config('billing.providers.paymongo.manual_qrph') === true;

        $canUpgrade = $access->plan !== null
            && $subscription !== null
            && $subscription->provider === BillingProvider::PayMongo
            && $subscription->collection_method->value === 'manual'
            && $subscription->interval !== null;

        return array_map(
            static function ($definition) use ($usesManualQrPh, $provider, $access, $upgradePolicy, $canUpgrade, $subscription) {
                $isCurrent = $access->plan !== null && $access->plan->value === $definition->code->value;
                $eligibleUpgrade = $canUpgrade
                    && ! $isCurrent
                    && $upgradePolicy->isEligibleUpgrade($access->plan, $definition->code)
                    && $definition->manualAmount($subscription->interval) !== null;

                return [
                    'code' => $definition->code->value,
                    'name' => $definition->name,
                    'monthly' => $usesManualQrPh
                        ? $definition->manualAmount('monthly') !== null
                        : $definition->externalPlanId($provider, 'monthly') !== null,
                    'yearly' => $usesManualQrPh
                        ? $definition->manualAmount('yearly') !== null
                        : $definition->externalPlanId($provider, 'yearly') !== null,
                    'features' => $definition->features,
                    'limits' => $definition->limits,
                    'current' => $isCurrent,
                    'eligibleUpgrade' => $eligibleUpgrade,
                ];
            },
            $planCatalog->all(),
        );
    }
}
