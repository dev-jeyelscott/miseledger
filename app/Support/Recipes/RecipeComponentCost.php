<?php

namespace App\Support\Recipes;

final readonly class RecipeComponentCost
{
    /**
     * Carry one component's costing result at authoritative decimal
     * precision, along with a structured reason when it could not be
     * priced.
     *
     * `nestedCost` carries the recursively resolved cost breakdown of a
     * nested recipe version component, when one was costed.
     */
    public function __construct(
        public int $componentId,
        public ?int $inventoryItemId,
        public ?int $componentRecipeVersionId,
        public string $effectiveQuantity,
        public ?string $unitCost,
        public ?string $extendedCost,
        public RecipeComponentCostStatus $status,
        public ?string $warning,
        public ?RecipeCost $nestedCost = null,
    ) {}
}
