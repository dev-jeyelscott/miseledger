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
use App\Models\Organization;
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
         *     2: array{
         *         organization_id: int,
         *         subscription_id: int,
         *         previous_plan: string|null,
         *         target_plan: string,
         *         interval: string|null,
         *         external_payment_id: string
         *     }|null
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

                return [$payment, false, null];
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

            $upgrade = null;

            if ($isUpgrade) {
                $upgrade = [
                    'organization_id' => $subscription->organization_id,
                    'subscription_id' => $subscription->id,
                    'previous_plan' => $previousPlanCode,
                    'target_plan' => $targetPlanCode,
                    'interval' => $subscription->interval,
                    'external_payment_id' => $externalPaymentId,
                ];
            }

            $payment->refresh();

            return [$payment, true, $upgrade];
        }, attempts: 3);

        [$payment, $wasSettled, $upgrade] = $result;

        if ($wasSettled) {
            SendManualRenewalPaymentReceipt::dispatch($payment->id);
        }

        if ($upgrade !== null) {
            $organization = Organization::query()
                ->whereKey($upgrade['organization_id'])
                ->firstOrFail();

            $this->recordAuditEntry->handle(
                $organization,
                null,
                'billing.subscription.upgraded',
                BillingSubscription::class,
                $upgrade['subscription_id'],
                [
                    'plan' => $upgrade['previous_plan'],
                    'interval' => $upgrade['interval'],
                ],
                [
                    'plan' => $upgrade['target_plan'],
                    'interval' => $upgrade['interval'],
                    'provider' => 'paymongo',
                ],
                $upgrade['external_payment_id'],
            );
        }

        return $payment;
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
