<?php

namespace App\Support\Recipes;

final readonly class RecipeCost
{
    /**
     * Carry the aggregated recipe costing result. The total sums only the
     * components that were successfully priced; `complete` reports whether
     * every component contributed.
     *
     * `costPerOutputUnit` is the total cost divided by the recipe version's
     * published yield quantity, and is null whenever the total is
     * incomplete.
     *
     * @param  list<RecipeComponentCost>  $components
     */
    public function __construct(
        public int $recipeVersionId,
        public string $totalCost,
        public bool $complete,
        public array $components,
        public ?string $costPerOutputUnit = null,
    ) {}
}
