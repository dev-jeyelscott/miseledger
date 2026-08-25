<?php

namespace App\Models;

use App\Enums\BillingProvider;
use Database\Factories\BillingCustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Durable, provider-neutral projection of an organization's billing-customer
 * identity on a given provider. Additive alongside Cashier's own Stripe
 * customer columns on `Organization` (`stripe_id`, `pm_type`, `pm_last_four`)
 * — this table never replaces or is written to by Cashier itself.
 *
 * @property int $id
 * @property int $organization_id
 * @property BillingProvider $provider
 * @property string $external_customer_id
 * @property bool $livemode
 */
#[Fillable(['organization_id', 'provider', 'external_customer_id', 'livemode'])]
class BillingCustomer extends Model
{
    /** @use HasFactory<BillingCustomerFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return HasMany<BillingSubscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(BillingSubscription::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider' => BillingProvider::class,
            'livemode' => 'boolean',
        ];
    }
}
