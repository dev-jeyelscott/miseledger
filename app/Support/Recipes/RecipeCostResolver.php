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

final class RecipeCostResolver
{
    private const QUANTITY_SCALE = 6;

    private const MONEY_SCALE = 4;

    private const INTERMEDIATE_SCALE = 10;

    /**
     * Cost a published recipe version at one location, recursing through
     * fixed nested published-version references. Each nested version is
     * costed proportionally: its total cost divided by its own published
     * yield gives a cost per output unit, which prices the parent
     * component's effective quantity of that output.
     *
     * A recipe version can never nest itself directly or indirectly
     * (enforced when versions are saved), but resolution still guards
     * against revisiting a version already on the current path so that a
     * cycle can never recurse indefinitely.
     */
    public static function resolve(
        Organization $organization,
        Location $location,
        RecipeVersion $recipeVersion,
    ): RecipeCost {
        if ($location->organization_id !== $organization->getKey()) {
            throw RecipeCostResolverException::locationNotInOrganization(
                $location->id,
                $organization->id,
            );
        }

        return self::resolveVersion($organization, $location, $recipeVersion, []);
    }

    /**
     * @param  array<int, true>  $visited
     */
    private static function resolveVersion(
        Organization $organization,
        Location $location,
        RecipeVersion $recipeVersion,
        array $visited,
    ): RecipeCost {
        if ($recipeVersion->recipe->organization_id !== $organization->getKey()) {
            throw RecipeCostResolverException::recipeVersionNotInOrganization(
                $recipeVersion->id,
                $organization->id,
            );
        }

        if ($recipeVersion->status !== RecipeVersionStatus::Published) {
            throw RecipeCostResolverException::recipeVersionNotPublished(
                $recipeVersion->id,
            );
        }

        if (isset($visited[$recipeVersion->id])) {
            throw RecipeCostResolverException::cycleDetected($recipeVersion->id);
        }

        $visited[$recipeVersion->id] = true;

        $components = $recipeVersion->components()
            ->with('inventoryItem', 'componentRecipeVersion')
            ->get();

        $totalCost = BigDecimal::zero()->toScale(self::MONEY_SCALE);
        $complete = true;
        $componentCosts = [];

        foreach ($components as $component) {
            $componentCost = self::costComponent($organization, $location, $component, $visited);

            $componentCosts[] = $componentCost;

            if ($componentCost->status !== RecipeComponentCostStatus::Costed) {
                $complete = false;

                continue;
            }

            $totalCost = $totalCost->plus(BigDecimal::of($componentCost->extendedCost));
        }

        $costPerOutputUnit = $complete
            ? (string) BigDecimal::of($totalCost)
                ->dividedBy(BigDecimal::of($recipeVersion->yield_quantity), self::MONEY_SCALE, RoundingMode::HalfUp)
            : null;

        return new RecipeCost(
            recipeVersionId: $recipeVersion->id,
            totalCost: (string) $totalCost,
            complete: $complete,
            components: $componentCosts,
            costPerOutputUnit: $costPerOutputUnit,
        );
    }

    /**
     * Cost a single component, retaining intermediate precision until the
     * effective quantity and extended cost are each rounded once.
     *
     * @param  array<int, true>  $visited
     */
    private static function costComponent(
        Organization $organization,
        Location $location,
        RecipeVersionComponent $component,
        array $visited,
    ): RecipeComponentCost {
        $yieldFraction = BigDecimal::of($component->yield_percentage)
            ->dividedBy('100', self::INTERMEDIATE_SCALE, RoundingMode::HalfUp);

        $preciseEffectiveQuantity = BigDecimal::of($component->base_quantity)
            ->dividedBy($yieldFraction, self::INTERMEDIATE_SCALE, RoundingMode::HalfUp);

        $effectiveQuantity = $preciseEffectiveQuantity
            ->toScale(self::QUANTITY_SCALE, RoundingMode::HalfUp);

        if ($component->component_recipe_version_id !== null) {
            return self::costNestedRecipeComponent(
                $organization,
                $location,
                $component,
                $preciseEffectiveQuantity,
                $effectiveQuantity,
                $visited,
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

    /**
     * Cost a component whose quantity is consumed from another recipe
     * version's published output, by recursively resolving that version's
     * own cost and pricing the effective quantity at its cost per output
     * unit.
     *
     * @param  array<int, true>  $visited
     */
    private static function costNestedRecipeComponent(
        Organization $organization,
        Location $location,
        RecipeVersionComponent $component,
        BigDecimal $preciseEffectiveQuantity,
        BigDecimal $effectiveQuantity,
        array $visited,
    ): RecipeComponentCost {
        $nestedVersion = $component->componentRecipeVersion;

        if ($nestedVersion === null) {
            throw RecipeCostResolverException::recipeVersionNotPublished(
                (int) $component->component_recipe_version_id,
            );
        }

        $nestedCost = self::resolveVersion($organization, $location, $nestedVersion, $visited);

        if (! $nestedCost->complete || $nestedCost->costPerOutputUnit === null) {
            return new RecipeComponentCost(
                componentId: $component->id,
                inventoryItemId: null,
                componentRecipeVersionId: $component->component_recipe_version_id,
                effectiveQuantity: (string) $effectiveQuantity,
                unitCost: null,
                extendedCost: null,
                status: RecipeComponentCostStatus::NestedRecipeIncomplete,
                warning: 'The nested recipe version could not be fully costed.',
                nestedCost: $nestedCost,
            );
        }

        $extendedCost = $preciseEffectiveQuantity
            ->multipliedBy(BigDecimal::of($nestedCost->costPerOutputUnit))
            ->toScale(self::MONEY_SCALE, RoundingMode::HalfUp);

        return new RecipeComponentCost(
            componentId: $component->id,
            inventoryItemId: null,
            componentRecipeVersionId: $component->component_recipe_version_id,
            effectiveQuantity: (string) $effectiveQuantity,
            unitCost: $nestedCost->costPerOutputUnit,
            extendedCost: (string) $extendedCost,
            status: RecipeComponentCostStatus::Costed,
            warning: null,
            nestedCost: $nestedCost,
        );
    }
}
