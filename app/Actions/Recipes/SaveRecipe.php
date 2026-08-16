<?php

namespace App\Actions\Recipes;

use App\Enums\RecipeType;
use App\Models\Organization;
use App\Models\Recipe;
use Illuminate\Support\Facades\DB;

final class SaveRecipe
{
    /**
     * @param  array{code: string, name: string, type: RecipeType, active: bool}  $attributes
     */
    public function handle(
        Organization $organization,
        array $attributes,
        ?Recipe $recipe = null,
    ): Recipe {
        return DB::transaction(function () use (
            $organization,
            $attributes,
            $recipe,
        ): Recipe {
            if ($recipe === null) {
                return $organization->recipes()->create($attributes);
            }

            $lockedRecipe = Recipe::query()
                ->where('organization_id', $organization->getKey())
                ->whereKey($recipe->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $lockedRecipe->update($attributes);

            return $lockedRecipe;
        });
    }
}
