<?php

namespace App\Actions\Billing;

use App\Enums\BillingCollectionMethod;
use App\Enums\BillingInvoiceStatus;
use App\Enums\BillingInvoiceType;
use App\Enums\BillingPaymentStatus;
use App\Enums\PlanCode;
use App\Models\BillingInvoice;
use App\Models\BillingSubscription;
use App\Support\Billing\PlanCatalog;
use App\Support\Billing\PlanUpgradePolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Creates or reuses an invoice representing a requested manual subscription
 * upgrade without mutating entitlement before authoritative payment.
 */
final class CreateUpgradeInvoice
{
    public function __construct(
        private readonly PlanCatalog $planCatalog,
        private readonly PlanUpgradePolicy $upgradePolicy,
    ) {}

    /** Create or reuse the currently valid prorated upgrade invoice. */
    public function handle(BillingSubscription $subscription, PlanCode $targetPlan): BillingInvoice
    {
        return DB::transaction(function () use ($subscription, $targetPlan): BillingInvoice {
            $subscription = BillingSubscription::query()
                ->whereKey($subscription->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($subscription->collection_method !== BillingCollectionMethod::Manual
                || $subscription->plan_code === null
                || $subscription->interval === null
                || $subscription->cancelled_at !== null) {
                throw new RuntimeException('This subscription cannot be upgraded manually.');
            }

            if ($subscription->current_period_ends_at === null || ! $subscription->current_period_ends_at->isFuture()) {
                throw new RuntimeException('This subscription must be renewed before it can be upgraded.');
            }

            $currentPlan = PlanCode::from($subscription->plan_code);

            if (! $this->upgradePolicy->isEligibleUpgrade($currentPlan, $targetPlan)) {
                throw new RuntimeException('This plan change is not a supported upgrade.');
            }

            $currency = config('billing.currency');
            $currency = is_string($currency) ? mb_strtoupper($currency) : null;

            if ($currency === null || preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
                throw new RuntimeException('Manual upgrade pricing is unavailable.');
            }

            $targetPlanVersionId = null;
            $sourcePlanVersionId = $subscription->plan_version_id;

            if ((bool) config('billing.versioned_catalog_enabled')) {
                $targetPlanVersionId = $this->planCatalog->currentVersionId($targetPlan);

                if ($targetPlanVersionId === null || $sourcePlanVersionId === null) {
                    throw new RuntimeException('The selected plan is not available for manual QR Ph billing.');
                }
            }

            $amount = $this->proratedAmount(
                $subscription,
                $currentPlan,
                $targetPlan,
                $currency,
                $sourcePlanVersionId,
                $targetPlanVersionId,
            );

            $existing = BillingInvoice::query()
                ->where('billing_subscription_id', $subscription->id)
                ->where('invoice_type', BillingInvoiceType::Upgrade)
                ->where('target_plan_code', $targetPlan->value)
                ->whereIn('status', [BillingInvoiceStatus::Pending, BillingInvoiceStatus::PaymentPending])
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                if ($existing->amount === $amount && $existing->currency === $currency) {
                    return $existing;
                }

                $this->cancelStaleInvoice($existing);
            }

            return BillingInvoice::query()->create([
                'organization_id' => $subscription->organization_id,
                'billing_subscription_id' => $subscription->id,
                'provider' => $subscription->provider,
                'invoice_number' => 'INV-'.Str::upper((string) Str::ulid()),
                'plan_code' => $subscription->plan_code,
                'plan_version_id' => $sourcePlanVersionId,
                'target_plan_code' => $targetPlan->value,
                'target_plan_version_id' => $targetPlanVersionId,
                'invoice_type' => BillingInvoiceType::Upgrade,
                'billing_interval' => $subscription->interval,
                'currency' => $currency,
                'amount' => $amount,
                'status' => BillingInvoiceStatus::Pending,
                'period_starts_at' => now(),
                'period_ends_at' => $subscription->current_period_ends_at,
                'due_at' => now(),
            ]);
        }, attempts: 3);
    }

    /** Cancel a stale upgrade invoice and its still-unpaid payment attempts. */
    private function cancelStaleInvoice(BillingInvoice $invoice): void
    {
        $invoice->payments()
            ->whereIn('status', [BillingPaymentStatus::Pending, BillingPaymentStatus::AwaitingPayment])
            ->get()
            ->each(fn ($payment) => $payment->update([
                'status' => BillingPaymentStatus::Failed,
                'failed_at' => now(),
            ]));

        $invoice->update([
            'status' => BillingInvoiceStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
    }

    /**
     * Calculate the current remaining-period price difference for an
     * upgrade using exact integer arithmetic (never floating point). When
     * both source and target versions are pinned, pricing is read from
     * their frozen `billing_plan_version_prices` rows exclusively; only the
     * legacy configuration snapshot is used before versioned-catalog
     * cutover.
     */
    private function proratedAmount(
        BillingSubscription $subscription,
        PlanCode $currentPlan,
        PlanCode $targetPlan,
        string $currency,
        ?int $sourcePlanVersionId,
        ?int $targetPlanVersionId,
    ): int {
        $interval = $subscription->interval;

        if ($sourcePlanVersionId !== null && $targetPlanVersionId !== null) {
            $oldAmount = $this->planCatalog->versionPrice(
                $sourcePlanVersionId,
                $subscription->provider,
                $subscription->collection_method,
                $interval,
                $currency,
            )?->amount_minor;

            $newAmount = $this->planCatalog->versionPrice(
                $targetPlanVersionId,
                $subscription->provider,
                $subscription->collection_method,
                $interval,
                $currency,
            )?->amount_minor;
        } else {
            $oldAmount = $this->planCatalog->get($currentPlan)?->manualAmount($interval);
            $newAmount = $this->planCatalog->get($targetPlan)?->manualAmount($interval);
        }

        if ($oldAmount === null || $newAmount === null) {
            throw new RuntimeException('The selected plan is not available for manual QR Ph billing.');
        }

        $nominalDays = $interval === 'yearly' ? 365 : 30;

        $remainingSeconds = max(
            0,
            $subscription->current_period_ends_at->getTimestamp() - now()->getTimestamp(),
        );

        // Integer ceil(remainingSeconds / 86400) without floating point.
        $remainingDays = (int) min($nominalDays, intdiv($remainingSeconds + 86399, 86400));

        if ($remainingDays === 0) {
            throw new RuntimeException('This subscription must be renewed before it can be upgraded.');
        }

        $amount = self::proratedDifference($oldAmount, $newAmount, $remainingDays, $nominalDays);

        $minimum = config('billing.upgrade_minimum_manual_amount');
        $minimum = is_numeric($minimum) ? (int) $minimum : 0;

        return max($amount, $minimum);
    }

    /**
     * Compute round((newAmount - oldAmount) * remainingDays / nominalDays)
     * with half-up rounding, using only integer operations.
     */
    private static function proratedDifference(
        int $oldAmount,
        int $newAmount,
        int $remainingDays,
        int $nominalDays,
    ): int {
        $difference = $newAmount - $oldAmount;
        $sign = $difference <=> 0;
        $product = abs($difference) * $remainingDays;

        $quotient = intdiv($product, $nominalDays);
        $remainder = $product % $nominalDays;

        if ($remainder * 2 >= $nominalDays) {
            $quotient++;
        }

        return $sign * $quotient;
    }
}
