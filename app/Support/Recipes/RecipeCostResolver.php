<?php

namespace App\Support\Recipes;

use App\Enums\RecipeVersionStatus;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Organization;
use App\Models\RecipeVersion;
use App\Models\RecipeVersionComponent;
use App\Support\Inventory\LocationItemCost;
use App\Support\Inventory\LocationItemCostQuery;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Collection;

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
     *
     * The reachable graph is walked once up front to collect every
     * version's components and every leaf inventory item, so item costs
     * are fetched with a single grouped query instead of one per item, and
     * a version reached through more than one branch is costed once and
     * reused for the rest of the request.
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

        $componentsByVersionId = [];
        $inventoryItemsById = [];

        self::collectReachable(
            $organization,
            $recipeVersion,
            [],
            $componentsByVersionId,
            $inventoryItemsById,
        );

        $itemCostsById = LocationItemCostQuery::resolveMany(
            $organization,
            $location,
            $inventoryItemsById,
        );

        $versionCostMemo = [];

        return self::resolveVersion(
            $organization,
            $location,
            $recipeVersion,
            [],
            $componentsByVersionId,
            $itemCostsById,
            $versionCostMemo,
        );
    }

    /**
     * Walk the reachable recipe graph, collecting each version's loaded
     * components (keyed by version id, so a version reached through more
     * than one branch is only loaded once) and every leaf inventory item
     * (keyed by item id, deduplicated). Applies the same organization,
     * published-status, and cycle checks as the actual costing pass, so an
     * invalid graph is rejected before any cost query runs.
     *
     * @param  array<int, true>  $visited
     * @param  array<int, Collection<int, RecipeVersionComponent>>  $componentsByVersionId
     * @param  array<int, InventoryItem>  $inventoryItemsById
     */
    private static function collectReachable(
        Organization $organization,
        RecipeVersion $recipeVersion,
        array $visited,
        array &$componentsByVersionId,
        array &$inventoryItemsById,
    ): void {
        self::assertVersionAccessible($organization, $recipeVersion);

        if (isset($visited[$recipeVersion->id])) {
            throw RecipeCostResolverException::cycleDetected($recipeVersion->id);
        }

        if (isset($componentsByVersionId[$recipeVersion->id])) {
            return;
        }

        $visited[$recipeVersion->id] = true;

        $components = $recipeVersion->components()
            ->with('inventoryItem', 'componentRecipeVersion.recipe')
            ->get();

        $componentsByVersionId[$recipeVersion->id] = $components;

        foreach ($components as $component) {
            if ($component->inventory_item_id !== null && $component->inventoryItem !== null) {
                $inventoryItemsById[$component->inventory_item_id] = $component->inventoryItem;
            }

            if ($component->component_recipe_version_id !== null && $component->componentRecipeVersion !== null) {
                self::collectReachable(
                    $organization,
                    $component->componentRecipeVersion,
                    $visited,
                    $componentsByVersionId,
                    $inventoryItemsById,
                );
            }
        }
    }

    private static function assertVersionAccessible(
        Organization $organization,
        RecipeVersion $recipeVersion,
    ): void {
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
    }

    /**
     * @param  array<int, true>  $visited
     * @param  array<int, Collection<int, RecipeVersionComponent>>  $componentsByVersionId
     * @param  array<int, LocationItemCost>  $itemCostsById
     * @param  array<int, RecipeCost>  $versionCostMemo
     */
    private static function resolveVersion(
        Organization $organization,
        Location $location,
        RecipeVersion $recipeVersion,
        array $visited,
        array $componentsByVersionId,
        array $itemCostsById,
        array &$versionCostMemo,
    ): RecipeCost {
        self::assertVersionAccessible($organization, $recipeVersion);

        if (isset($visited[$recipeVersion->id])) {
            throw RecipeCostResolverException::cycleDetected($recipeVersion->id);
        }

        if (isset($versionCostMemo[$recipeVersion->id])) {
            return $versionCostMemo[$recipeVersion->id];
        }

        $visited[$recipeVersion->id] = true;

        $components = $componentsByVersionId[$recipeVersion->id];

        $totalCost = BigDecimal::zero()->toScale(self::MONEY_SCALE);
        $complete = true;
        $componentCosts = [];

        foreach ($components as $component) {
            $componentCost = self::costComponent(
                $organization,
                $location,
                $component,
                $visited,
                $componentsByVersionId,
                $itemCostsById,
                $versionCostMemo,
            );

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

        $result = new RecipeCost(
            recipeVersionId: $recipeVersion->id,
            totalCost: (string) $totalCost,
            complete: $complete,
            components: $componentCosts,
            costPerOutputUnit: $costPerOutputUnit,
        );

        $versionCostMemo[$recipeVersion->id] = $result;

        return $result;
    }

    /**
     * Cost a single component, retaining intermediate precision until the
     * effective quantity and extended cost are each rounded once.
     *
     * @param  array<int, true>  $visited
     * @param  array<int, Collection<int, RecipeVersionComponent>>  $componentsByVersionId
     * @param  array<int, LocationItemCost>  $itemCostsById
     * @param  array<int, RecipeCost>  $versionCostMemo
     */
    private static function costComponent(
        Organization $organization,
        Location $location,
        RecipeVersionComponent $component,
        array $visited,
        array $componentsByVersionId,
        array $itemCostsById,
        array &$versionCostMemo,
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
                $componentsByVersionId,
                $itemCostsById,
                $versionCostMemo,
            );
        }

        $locationCost = $itemCostsById[$component->inventoryItem->id];

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
     * @param  array<int, Collection<int, RecipeVersionComponent>>  $componentsByVersionId
     * @param  array<int, LocationItemCost>  $itemCostsById
     * @param  array<int, RecipeCost>  $versionCostMemo
     */
    private static function costNestedRecipeComponent(
        Organization $organization,
        Location $location,
        RecipeVersionComponent $component,
        BigDecimal $preciseEffectiveQuantity,
        BigDecimal $effectiveQuantity,
        array $visited,
        array $componentsByVersionId,
        array $itemCostsById,
        array &$versionCostMemo,
    ): RecipeComponentCost {
        $nestedVersion = $component->componentRecipeVersion;

        if ($nestedVersion === null) {
            throw RecipeCostResolverException::recipeVersionNotPublished(
                (int) $component->component_recipe_version_id,
            );
        }

        $nestedCost = self::resolveVersion(
            $organization,
            $location,
            $nestedVersion,
            $visited,
            $componentsByVersionId,
            $itemCostsById,
            $versionCostMemo,
        );

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
