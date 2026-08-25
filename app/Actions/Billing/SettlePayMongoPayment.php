<?php

namespace App\Actions\Billing;

use App\Enums\BillingInvoiceStatus;
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
    public function handle(BillingPayment $payment, string $externalPaymentId, int $amount, string $currency, bool $livemode, ?Carbon $paidAt = null): BillingPayment
    {
        [$payment, $wasSettled] = DB::transaction(function () use ($payment, $externalPaymentId, $amount, $currency, $livemode, $paidAt): array {
            $payment = BillingPayment::query()->lockForUpdate()->whereKey($payment->getKey())->first();

            if ($payment === null) {
                throw new RuntimeException('PayMongo payment was not found.');
            }
            $invoice = BillingInvoice::query()->lockForUpdate()->findOrFail($payment->billing_invoice_id);
            $subscription = BillingSubscription::query()->lockForUpdate()->findOrFail($invoice->billing_subscription_id);

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
                throw new RuntimeException('PayMongo payment settlement validation failed.');
            }

            if ($payment->status === BillingPaymentStatus::Paid) {
                if ($payment->external_payment_id !== $externalPaymentId) {
                    throw new RuntimeException('PayMongo payment identity conflicts with a settled attempt.');
                }

                return [$payment, false];
            }

            if (! $invoice->status->isPayable()) {
                throw new RuntimeException('PayMongo payment targets an invoice that is not payable.');
            }

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
            ]);

            return [$payment->fresh(), true];
        }, attempts: 3);

        if ($wasSettled) {
            SendManualRenewalPaymentReceipt::dispatch($payment->getKey());
        }

        return $payment;
    }
}
