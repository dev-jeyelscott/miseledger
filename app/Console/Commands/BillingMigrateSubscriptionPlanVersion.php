<?php

namespace App\Console\Commands;

use App\Actions\Audit\RecordAuditEntry;
use App\Enums\BillingCollectionMethod;
use App\Enums\PlanCode;
use App\Models\BillingPlanVersion;
use App\Models\BillingSubscription;
use App\Models\Organization;
use App\Support\Billing\PlanCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Operator-only, dry-run-first grandfathering tool. Repins one organization's
 * existing subscription to a different published version of its own plan
 * code. Publication never triggers this; it is always an explicit,
 * auditable, single-organization action. A local-only pin change is
 * permitted only when the subscription's current charge tuple
 * (provider + collection method + interval + currency + amount_minor) is
 * verified unchanged against the target version's price; anything else,
 * including an unverifiable current price, is refused rather than risking a
 * silent reprice. This command never contacts a payment provider and never
 * touches tenant/inventory state.
 */
final class BillingMigrateSubscriptionPlanVersion extends Command
{
    protected $signature = 'billing:migrate-subscription-plan-version
        {organization : billing-owning Organization ID}
        {target_version : Target billing_plan_versions.id, same plan code as the subscription}
        {--apply : Persist the pin migration instead of dry-running}';

    protected $description =
        'Explicitly repin one organization\'s subscription to another published version of its own plan code, refusing any change to its charge tuple.';

    public function __construct(
        private readonly PlanCatalog $planCatalog,
        private readonly RecordAuditEntry $recordAuditEntry,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $organizationId = (int) $this->argument('organization');
        $targetVersionId = (int) $this->argument('target_version');
        $apply = (bool) $this->option('apply');

        $organization = Organization::query()->find($organizationId);

        if ($organization === null) {
            $this->error("Organization [{$organizationId}] not found.");

            return self::FAILURE;
        }

        $targetVersion = BillingPlanVersion::query()
            ->whereKey($targetVersionId)
            ->whereNotNull('published_at')
            ->first();

        if ($targetVersion === null) {
            $this->error("Target version [{$targetVersionId}] is not a published commercial version.");

            return self::FAILURE;
        }

        $type = (string) config('billing.subscription_type');

        $subscription = BillingSubscription::query()
            ->where('organization_id', $organization->getKey())
            ->where('type', $type)
            ->first();

        if ($subscription === null) {
            $this->error('This organization has no provider-neutral subscription projection to migrate.');

            return self::FAILURE;
        }

        if ($subscription->plan_code !== $targetVersion->plan_code) {
            $this->error('The target version belongs to a different plan code than the subscription. Cross-plan migration is not permitted here.');

            return self::FAILURE;
        }

        if ($subscription->plan_version_id === $targetVersion->id) {
            $this->line('Subscription is already pinned to this version. No change.');

            return self::SUCCESS;
        }

        $currency = PlanCatalog::billingCurrency();

        if ($currency === null) {
            $this->error('Billing currency is not configured. Refusing an unverifiable migration.');

            return self::FAILURE;
        }

        $currentAmount = $this->resolveCurrentAmount($subscription, $currency);
        $targetPrice = $this->planCatalog->versionPrice(
            $targetVersion->id,
            $subscription->provider,
            $subscription->collection_method,
            (string) $subscription->interval,
            $currency,
        );

        if ($currentAmount === null || $targetPrice === null) {
            $this->error('Unable to verify the subscription\'s current charge tuple against the target version\'s price. Local-only migration is refused.');

            return self::FAILURE;
        }

        if ($currentAmount !== $targetPrice->amount_minor) {
            $this->error(sprintf(
                'Target version price (%d %s) differs from the subscription\'s current amount (%d %s). Price-changing migration requires an approved provider repricing workflow and is refused here.',
                $targetPrice->amount_minor,
                $currency,
                $currentAmount,
                $currency,
            ));

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Organization #%d, plan %s: charge tuple unchanged (%s %s %s %d %s). Migrating pin from version %s to version %d.',
            $organization->getKey(),
            $subscription->plan_code,
            $subscription->provider->value,
            $subscription->collection_method->value,
            $subscription->interval,
            $targetPrice->amount_minor,
            $currency,
            $subscription->plan_version_id ?? 'unset',
            $targetVersion->id,
        ));

        if (! $apply) {
            $this->line('Dry run only. No database changes were written.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($organization, $subscription, $targetVersion): void {
            $locked = BillingSubscription::query()
                ->whereKey($subscription->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->plan_version_id === $targetVersion->id) {
                return;
            }

            $previousVersionId = $locked->plan_version_id;

            $locked->update(['plan_version_id' => $targetVersion->id]);

            $this->recordAuditEntry->handle(
                $organization,
                null,
                'billing.subscription.plan_version_migrated',
                BillingSubscription::class,
                $locked->id,
                ['plan_version_id' => $previousVersionId],
                ['plan_version_id' => $targetVersion->id],
                'billing-migrate-subscription-plan-version:'.$locked->id.':'.$targetVersion->id,
                isDeduplicationKey: true,
            );
        }, attempts: 3);

        $this->info('Migration applied.');

        return self::SUCCESS;
    }

    /**
     * Resolve the subscription's current authoritative charge amount. When
     * already version-pinned, the pin's own price row is authoritative. An
     * unpinned manual subscription falls back to the legacy configuration
     * amount it was actually charging; an unpinned automatic (Stripe)
     * subscription has no locally verifiable amount and returns null,
     * which causes the migration to be refused above.
     */
    private function resolveCurrentAmount(BillingSubscription $subscription, string $currency): ?int
    {
        if ($subscription->plan_version_id !== null) {
            return $this->planCatalog->versionPrice(
                $subscription->plan_version_id,
                $subscription->provider,
                $subscription->collection_method,
                (string) $subscription->interval,
                $currency,
            )?->amount_minor;
        }

        if ($subscription->collection_method !== BillingCollectionMethod::Manual
            || $subscription->plan_code === null
            || $subscription->interval === null) {
            return null;
        }

        try {
            $planCode = PlanCode::from($subscription->plan_code);
        } catch (InvalidArgumentException) {
            return null;
        }

        return $this->planCatalog->get($planCode)?->manualAmount($subscription->interval);
    }
}
