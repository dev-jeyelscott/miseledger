<?php

namespace App\Support\Recipes;

use App\Models\RecipeVersion;
use App\Models\RecipeVersionComponent;

final class RecipeVersionGraph
{
    /**
     * Walk the nested component graph, collecting every recipe reachable
     * from the given published recipe version.
     *
     * @param  array<int, true>  $visited
     * @return list<int>
     */
    public static function reachableRecipeIds(
        RecipeVersion $version,
        array &$visited = [],
    ): array {
        if (isset($visited[$version->id])) {
            return [];
        }

        $visited[$version->id] = true;

        $recipeIds = [$version->recipe_id];

        $nestedComponents = RecipeVersionComponent::query()
            ->where('recipe_version_id', $version->id)
            ->whereNotNull('component_recipe_version_id')
            ->with('componentRecipeVersion')
            ->get();

        foreach ($nestedComponents as $nestedComponent) {
            $child = $nestedComponent->componentRecipeVersion;

            if ($child !== null) {
                $recipeIds = [
                    ...$recipeIds,
                    ...self::reachableRecipeIds($child, $visited),
                ];
            }
        }

        return $recipeIds;
    }
}
