<?php

namespace App\Models;

use App\Enums\BillingCollectionMethod;
use App\Enums\BillingProvider;
use Database\Factories\BillingPlanVersionPriceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'billing_plan_version_id',
    'provider',
    'collection_method',
    'interval',
    'currency',
    'amount_minor',
])]
class BillingPlanVersionPrice extends Model
{
    /** @use HasFactory<BillingPlanVersionPriceFactory> */
    use HasFactory;

    /** @return BelongsTo<BillingPlanVersion, $this> */
    public function planVersion(): BelongsTo
    {
        return $this->belongsTo(
            BillingPlanVersion::class,
            'billing_plan_version_id',
        );
    }

    /**
     * Cast provider and monetary identity without floating-point conversion.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider' => BillingProvider::class,
            'collection_method' => BillingCollectionMethod::class,
            'amount_minor' => 'integer',
        ];
    }
}
