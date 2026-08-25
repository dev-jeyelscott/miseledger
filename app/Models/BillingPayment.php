<?php

namespace App\Models;

use App\Enums\BillingPaymentMethod;
use App\Enums\BillingPaymentStatus;
use App\Enums\BillingProvider;
use Database\Factories\BillingPaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['organization_id', 'billing_invoice_id', 'provider', 'payment_method', 'provider_request_key', 'external_payment_intent_id', 'external_payment_id', 'currency', 'amount', 'status', 'livemode', 'expires_at', 'qr_code_url', 'paid_at', 'failed_at', 'provider_error_code'])]
class BillingPayment extends Model
{
    /** @use HasFactory<BillingPaymentFactory> */
    use HasFactory;

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<BillingInvoice, $this> */
    public function billingInvoice(): BelongsTo
    {
        return $this->belongsTo(BillingInvoice::class);
    }

    protected function casts(): array
    {
        return [
            'provider' => BillingProvider::class,
            'payment_method' => BillingPaymentMethod::class,
            'status' => BillingPaymentStatus::class,
            'livemode' => 'boolean',
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }
}
