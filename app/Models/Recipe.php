<?php

namespace App\Models;

use App\Enums\RecipeType;
use Database\Factories\RecipeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property string $code
 * @property string $name
 * @property RecipeType $type
 * @property bool $active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['organization_id', 'code', 'name', 'type', 'active'])]
class Recipe extends Model
{
    /** @use HasFactory<RecipeFactory> */
    use HasFactory;

    /**
     * Get the organization owning this recipe.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the sequential draft and published versions of this recipe.
     *
     * @return HasMany<RecipeVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(RecipeVersion::class);
    }

    /**
     * Cast persisted recipe state.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => RecipeType::class,
            'active' => 'boolean',
        ];
    }
}
