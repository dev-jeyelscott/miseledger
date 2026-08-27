<?php

namespace App\Models;

use App\Enums\BillingPaymentMethod;
use App\Enums\BillingPaymentStatus;
use App\Enums\BillingProvider;
use Carbon\CarbonInterface;
use Database\Factories\BillingPaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $billing_invoice_id
 * @property BillingProvider $provider
 * @property BillingPaymentMethod $payment_method
 * @property string $provider_request_key
 * @property string|null $external_payment_intent_id
 * @property string|null $external_payment_id
 * @property string $currency
 * @property int $amount
 * @property BillingPaymentStatus $status
 * @property bool $livemode
 * @property CarbonInterface|null $expires_at
 * @property string|null $qr_code_url
 * @property CarbonInterface|null $paid_at
 * @property CarbonInterface|null $receipt_notification_claimed_at
 * @property CarbonInterface|null $receipt_notification_dispatched_at
 * @property CarbonInterface|null $failed_at
 * @property string|null $provider_error_code
 */
#[Fillable([
    'organization_id',
    'billing_invoice_id',
    'provider',
    'payment_method',
    'provider_request_key',
    'external_payment_intent_id',
    'external_payment_id',
    'currency',
    'amount',
    'status',
    'livemode',
    'expires_at',
    'qr_code_url',
    'paid_at',
    'receipt_notification_claimed_at',
    'receipt_notification_dispatched_at',
    'failed_at',
    'provider_error_code',
])]
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
            'receipt_notification_claimed_at' => 'datetime',
            'receipt_notification_dispatched_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }
}
