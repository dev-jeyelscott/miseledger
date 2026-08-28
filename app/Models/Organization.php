<?php

namespace App\Models;

use App\Enums\OrganizationRolloutClassification;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Laravel\Cashier\Billable;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $timezone
 * @property string $currency
 * @property bool $active
 * @property Carbon|null $trial_ends_at
 * @property OrganizationRolloutClassification|null $rollout_classification
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * `active` is exclusively an administrative enable/disable flag, toggled by
 * an organization manager or platform operator. Subscription/billing state
 * must never read or write this column; commercial access must be derived
 * separately so organizations remain resolvable to members for historical
 * reads and billing recovery even when commercially read-only.
 *
 * `rollout_classification` is an explicit, operator-assigned pre-enforcement
 * classification (see `docs/existing-organization-rollout-plan.md`). It is
 * never inferred from timestamps or backfilled automatically.
 */
#[Fillable(['name', 'slug', 'timezone', 'currency', 'active', 'trial_ends_at', 'rollout_classification'])]
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use Billable, HasFactory;

    /**
     * Get locations belonging to this organization.
     *
     * @return HasMany<Location, $this>
     */
    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    /**
     * Get physical storage locations belonging to this organization.
     *
     * @return HasMany<StorageLocation, $this>
     */
    public function storageLocations(): HasMany
    {
        return $this->hasMany(StorageLocation::class);
    }

    /**
     * Get the organization's explicit user memberships.
     *
     * @return HasMany<OrganizationMembership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class);
    }

    /**
     * Get users belonging to this organization.
     *
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'organization_memberships',
        )
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Get organization-scoped units of measure.
     *
     * @return HasMany<UnitOfMeasure, $this>
     */
    public function unitsOfMeasure(): HasMany
    {
        return $this->hasMany(UnitOfMeasure::class);
    }

    /**
     * Get organization-scoped inventory items.
     *
     * @return HasMany<InventoryItem, $this>
     */
    public function inventoryItems(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }

    /**
     * Get organization-scoped inventory categories.
     *
     * @return HasMany<InventoryCategory, $this>
     */
    public function inventoryCategories(): HasMany
    {
        return $this->hasMany(InventoryCategory::class);
    }

    /**
     * Get organization-scoped inventory brands.
     *
     * @return HasMany<InventoryBrand, $this>
     */
    public function inventoryBrands(): HasMany
    {
        return $this->hasMany(InventoryBrand::class);
    }

    /**
     * Get organization-scoped suppliers.
     *
     * @return HasMany<Supplier, $this>
     */
    public function suppliers(): HasMany
    {
        return $this->hasMany(Supplier::class);
    }

    /**
     * Get organization-scoped recipe masters.
     *
     * @return HasMany<Recipe, $this>
     */
    public function recipes(): HasMany
    {
        return $this->hasMany(Recipe::class);
    }

    /**
     * Get the organization's durable, provider-neutral billing-customer
     * identities (one per provider it has been onboarded with).
     *
     * @return HasMany<BillingCustomer, $this>
     */
    public function billingCustomers(): HasMany
    {
        return $this->hasMany(BillingCustomer::class);
    }

    /**
     * Get the organization's durable, provider-neutral subscription
     * projections.
     *
     * @return HasMany<BillingSubscription, $this>
     */
    public function billingSubscriptions(): HasMany
    {
        return $this->hasMany(BillingSubscription::class);
    }

    /** @return HasMany<BillingInvoice, $this> */
    public function billingInvoices(): HasMany
    {
        return $this->hasMany(BillingInvoice::class);
    }

    /** @return HasMany<BillingPayment, $this> */
    public function billingPayments(): HasMany
    {
        return $this->hasMany(BillingPayment::class);
    }

    /**
     * Cast organization state to stable application types.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'trial_ends_at' => 'datetime',
            'rollout_classification' => OrganizationRolloutClassification::class,
        ];
    }
}
