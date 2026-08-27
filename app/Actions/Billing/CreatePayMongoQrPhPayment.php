<?php

namespace App\Actions\Billing;

use App\Enums\BillingCollectionMethod;
use App\Enums\BillingInvoiceStatus;
use App\Enums\BillingPaymentMethod;
use App\Enums\BillingPaymentStatus;
use App\Enums\BillingProvider;
use App\Models\BillingInvoice;
use App\Models\BillingPayment;
use App\Support\Billing\ManualRenewalCheckout;
use App\Support\Billing\Providers\PayMongoClient;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class CreatePayMongoQrPhPayment
{
    public function __construct(private readonly PayMongoClient $client) {}

    /** Create or reuse a safe QR Ph payment attempt for one payable invoice. */
    public function handle(BillingInvoice $invoice): ManualRenewalCheckout
    {
        $invoiceId = $invoice->id;

        $payment = DB::transaction(function () use ($invoiceId): BillingPayment|ManualRenewalCheckout {
            $invoice = BillingInvoice::query()
                ->with('billingSubscription')
                ->whereKey($invoiceId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($invoice->billingSubscription->livemode !== (config('billing.providers.paymongo.mode') === 'live')) {
                throw new RuntimeException('The QR Ph billing profile belongs to a different PayMongo environment.');
            }

            if (! $invoice->status->isPayable()
                || $invoice->provider !== BillingProvider::PayMongo
                || $invoice->billingSubscription->collection_method !== BillingCollectionMethod::Manual) {
                throw new RuntimeException('This invoice cannot be paid with QR Ph.');
            }

            $payment = $invoice->payments()
                ->lockForUpdate()
                ->where('status', BillingPaymentStatus::AwaitingPayment)
                ->latest()
                ->first();

            if ($payment !== null && $payment->expires_at?->isFuture() === true && $payment->qr_code_url !== null) {
                return new ManualRenewalCheckout($invoice, $payment);
            }

            if ($payment !== null) {
                $payment->update(['status' => BillingPaymentStatus::Expired]);
            }

            $pendingPayment = $invoice->payments()
                ->lockForUpdate()
                ->where('status', BillingPaymentStatus::Pending)
                ->latest()
                ->first();

            if ($pendingPayment !== null) {
                return $pendingPayment;
            }

            return BillingPayment::query()->create([
                'organization_id' => $invoice->organization_id,
                'billing_invoice_id' => $invoice->id,
                'provider' => BillingProvider::PayMongo,
                'payment_method' => BillingPaymentMethod::QrPh,
                'provider_request_key' => 'miseledger:paymongo:qrph:intent:'.Str::lower((string) Str::ulid()),
                'currency' => $invoice->currency,
                'amount' => $invoice->amount,
                'status' => BillingPaymentStatus::Pending,
                'livemode' => $invoice->billingSubscription->livemode,
            ]);
        }, attempts: 3);

        if ($payment instanceof ManualRenewalCheckout) {
            return $payment;
        }

        $invoice = BillingInvoice::query()
            ->whereKey($invoiceId)
            ->firstOrFail();

        $paymentIntent = $this->client->createPaymentIntent(
            $invoice->amount,
            $invoice->currency,
            [
                'organization_id' => (string) $invoice->organization_id,
                'billing_invoice_id' => (string) $invoice->id,
            ],
            (string) $invoice->organization_id,
            $payment->provider_request_key,
        );

        $paymentIntentAttributes = $this->resourceAttributes($paymentIntent, 'payment_intent');
        $paymentIntentId = $paymentIntent['data']['id'] ?? null;

        if (! is_string($paymentIntentId) || ! str_starts_with($paymentIntentId, 'pi_')
            || ($paymentIntentAttributes['amount'] ?? null) !== $invoice->amount
            || ($paymentIntentAttributes['currency'] ?? null) !== $invoice->currency
            || ! is_bool($paymentIntentAttributes['livemode'] ?? null)) {
            throw new RuntimeException('PayMongo returned an invalid payment intent response.');
        }

        try {
            $payment->update([
                'external_payment_intent_id' => $paymentIntentId,
                'livemode' => $paymentIntentAttributes['livemode'],
            ]);
        } catch (QueryException) {
            $payment = BillingPayment::query()
                ->where('provider', BillingProvider::PayMongo)
                ->where('external_payment_intent_id', $paymentIntentId)
                ->firstOrFail();

            if ($payment->billing_invoice_id !== $invoice->id) {
                throw new RuntimeException('PayMongo payment intent ownership conflicts with this invoice.');
            }
        }

        $paymentMethod = $this->client->createQrPhPaymentMethod(
            (string) $invoice->organization_id,
            "{$payment->provider_request_key}:method",
        );
        $paymentMethodId = $paymentMethod['data']['id'] ?? null;

        if (! is_string($paymentMethodId) || ! str_starts_with($paymentMethodId, 'pm_')) {
            throw new RuntimeException('PayMongo returned an invalid QR Ph payment method response.');
        }

        $attachedPaymentIntent = $this->client->attachPaymentMethod(
            $paymentIntentId,
            $paymentMethodId,
            (string) $invoice->organization_id,
            "{$payment->provider_request_key}:attach",
        );

        $attributes = $this->resourceAttributes($attachedPaymentIntent, 'payment_intent');
        $qrCodeUrl = data_get($attributes, 'next_action.code.image_url');

        if (($attachedPaymentIntent['data']['id'] ?? null) !== $paymentIntentId
            || ! $this->isSafeQrCodeUrl($qrCodeUrl)) {
            throw new RuntimeException('PayMongo did not return a QR Ph code.');
        }

        $expiresAt = $this->expiresAt(
            data_get($attributes, 'next_action.code.expires_at'),
        );
        $paymentId = $payment->id;

        return DB::transaction(function () use ($invoiceId, $paymentId, $qrCodeUrl, $expiresAt): ManualRenewalCheckout {
            $invoice = BillingInvoice::query()
                ->whereKey($invoiceId)
                ->lockForUpdate()
                ->firstOrFail();

            $payment = BillingPayment::query()
                ->whereKey($paymentId)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $invoice->status->isPayable()) {
                throw new RuntimeException('This invoice is no longer payable.');
            }

            $payment->update([
                'status' => BillingPaymentStatus::AwaitingPayment,
                'qr_code_url' => $qrCodeUrl,
                'expires_at' => $expiresAt,
            ]);

            $invoice->update([
                'status' => BillingInvoiceStatus::PaymentPending,
            ]);

            $invoice->refresh();
            $payment->refresh();

            return new ManualRenewalCheckout($invoice, $payment);
        }, attempts: 3);
    }

    /**
     * Validate and extract attributes from one PayMongo JSON:API resource.
     *
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    private function resourceAttributes(array $response, string $type): array
    {
        $resource = $response['data'] ?? null;
        $attributes = is_array($resource) ? $resource['attributes'] ?? null : null;

        if (! is_array($resource)
            || ($resource['type'] ?? null) !== $type
            || ! is_array($attributes)) {
            throw new RuntimeException("PayMongo returned an invalid {$type} response.");
        }

        return $attributes;
    }

    /** Convert PayMongo expiry evidence to a bounded local expiration timestamp. */
    private function expiresAt(mixed $value): CarbonInterface
    {
        return (is_int($value) || (is_string($value) && ctype_digit($value)))
            ? Carbon::createFromTimestampUTC((int) $value)
            : now()->addMinutes(30);
    }

    /** Accept only normal URLs or bounded image data URLs as QR display artifacts. */
    private function isSafeQrCodeUrl(mixed $value): bool
    {
        if (! is_string($value) || $value === '' || mb_strlen($value) > 500_000) {
            return false;
        }

        return filter_var($value, FILTER_VALIDATE_URL) !== false
            || preg_match('/^data:image\/(?:png|svg\+xml);base64,[A-Za-z0-9+\/]+=*$/D', $value) === 1;
    }
}
