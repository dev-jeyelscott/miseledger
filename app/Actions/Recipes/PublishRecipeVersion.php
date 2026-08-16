<?php

namespace App\Actions\Recipes;

use App\Enums\OrganizationPermission;
use App\Enums\RecipeVersionStatus;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\RecipeVersion;
use App\Models\User;
use App\Support\Recipes\RecipeVersionGraph;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PublishRecipeVersion
{
    /**
     * Publish a validated draft recipe version as an immutable, effective
     * revision of its recipe.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function handle(
        Organization $organization,
        User $actor,
        RecipeVersion $recipeVersion,
        array $attributes,
    ): RecipeVersion {
        return DB::transaction(function () use (
            $organization,
            $actor,
            $recipeVersion,
            $attributes,
        ): RecipeVersion {
            $this->authorize($organization, $actor);

            $version = RecipeVersion::query()
                ->whereHas(
                    'recipe',
                    fn ($query) => $query->where(
                        'organization_id',
                        $organization->id,
                    ),
                )
                ->whereKey($recipeVersion->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($version->status !== RecipeVersionStatus::Draft) {
                throw ValidationException::withMessages([
                    'recipe_version' => __(
                        'Only a draft recipe version can be published.',
                    ),
                ]);
            }

            [$effectiveStartDate, $effectiveEndDate] = $this->effectivePeriod(
                $attributes,
            );

            $this->assertNoEffectivePeriodOverlap(
                $version,
                $effectiveStartDate,
                $effectiveEndDate,
            );

            $this->assertValidOutput($organization, $version);
            $this->assertValidComponents($organization, $version);

            $publishedAt = now();

            $version->forceFill([
                'status' => RecipeVersionStatus::Published,
                'published_by' => $actor->id,
                'published_at' => $publishedAt,
                'effective_start_date' => $effectiveStartDate,
                'effective_end_date' => $effectiveEndDate,
            ])->save();

            AuditLog::query()->create([
                'organization_id' => $organization->id,
                'actor_id' => $actor->id,
                'action' => 'recipe_version.published',
                'entity_type' => 'recipe_version',
                'entity_id' => $version->id,
                'before_data' => [
                    'status' => RecipeVersionStatus::Draft->value,
                ],
                'after_data' => [
                    'status' => RecipeVersionStatus::Published->value,
                    'published_at' => $publishedAt->toIso8601String(),
                    'effective_start_date' => $effectiveStartDate->toDateString(),
                    'effective_end_date' => $effectiveEndDate?->toDateString(),
                ],
                'correlation_id' => "recipe_version:{$version->id}:publish",
            ]);

            return $version->refresh();
        }, 3);
    }

    /**
     * Parse and validate the requested effective period.
     *
     * @param  array<string, mixed>  $attributes
     * @return array{0: Carbon, 1: Carbon|null}
     */
    private function effectivePeriod(array $attributes): array
    {
        $start = $attributes['effective_start_date'] ?? null;

        if (! is_string($start) || trim($start) === '' || ! strtotime($start)) {
            throw ValidationException::withMessages([
                'effective_start_date' => __(
                    'A valid effective start date is required.',
                ),
            ]);
        }

        $effectiveStartDate = Carbon::parse($start)->startOfDay();

        $end = $attributes['effective_end_date'] ?? null;
        $effectiveEndDate = null;

        if ($end !== null) {
            if (! is_string($end) || trim($end) === '' || ! strtotime($end)) {
                throw ValidationException::withMessages([
                    'effective_end_date' => __(
                        'The effective end date must be a valid date.',
                    ),
                ]);
            }

            $effectiveEndDate = Carbon::parse($end)->startOfDay();

            if ($effectiveEndDate->lt($effectiveStartDate)) {
                throw ValidationException::withMessages([
                    'effective_end_date' => __(
                        'The effective end date must be on or after the effective start date.',
                    ),
                ]);
            }
        }

        return [$effectiveStartDate, $effectiveEndDate];
    }

    /**
     * Reject a publication whose effective period overlaps another
     * published version of the same recipe.
     */
    private function assertNoEffectivePeriodOverlap(
        RecipeVersion $version,
        Carbon $effectiveStartDate,
        ?Carbon $effectiveEndDate,
    ): void {
        $overlaps = RecipeVersion::query()
            ->where('recipe_id', $version->recipe_id)
            ->where('status', RecipeVersionStatus::Published)
            ->whereKeyNot($version->id)
            ->where(
                fn ($query) => $query
                    ->whereNull('effective_end_date')
                    ->orWhere('effective_end_date', '>=', $effectiveStartDate),
            )
            ->when(
                $effectiveEndDate !== null,
                fn ($query) => $query->where(
                    'effective_start_date',
                    '<=',
                    $effectiveEndDate,
                ),
            )
            ->exists();

        if ($overlaps) {
            throw ValidationException::withMessages([
                'effective_start_date' => __(
                    'The effective period overlaps another published version of this recipe.',
                ),
            ]);
        }
    }

    /**
     * Re-validate the recipe version's finished yield output.
     */
    private function assertValidOutput(
        Organization $organization,
        RecipeVersion $version,
    ): void {
        $yieldUnit = $version->yieldUnit;

        if (
            $yieldUnit === null
            || $yieldUnit->organization_id !== $organization->id
            || ! $yieldUnit->active
        ) {
            throw ValidationException::withMessages([
                'yield_unit_id' => __(
                    'The yield unit must be an active unit belonging to the active organization.',
                ),
            ]);
        }

        if ($version->yield_quantity <= 0) {
            throw ValidationException::withMessages([
                'yield_quantity' => __(
                    'The yield quantity must be greater than zero.',
                ),
            ]);
        }
    }

    /**
     * Re-validate every component against its current tenant, unit, and
     * nested-version state before publication.
     */
    private function assertValidComponents(
        Organization $organization,
        RecipeVersion $version,
    ): void {
        $components = $version->components()
            ->with(['inventoryItem', 'unitOfMeasure', 'componentRecipeVersion'])
            ->get();

        if ($components->isEmpty()) {
            throw ValidationException::withMessages([
                'components' => __(
                    'At least one item component is required.',
                ),
            ]);
        }

        foreach ($components as $component) {
            $unitOfMeasure = $component->unitOfMeasure;

            if (
                $unitOfMeasure === null
                || $unitOfMeasure->organization_id !== $organization->id
                || ! $unitOfMeasure->active
            ) {
                throw ValidationException::withMessages([
                    'components' => __(
                        'Every component unit must be an active unit belonging to the active organization.',
                    ),
                ]);
            }

            if ($component->quantity <= 0 || $component->yield_percentage <= 0 || $component->yield_percentage > 100) {
                throw ValidationException::withMessages([
                    'components' => __(
                        'Every component must have a valid quantity and yield percentage.',
                    ),
                ]);
            }

            if ($component->component_recipe_version_id !== null) {
                $this->assertValidNestedComponent($organization, $version, $component->componentRecipeVersion);

                continue;
            }

            $inventoryItem = $component->inventoryItem;

            if (
                $inventoryItem === null
                || $inventoryItem->organization_id !== $organization->id
                || ! $inventoryItem->active
            ) {
                throw ValidationException::withMessages([
                    'components' => __(
                        'Every component item must be an active inventory item belonging to the active organization.',
                    ),
                ]);
            }
        }
    }

    /**
     * Re-validate a nested recipe version component's tenant, published
     * state, and absence of reference cycles.
     */
    private function assertValidNestedComponent(
        Organization $organization,
        RecipeVersion $version,
        ?RecipeVersion $nestedVersion,
    ): void {
        if (
            $nestedVersion === null
            || $nestedVersion->recipe->organization_id !== $organization->id
        ) {
            throw ValidationException::withMessages([
                'components' => __(
                    'Every nested component must reference a recipe version belonging to the active organization.',
                ),
            ]);
        }

        if ($nestedVersion->status !== RecipeVersionStatus::Published) {
            throw ValidationException::withMessages([
                'components' => __(
                    'Only published recipe versions can be nested as components.',
                ),
            ]);
        }

        $visited = [];

        if (in_array($version->recipe_id, RecipeVersionGraph::reachableRecipeIds($nestedVersion, $visited), true)) {
            throw ValidationException::withMessages([
                'components' => __(
                    'This recipe version cannot be published because a nested component would create a reference cycle.',
                ),
            ]);
        }
    }

    /**
     * Require recipe management permission.
     */
    private function authorize(
        Organization $organization,
        User $actor,
    ): void {
        if (
            ! $actor->hasOrganizationPermission(
                $organization,
                OrganizationPermission::RecipesManage,
            )
        ) {
            abort(403);
        }
    }
}
