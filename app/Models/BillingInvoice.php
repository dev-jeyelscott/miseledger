<?php

namespace App\Models;

use App\Enums\BillingInvoiceStatus;
use App\Enums\BillingInvoiceType;
use App\Enums\BillingProvider;
use Carbon\CarbonInterface;
use Database\Factories\BillingInvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Provider-neutral commercial invoice for one renewal or upgrade obligation.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $billing_subscription_id
 * @property BillingProvider $provider
 * @property string $invoice_number
 * @property string $plan_code
 * @property BillingInvoiceType $invoice_type
 * @property string|null $target_plan_code
 * @property string $billing_interval
 * @property string $currency
 * @property int $amount
 * @property BillingInvoiceStatus $status
 * @property CarbonInterface $period_starts_at
 * @property CarbonInterface $period_ends_at
 * @property CarbonInterface|null $due_at
 * @property CarbonInterface|null $paid_at
 * @property CarbonInterface|null $cancelled_at
 */
#[Fillable([
    'organization_id',
    'billing_subscription_id',
    'provider',
    'invoice_number',
    'plan_code',
    'invoice_type',
    'target_plan_code',
    'billing_interval',
    'currency',
    'amount',
    'status',
    'period_starts_at',
    'period_ends_at',
    'due_at',
    'paid_at',
    'cancelled_at',
])]
class BillingInvoice extends Model
{
    /** @use HasFactory<BillingInvoiceFactory> */
    use HasFactory;

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<BillingSubscription, $this> */
    public function billingSubscription(): BelongsTo
    {
        return $this->belongsTo(BillingSubscription::class);
    }

    /** @return HasMany<BillingPayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(BillingPayment::class);
    }

    /** @return HasMany<BillingRenewalReminder, $this> */
    public function renewalReminders(): HasMany
    {
        return $this->hasMany(BillingRenewalReminder::class);
    }

    protected function casts(): array
    {
        return [
            'provider' => BillingProvider::class,
            'status' => BillingInvoiceStatus::class,
            'invoice_type' => BillingInvoiceType::class,
            'period_starts_at' => 'datetime',
            'period_ends_at' => 'datetime',
            'due_at' => 'datetime',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }
}
