<?php

namespace App\Http\Controllers\Recipes;

use App\Enums\OrganizationPermission;
use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Organization;
use App\Models\RecipeVersionComponent;
use App\Support\Recipes\EffectiveRecipeVersionResolutionException;
use App\Support\Recipes\EffectiveRecipeVersionResolver;
use App\Support\Recipes\RecipeComponentCost;
use App\Support\Recipes\RecipeCost;
use App\Support\Recipes\RecipeCostResolver;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RecipeCostController extends Controller
{
    /**
     * Show the current cost breakdown of a recipe's effective published
     * version at one organization location. No historical costing is
     * offered; the breakdown always reflects current location item costs.
     */
    public function show(Request $request, string $recipe): Response
    {
        $organization = $this->activeOrganization($request);

        Gate::authorize(
            OrganizationPermission::RecipesView->value,
            $organization,
        );

        Gate::authorize(
            OrganizationPermission::CostsView->value,
            $organization,
        );

        $recipeModel = $organization
            ->recipes()
            ->findOrFail($recipe);

        $validated = $request->validate([
            'location_id' => [
                'nullable',
                'integer',
                Rule::exists('locations', 'id')->where(
                    fn (Builder $query): Builder => $query->where(
                        'organization_id',
                        $organization->id,
                    ),
                ),
            ],
        ]);

        $locationId = isset($validated['location_id'])
            ? (int) $validated['location_id']
            : null;

        $locationOptions = Location::query()
            ->where('organization_id', $organization->id)
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(static fn (Location $location): array => [
                'id' => $location->id,
                'name' => $location->name,
            ])
            ->values()
            ->all();

        $asOf = Carbon::now();

        $recipeVersion = null;
        $error = null;

        try {
            $recipeVersion = EffectiveRecipeVersionResolver::resolve(
                $recipeModel,
                $asOf,
            );
        } catch (EffectiveRecipeVersionResolutionException) {
            $error = __(
                'No published recipe version is currently effective for this recipe.',
            );
        }

        $cost = null;

        if ($recipeVersion !== null && $locationId !== null) {
            $location = Location::query()
                ->where('organization_id', $organization->id)
                ->findOrFail($locationId);

            $cost = $this->presentCost(
                RecipeCostResolver::resolve(
                    $organization,
                    $location,
                    $recipeVersion,
                ),
            );
        }

        return Inertia::render('recipes/cost', [
            'recipe' => [
                'id' => $recipeModel->id,
                'code' => $recipeModel->code,
                'name' => $recipeModel->name,
            ],
            'recipeVersion' => $recipeVersion === null ? null : [
                'id' => $recipeVersion->id,
                'versionNumber' => $recipeVersion->version_number,
                'yieldQuantity' => $recipeVersion->yield_quantity,
                'yieldUnitSymbol' => $recipeVersion->yieldUnit->symbol,
            ],
            'asOf' => $asOf->toIso8601String(),
            'timezone' => $organization->timezone,
            'currency' => $organization->currency,
            'locationOptions' => $locationOptions,
            'filters' => [
                'locationId' => $locationId,
            ],
            'cost' => $cost,
            'error' => $error,
        ]);
    }

    /**
     * Flatten a recursive recipe cost into a JSON-safe structure, naming
     * each component from a single batched lookup of the underlying
     * component rows so the nested tree avoids N+1 queries.
     *
     * @return array<string, mixed>
     */
    private function presentCost(RecipeCost $cost): array
    {
        $componentIds = [];
        $this->collectComponentIds($cost, $componentIds);

        $components = RecipeVersionComponent::query()
            ->whereIn('id', $componentIds)
            ->with([
                'inventoryItem.baseUnitOfMeasure',
                'componentRecipeVersion.recipe',
                'componentRecipeVersion.yieldUnit',
            ])
            ->get()
            ->keyBy('id');

        return $this->presentCostNode($cost, $components);
    }

    /**
     * @param  array<int, int>  $componentIds
     */
    private function collectComponentIds(RecipeCost $cost, array &$componentIds): void
    {
        foreach ($cost->components as $component) {
            $componentIds[] = $component->componentId;

            if ($component->nestedCost !== null) {
                $this->collectComponentIds($component->nestedCost, $componentIds);
            }
        }
    }

    /**
     * @param  Collection<int, RecipeVersionComponent>  $components
     * @return array<string, mixed>
     */
    private function presentCostNode(RecipeCost $cost, Collection $components): array
    {
        return [
            'recipeVersionId' => $cost->recipeVersionId,
            'totalCost' => $cost->totalCost,
            'complete' => $cost->complete,
            'costPerOutputUnit' => $cost->costPerOutputUnit,
            'components' => array_map(
                fn (RecipeComponentCost $component): array => $this->presentComponent(
                    $component,
                    $components,
                ),
                $cost->components,
            ),
        ];
    }

    /**
     * @param  Collection<int, RecipeVersionComponent>  $components
     * @return array<string, mixed>
     */
    private function presentComponent(
        RecipeComponentCost $component,
        Collection $components,
    ): array {
        $model = $components->get($component->componentId);

        return [
            'componentId' => $component->componentId,
            'kind' => $component->inventoryItemId !== null
                ? 'inventory_item'
                : 'nested_recipe',
            'name' => $model?->inventoryItem->name
                ?? $model?->componentRecipeVersion->recipe->name
                ?? __('Unknown component'),
            'sku' => $model?->inventoryItem->sku,
            'effectiveQuantity' => $component->effectiveQuantity,
            'unitSymbol' => $model?->inventoryItem->baseUnitOfMeasure->symbol
                ?? $model?->componentRecipeVersion->yieldUnit->symbol
                ?? '',
            'unitCost' => $component->unitCost,
            'extendedCost' => $component->extendedCost,
            'status' => $component->status->value,
            'warning' => $component->warning,
            'nestedCost' => $component->nestedCost === null
                ? null
                : $this->presentCostNode($component->nestedCost, $components),
        ];
    }

    private function activeOrganization(Request $request): Organization
    {
        $organization = $request->attributes->get(
            'activeOrganization',
        );

        if (! $organization instanceof Organization) {
            abort(403);
        }

        return $organization;
    }
}
