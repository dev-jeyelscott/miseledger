<?php

namespace App\Models;

use App\Enums\BillingCollectionMethod;
use App\Enums\BillingProvider;
use Database\Factories\BillingSubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Durable, provider-neutral application projection of a subscription's
 * lifecycle identity. Normalizes only the small set of lifecycle fields
 * application code needs; provider-specific status is retained verbatim in
 * `provider_status` rather than interpreted here. This table is not the
 * entitlement authority — `OrganizationSubscriptionAccessResolver` continues
 * to read Cashier/Stripe state directly for commercial access decisions.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $billing_customer_id
 * @property BillingProvider $provider
 * @property string|null $type
 * @property string $external_subscription_id
 * @property string|null $external_plan_id
 * @property string|null $plan_code
 * @property string|null $interval
 * @property BillingCollectionMethod $collection_method
 * @property string|null $provider_status
 * @property bool $livemode
 * @property Carbon|null $trial_ends_at
 * @property Carbon|null $current_period_ends_at
 * @property Carbon|null $next_billing_at
 * @property Carbon|null $ends_at
 * @property Carbon|null $cancelled_at
 */
#[Fillable([
    'organization_id',
    'billing_customer_id',
    'provider',
    'type',
    'external_subscription_id',
    'external_plan_id',
    'plan_code',
    'interval',
    'collection_method',
    'provider_status',
    'livemode',
    'trial_ends_at',
    'current_period_ends_at',
    'next_billing_at',
    'ends_at',
    'cancelled_at',
])]
class BillingSubscription extends Model
{
    /** @use HasFactory<BillingSubscriptionFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<BillingCustomer, $this>
     */
    public function billingCustomer(): BelongsTo
    {
        return $this->belongsTo(BillingCustomer::class);
    }

    /** @return HasMany<BillingInvoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(BillingInvoice::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider' => BillingProvider::class,
            'collection_method' => BillingCollectionMethod::class,
            'livemode' => 'boolean',
            'trial_ends_at' => 'datetime',
            'current_period_ends_at' => 'datetime',
            'next_billing_at' => 'datetime',
            'ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }
}
