<?php

namespace App\Support\Recipes;

enum RecipeComponentCostStatus: string
{
    case Costed = 'costed';
    case MissingLocationCost = 'missing_location_cost';
    case NestedRecipeNotCosted = 'nested_recipe_not_costed';
    case NestedRecipeIncomplete = 'nested_recipe_incomplete';
}
