<?php

namespace App\Support\Recipes;

use App\Models\Recipe;
use App\Models\RecipeVersion;
use Illuminate\Support\Carbon;

final class EffectiveRecipeVersionResolver
{
    /**
     * Resolve the single published recipe version effective for a recipe at
     * the given timestamp, deterministically.
     *
     * @throws EffectiveRecipeVersionResolutionException when zero or more
     *                                                   than one published version is effective for the timestamp
     */
    public static function resolve(Recipe $recipe, Carbon $asOf): RecipeVersion
    {
        $date = $asOf->clone()->startOfDay();

        $versions = RecipeVersion::query()
            ->where('recipe_id', $recipe->id)
            ->effectiveOn($date)
            ->orderBy('effective_start_date')
            ->orderBy('id')
            ->get();

        if ($versions->isEmpty()) {
            throw EffectiveRecipeVersionResolutionException::noneEffective(
                $recipe->id,
                $date->toDateString(),
            );
        }

        if ($versions->count() > 1) {
            throw EffectiveRecipeVersionResolutionException::multipleEffective(
                $recipe->id,
                $date->toDateString(),
            );
        }

        return $versions->sole();
    }
}
