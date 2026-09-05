<?php

namespace App\Actions\Billing;

use App\Actions\Audit\RecordAuditEntry;
use App\Enums\BillingCollectionMethod;
use App\Enums\BillingInvoiceStatus;
use App\Enums\BillingInvoiceType;
use App\Enums\BillingPaymentMethod;
use App\Enums\BillingPaymentStatus;
use App\Enums\BillingProvider;
use App\Jobs\SendManualRenewalPaymentReceipt;
use App\Models\BillingInvoice;
use App\Models\BillingPayment;
use App\Models\BillingSubscription;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class SettlePayMongoPayment
{
    public function __construct(
        private readonly RecordAuditEntry $recordAuditEntry,
    ) {}

    /** Settle one authoritative PayMongo payment exactly once. */
    public function handle(
        BillingPayment $payment,
        string $externalPaymentId,
        int $amount,
        string $currency,
        bool $livemode,
        ?Carbon $paidAt = null,
    ): BillingPayment {
        /**
         * @var array{
         *     0: BillingPayment,
         *     1: bool,
         * } $result
         */
        $result = DB::transaction(function () use (
            $payment,
            $externalPaymentId,
            $amount,
            $currency,
            $livemode,
            $paidAt,
        ): array {
            $payment = BillingPayment::query()
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            $invoice = BillingInvoice::query()
                ->whereKey($payment->billing_invoice_id)
                ->lockForUpdate()
                ->firstOrFail();

            $subscription = BillingSubscription::query()
                ->whereKey($invoice->billing_subscription_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($this->canRepairLegacyManualSubscriptionMode(
                $payment,
                $subscription,
                $livemode,
            )) {
                $subscription->update(['livemode' => $livemode]);
                $subscription->refresh();
            }

            if ($payment->provider !== BillingProvider::PayMongo
                || $invoice->provider !== BillingProvider::PayMongo
                || $subscription->provider !== BillingProvider::PayMongo
                || $payment->organization_id !== $invoice->organization_id
                || $invoice->organization_id !== $subscription->organization_id
                || $payment->amount !== $invoice->amount
                || $payment->currency !== $invoice->currency
                || $amount !== $payment->amount
                || $currency !== $payment->currency
                || $livemode !== $payment->livemode
                || $livemode !== $subscription->livemode) {
                throw new RuntimeException(
                    'PayMongo payment settlement validation failed.',
                );
            }

            if ($payment->status === BillingPaymentStatus::Paid) {
                if ($payment->external_payment_id !== $externalPaymentId) {
                    throw new RuntimeException(
                        'PayMongo payment identity conflicts with a settled attempt.',
                    );
                }

                if ($invoice->invoice_type === BillingInvoiceType::Upgrade) {
                    $this->recordUpgradeAudit(
                        $subscription,
                        $invoice->plan_code,
                        $invoice->target_plan_code,
                        $externalPaymentId,
                    );
                }

                return [$payment, false];
            }

            if (! $invoice->status->isPayable()) {
                throw new RuntimeException(
                    'PayMongo payment targets an invoice that is not payable.',
                );
            }

            $isUpgrade = $invoice->invoice_type === BillingInvoiceType::Upgrade;
            $targetPlanCode = $invoice->target_plan_code;

            if ($isUpgrade && $targetPlanCode === null) {
                throw new RuntimeException(
                    'Upgrade invoice is missing its target plan.',
                );
            }

            $previousPlanCode = $subscription->plan_code;

            $payment->update([
                'status' => BillingPaymentStatus::Paid,
                'external_payment_id' => $externalPaymentId,
                'paid_at' => $paidAt ?? now(),
            ]);

            $invoice->update([
                'status' => BillingInvoiceStatus::Paid,
                'paid_at' => $paidAt ?? now(),
            ]);

            $subscription->update([
                'provider_status' => 'active',
                'current_period_ends_at' => $invoice->period_ends_at,
                'next_billing_at' => $invoice->period_ends_at,
                'ends_at' => $invoice->period_ends_at,
                'cancelled_at' => null,
                ...($isUpgrade ? ['plan_code' => $targetPlanCode] : []),
            ]);

            if ($isUpgrade) {
                $this->recordUpgradeAudit(
                    $subscription,
                    $previousPlanCode,
                    $targetPlanCode,
                    $externalPaymentId,
                );
            }

            $payment->refresh();

            return [$payment, true];
        }, attempts: 3);

        [$payment, $wasSettled] = $result;

        if ($wasSettled) {
            SendManualRenewalPaymentReceipt::dispatch($payment->id);
        }

        return $payment;
    }

    /** Record an upgrade audit within the settlement transaction. */
    private function recordUpgradeAudit(
        BillingSubscription $subscription,
        ?string $previousPlanCode,
        ?string $targetPlanCode,
        string $externalPaymentId,
    ): void {
        if ($targetPlanCode === null) {
            throw new RuntimeException(
                'Upgrade invoice is missing its target plan.',
            );
        }

        $this->recordAuditEntry->handle(
            $subscription->organization,
            null,
            'billing.subscription.upgraded',
            BillingSubscription::class,
            $subscription->id,
            [
                'plan' => $previousPlanCode,
                'interval' => $subscription->interval,
            ],
            [
                'plan' => $targetPlanCode,
                'interval' => $subscription->interval,
                'provider' => 'paymongo',
            ],
            $externalPaymentId,
            isDeduplicationKey: true,
        );
    }

    /** Allow only the narrowly defined legacy manual-mode repair. */
    private function canRepairLegacyManualSubscriptionMode(
        BillingPayment $payment,
        BillingSubscription $subscription,
        bool $livemode,
    ): bool {
        return $livemode
            && $payment->payment_method === BillingPaymentMethod::QrPh
            && $payment->status === BillingPaymentStatus::AwaitingPayment
            && $payment->livemode === $livemode
            && $subscription->collection_method === BillingCollectionMethod::Manual
            && ! $subscription->livemode;
    }
}
