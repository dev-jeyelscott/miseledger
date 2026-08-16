<?php

namespace App\Models;

use App\Enums\RecipeVersionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $recipe_id
 * @property int $version_number
 * @property RecipeVersionStatus $status
 * @property string $yield_quantity
 * @property int $yield_unit_id
 * @property string|null $notes
 * @property int|null $created_by
 * @property int|null $published_by
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'recipe_id',
    'version_number',
    'status',
    'yield_quantity',
    'yield_unit_id',
    'notes',
    'created_by',
    'published_by',
    'published_at',
])]
class RecipeVersion extends Model
{
    /**
     * Get the stable recipe identity owning this version.
     *
     * @return BelongsTo<Recipe, $this>
     */
    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    /**
     * Get the unit the finished yield is expressed in.
     *
     * @return BelongsTo<UnitOfMeasure, $this>
     */
    public function yieldUnit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'yield_unit_id');
    }

    /**
     * Get the user who created the draft.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who published this version.
     *
     * @return BelongsTo<User, $this>
     */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    /**
     * Get the item components consumed by this version.
     *
     * @return HasMany<RecipeVersionComponent, $this>
     */
    public function components(): HasMany
    {
        return $this->hasMany(RecipeVersionComponent::class);
    }

    /**
     * Cast persisted version lifecycle fields.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RecipeVersionStatus::class,
            'yield_quantity' => 'decimal:6',
            'published_at' => 'datetime',
        ];
    }
}
