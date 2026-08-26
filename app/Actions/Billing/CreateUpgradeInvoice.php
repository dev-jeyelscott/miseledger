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
 * Creates (or reuses) a `BillingInvoice` representing a requested plan
 * upgrade for a manual PayMongo subscription. Deliberately a sibling of
 * `CreateRenewalInvoice`, not an extension of it: an upgrade invoice never
 * represents a new billing period, must never mutate the subscription's
 * `plan_code` before payment, and must never advance the subscription's
 * existing paid-through boundary (`current_period_ends_at`). The invoice's
 * `plan_code` stays the subscription's current plan; `target_plan_code`
 * carries the requested plan, applied to the subscription only after
 * `SettlePayMongoPayment` verifies payment.
 *
 * The prorated amount is a function of "days remaining right now", so it is
 * only ever valid at the moment it was computed -- unlike a renewal invoice,
 * it cannot be trusted to stay correct indefinitely. Every call recomputes
 * the current amount and compares it against any still-pending invoice for
 * the same target plan: an unpaid invoice reuse is only safe when the
 * amount still matches; otherwise the stale invoice (and its unpaid
 * payment attempts) are cancelled and a fresh one is created.
 */
final class CreateUpgradeInvoice
{
    public function __construct(
        private readonly PlanCatalog $planCatalog,
        private readonly PlanUpgradePolicy $upgradePolicy,
    ) {}

    public function handle(BillingSubscription $subscription, PlanCode $targetPlan): BillingInvoice
    {
        return DB::transaction(function () use ($subscription, $targetPlan): BillingInvoice {
            $subscription = BillingSubscription::query()->lockForUpdate()->findOrFail($subscription->getKey());

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

            $amount = $this->proratedAmount($subscription, $currentPlan, $targetPlan);

            $existing = BillingInvoice::query()
                ->where('billing_subscription_id', $subscription->getKey())
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
                'billing_subscription_id' => $subscription->getKey(),
                'provider' => $subscription->provider,
                'invoice_number' => 'INV-'.Str::upper((string) Str::ulid()),
                'plan_code' => $subscription->plan_code,
                'target_plan_code' => $targetPlan->value,
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

    /**
     * Cancels a pending upgrade invoice whose amount no longer matches the
     * current proration (e.g. plan pricing changed, or enough time has
     * passed that the remaining-days calculation moved) and fails any of
     * its unpaid payment attempts, so neither is ever mistaken for still
     * being valid.
     */
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
     * Daily-rate difference proration for the remaining whole days of the
     * subscription's existing paid period: nominal 30-day (monthly) /
     * 365-day (yearly) periods, rounded to the nearest minor currency unit,
     * floored at `billing.upgrade_minimum_manual_amount` when configured.
     */
    private function proratedAmount(BillingSubscription $subscription, PlanCode $currentPlan, PlanCode $targetPlan): int
    {
        $interval = $subscription->interval;
        $currentDefinition = $this->planCatalog->get($currentPlan);
        $targetDefinition = $this->planCatalog->get($targetPlan);

        $oldAmount = $currentDefinition?->manualAmount($interval);
        $newAmount = $targetDefinition?->manualAmount($interval);

        if ($oldAmount === null || $newAmount === null) {
            throw new RuntimeException('The selected plan is not available for manual QR Ph billing.');
        }

        $nominalDays = $interval === 'yearly' ? 365 : 30;

        $remainingSeconds = max(0, $subscription->current_period_ends_at->getTimestamp() - now()->getTimestamp());
        $remainingDays = (int) min($nominalDays, ceil($remainingSeconds / 86400));

        if ($remainingDays === 0) {
            throw new RuntimeException('This subscription must be renewed before it can be upgraded.');
        }

        $oldDailyRate = $oldAmount / $nominalDays;
        $newDailyRate = $newAmount / $nominalDays;

        $amount = (int) round(($newDailyRate - $oldDailyRate) * $remainingDays);

        $minimum = config('billing.upgrade_minimum_manual_amount');
        $minimum = is_numeric($minimum) ? (int) $minimum : 0;

        return max($amount, $minimum);
    }
}
