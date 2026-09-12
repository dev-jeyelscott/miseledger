<?php

namespace App\Models;

use App\Enums\PlanCode;
use Database\Factories\BillingPlanVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'plan_code',
    'version',
    'name',
    'tier',
    'feature_codes',
    'limits',
    'published_at',
    'superseded_at',
    'created_by_user_id',
    'published_by_user_id',
])]
class BillingPlanVersion extends Model
{
    /** @use HasFactory<BillingPlanVersionFactory> */
    use HasFactory;

    /**
     * Return the stable application plan identity.
     */
    public function planCode(): PlanCode
    {
        return PlanCode::from($this->plan_code);
    }

    /**
     * Determine whether this version is still editable.
     */
    public function isDraft(): bool
    {
        return $this->published_at === null;
    }

    /**
     * Determine whether this version is current for new acquisition.
     */
    public function isCurrent(): bool
    {
        return $this->published_at !== null
            && $this->superseded_at === null;
    }

    /**
     * Determine whether this version is immutable historical commercial state.
     */
    public function isSuperseded(): bool
    {
        return $this->published_at !== null
            && $this->superseded_at !== null;
    }

    /**
     * Return a human-readable lifecycle status.
     */
    public function status(): string
    {
        return match (true) {
            $this->isCurrent() => 'published',
            $this->isSuperseded() => 'superseded',
            default => 'draft',
        };
    }

    /** @return HasMany<BillingPlanVersionPrice, $this> */
    public function prices(): HasMany
    {
        return $this->hasMany(BillingPlanVersionPrice::class);
    }

    /** @return HasMany<BillingSubscription, $this> */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(
            BillingSubscription::class,
            'plan_version_id',
        );
    }

    /** @return HasMany<BillingInvoice, $this> */
    public function sourceInvoices(): HasMany
    {
        return $this->hasMany(
            BillingInvoice::class,
            'plan_version_id',
        );
    }

    /** @return HasMany<BillingInvoice, $this> */
    public function targetInvoices(): HasMany
    {
        return $this->hasMany(
            BillingInvoice::class,
            'target_plan_version_id',
        );
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by_user_id',
        );
    }

    /** @return BelongsTo<User, $this> */
    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'published_by_user_id',
        );
    }

    /**
     * Cast immutable commercial snapshot values.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'tier' => 'integer',
            'feature_codes' => 'array',
            'limits' => 'array',
            'published_at' => 'datetime',
            'superseded_at' => 'datetime',
        ];
    }
}
