<?php

namespace Database\Factories;

use App\Enums\BillingPaymentMethod;
use App\Enums\BillingPaymentStatus;
use App\Models\BillingInvoice;
use App\Models\BillingPayment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BillingPayment>
 */
class BillingPaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'billing_invoice_id' => BillingInvoice::factory(),
            'organization_id' => fn (array $attributes): int => BillingInvoice::query()
                ->whereKey($attributes['billing_invoice_id'])
                ->value('organization_id'),
            'provider' => fn (array $attributes): string => BillingInvoice::query()
                ->whereKey($attributes['billing_invoice_id'])
                ->value('provider'),
            'payment_method' => BillingPaymentMethod::QrPh,
            'provider_request_key' => 'miseledger:paymongo:qrph:'.Str::lower(Str::random(20)),
            'external_payment_intent_id' => 'pi_'.Str::lower(Str::random(14)),
            'external_payment_id' => null,
            'currency' => 'PHP',
            'amount' => 49_900,
            'status' => BillingPaymentStatus::AwaitingPayment,
            'livemode' => false,
            'expires_at' => now()->addMinutes(30),
            'qr_code_url' => 'https://paymongo.test/qr/example.png',
            'paid_at' => null,
            'failed_at' => null,
            'provider_error_code' => null,
        ];
    }
}
