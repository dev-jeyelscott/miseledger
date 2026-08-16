<?php

namespace App\Support\Recipes;

use App\Enums\RecipeVersionStatus;
use App\Models\Location;
use App\Models\Organization;
use App\Models\RecipeVersion;
use App\Models\RecipeVersionComponent;
use App\Support\Inventory\LocationItemCostQuery;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final class SimpleRecipeCostResolver
{
    private const QUANTITY_SCALE = 6;

    private const MONEY_SCALE = 4;

    private const INTERMEDIATE_SCALE = 10;

    /**
     * Cost a published recipe version's direct components at one location,
     * deterministically.
     *
     * Effective quantity accounts for component yield loss: a smaller
     * yield percentage requires proportionally more of the raw component
     * to net the recipe's stored base quantity. Nested recipe components
     * and inventory items without a positive location cost are reported
     * as incomplete rather than priced with an invented cost.
     */
    public static function resolve(
        Organization $organization,
        Location $location,
        RecipeVersion $recipeVersion,
    ): RecipeCost {
        if ($recipeVersion->recipe->organization_id !== $organization->getKey()) {
            throw SimpleRecipeCostResolverException::recipeVersionNotInOrganization(
                $recipeVersion->id,
                $organization->id,
            );
        }

        if ($recipeVersion->status !== RecipeVersionStatus::Published) {
            throw SimpleRecipeCostResolverException::recipeVersionNotPublished(
                $recipeVersion->id,
            );
        }

        if ($location->organization_id !== $organization->getKey()) {
            throw SimpleRecipeCostResolverException::locationNotInOrganization(
                $location->id,
                $organization->id,
            );
        }

        $components = $recipeVersion->components()
            ->with('inventoryItem')
            ->get();

        $totalCost = BigDecimal::zero()->toScale(self::MONEY_SCALE);
        $complete = true;
        $componentCosts = [];

        foreach ($components as $component) {
            $componentCost = self::costComponent($organization, $location, $component);

            $componentCosts[] = $componentCost;

            if ($componentCost->status !== RecipeComponentCostStatus::Costed) {
                $complete = false;

                continue;
            }

            $totalCost = $totalCost->plus(BigDecimal::of($componentCost->extendedCost));
        }

        return new RecipeCost(
            recipeVersionId: $recipeVersion->id,
            totalCost: (string) $totalCost,
            complete: $complete,
            components: $componentCosts,
        );
    }

    /**
     * Cost a single component, retaining intermediate precision until the
     * effective quantity and extended cost are each rounded once.
     */
    private static function costComponent(
        Organization $organization,
        Location $location,
        RecipeVersionComponent $component,
    ): RecipeComponentCost {
        $yieldFraction = BigDecimal::of($component->yield_percentage)
            ->dividedBy('100', self::INTERMEDIATE_SCALE, RoundingMode::HalfUp);

        $preciseEffectiveQuantity = BigDecimal::of($component->base_quantity)
            ->dividedBy($yieldFraction, self::INTERMEDIATE_SCALE, RoundingMode::HalfUp);

        $effectiveQuantity = $preciseEffectiveQuantity
            ->toScale(self::QUANTITY_SCALE, RoundingMode::HalfUp);

        if ($component->component_recipe_version_id !== null) {
            return new RecipeComponentCost(
                componentId: $component->id,
                inventoryItemId: null,
                componentRecipeVersionId: $component->component_recipe_version_id,
                effectiveQuantity: (string) $effectiveQuantity,
                unitCost: null,
                extendedCost: null,
                status: RecipeComponentCostStatus::NestedRecipeNotCosted,
                warning: 'Nested recipe components are not priced by simple recipe costing.',
            );
        }

        $locationCost = LocationItemCostQuery::resolve(
            $organization,
            $location,
            $component->inventoryItem,
        );

        if (BigDecimal::of($locationCost->quantityOnHand)->isLessThanOrEqualTo(BigDecimal::zero())) {
            return new RecipeComponentCost(
                componentId: $component->id,
                inventoryItemId: $component->inventory_item_id,
                componentRecipeVersionId: null,
                effectiveQuantity: (string) $effectiveQuantity,
                unitCost: null,
                extendedCost: null,
                status: RecipeComponentCostStatus::MissingLocationCost,
                warning: 'No location item cost is available for this inventory item.',
            );
        }

        $extendedCost = $preciseEffectiveQuantity
            ->multipliedBy(BigDecimal::of($locationCost->averageUnitCost))
            ->toScale(self::MONEY_SCALE, RoundingMode::HalfUp);

        return new RecipeComponentCost(
            componentId: $component->id,
            inventoryItemId: $component->inventory_item_id,
            componentRecipeVersionId: null,
            effectiveQuantity: (string) $effectiveQuantity,
            unitCost: $locationCost->averageUnitCost,
            extendedCost: (string) $extendedCost,
            status: RecipeComponentCostStatus::Costed,
            warning: null,
        );
    }
}
