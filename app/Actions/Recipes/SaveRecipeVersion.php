<?php

namespace App\Actions\Recipes;

use App\Actions\Inventory\ConvertQuantity;
use App\Enums\OrganizationPermission;
use App\Enums\RecipeVersionStatus;
use App\Models\InventoryItem;
use App\Models\Organization;
use App\Models\Recipe;
use App\Models\RecipeVersion;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\NumberFormatException;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SaveRecipeVersion
{
    private const MAX_QUANTITY = '999999999.999999';

    public function __construct(
        private readonly ConvertQuantity $convertQuantity,
    ) {}

    /**
     * Create or replace a sequential, editable-while-draft recipe version.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function handle(
        Organization $organization,
        User $actor,
        Recipe $recipe,
        array $attributes,
        ?RecipeVersion $recipeVersion = null,
    ): RecipeVersion {
        return DB::transaction(function () use (
            $organization,
            $actor,
            $recipe,
            $attributes,
            $recipeVersion,
        ): RecipeVersion {
            $this->authorize($organization, $actor);

            $lockedRecipe = Recipe::query()
                ->where('organization_id', $organization->id)
                ->whereKey($recipe->id)
                ->lockForUpdate()
                ->firstOrFail();

            $version = $recipeVersion === null
                ? null
                : RecipeVersion::query()
                    ->where('recipe_id', $lockedRecipe->id)
                    ->whereKey($recipeVersion->id)
                    ->lockForUpdate()
                    ->firstOrFail();

            if (
                $version !== null
                && ! $version->status->isEditable()
            ) {
                throw ValidationException::withMessages([
                    'recipe_version' => __(
                        'Only draft recipe versions can be edited.',
                    ),
                ]);
            }

            $yieldUnit = $this->activeUnitOfMeasure(
                $organization,
                $attributes['yield_unit_id'] ?? null,
                'yield_unit_id',
            );

            $yieldQuantity = $this->positiveQuantity(
                $attributes['yield_quantity'] ?? null,
                'yield_quantity',
            );

            $rawComponents = $attributes['components'] ?? [];

            if (! is_array($rawComponents) || $rawComponents === []) {
                throw ValidationException::withMessages([
                    'components' => __(
                        'At least one item component is required.',
                    ),
                ]);
            }

            $componentSnapshots = [];
            $seenItemIds = [];

            foreach (array_values($rawComponents) as $index => $rawComponent) {
                if (! is_array($rawComponent)) {
                    throw ValidationException::withMessages([
                        "components.{$index}" => __(
                            'Invalid item component.',
                        ),
                    ]);
                }

                $inventoryItem = $this->activeInventoryItem(
                    $organization,
                    $rawComponent['inventory_item_id'] ?? null,
                    $index,
                );

                if (isset($seenItemIds[$inventoryItem->id])) {
                    throw ValidationException::withMessages([
                        "components.{$index}.inventory_item_id" => __(
                            'Each inventory item may appear only once per recipe version.',
                        ),
                    ]);
                }

                $seenItemIds[$inventoryItem->id] = true;

                $unitOfMeasure = $this->activeUnitOfMeasure(
                    $organization,
                    $rawComponent['unit_of_measure_id'] ?? null,
                    "components.{$index}.unit_of_measure_id",
                );

                $baseUnit = UnitOfMeasure::query()
                    ->where('organization_id', $organization->id)
                    ->whereKey($inventoryItem->base_unit_of_measure_id)
                    ->where('active', true)
                    ->first();

                if ($baseUnit === null) {
                    throw ValidationException::withMessages([
                        "components.{$index}.inventory_item_id" => __(
                            'The inventory item does not have an active base unit.',
                        ),
                    ]);
                }

                $quantity = $this->positiveQuantity(
                    $rawComponent['quantity'] ?? null,
                    "components.{$index}.quantity",
                );

                try {
                    $baseQuantity = $this->convertQuantity->handle(
                        $organization,
                        $inventoryItem,
                        (string) $quantity,
                        $unitOfMeasure,
                        $baseUnit,
                    );
                } catch (ValidationException $exception) {
                    throw ValidationException::withMessages([
                        "components.{$index}.unit_of_measure_id" => $this->firstValidationMessage(
                            $exception,
                        ),
                    ]);
                }

                $yieldPercentage = $this->yieldPercentage(
                    $rawComponent['yield_percentage'] ?? null,
                    "components.{$index}.yield_percentage",
                );

                $componentSnapshots[] = [
                    'inventory_item_id' => $inventoryItem->id,
                    'quantity' => (string) $quantity,
                    'unit_of_measure_id' => $unitOfMeasure->id,
                    'base_quantity' => BigDecimal::of($baseQuantity)
                        ->toScale(6, RoundingMode::HalfUp)
                        ->__toString(),
                    'yield_percentage' => (string) $yieldPercentage,
                    'notes' => $this->nullableString(
                        $rawComponent['notes'] ?? null,
                    ),
                ];
            }

            $values = [
                'yield_quantity' => (string) $yieldQuantity,
                'yield_unit_id' => $yieldUnit->id,
                'notes' => $this->nullableString(
                    $attributes['notes'] ?? null,
                ),
            ];

            if ($version === null) {
                $nextVersionNumber = ((int) RecipeVersion::query()
                    ->where('recipe_id', $lockedRecipe->id)
                    ->max('version_number')) + 1;

                $version = $lockedRecipe->versions()->create([
                    ...$values,
                    'version_number' => $nextVersionNumber,
                    'status' => RecipeVersionStatus::Draft,
                    'created_by' => $actor->id,
                    'published_by' => null,
                    'published_at' => null,
                ]);
            } else {
                $version->fill($values);
                $version->save();

                $version->components()->delete();
            }

            foreach ($componentSnapshots as $componentSnapshot) {
                $version->components()->create($componentSnapshot);
            }

            return $version->refresh();
        }, 3);
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

    /**
     * Resolve an active tenant-owned unit of measure.
     */
    private function activeUnitOfMeasure(
        Organization $organization,
        mixed $value,
        string $field,
    ): UnitOfMeasure {
        $unit = UnitOfMeasure::query()
            ->where('organization_id', $organization->id)
            ->where('active', true)
            ->find((int) $value);

        if ($unit === null) {
            throw ValidationException::withMessages([
                $field => __(
                    'Select an active unit belonging to the active organization.',
                ),
            ]);
        }

        return $unit;
    }

    /**
     * Resolve an active tenant-owned inventory item.
     */
    private function activeInventoryItem(
        Organization $organization,
        mixed $value,
        int $index,
    ): InventoryItem {
        $inventoryItem = InventoryItem::query()
            ->where('organization_id', $organization->id)
            ->where('active', true)
            ->find((int) $value);

        if ($inventoryItem === null) {
            throw ValidationException::withMessages([
                "components.{$index}.inventory_item_id" => __(
                    'Select an active inventory item belonging to the active organization.',
                ),
            ]);
        }

        return $inventoryItem;
    }

    /**
     * Parse a strictly positive quantity without floating point.
     */
    private function positiveQuantity(
        mixed $value,
        string $field,
    ): BigDecimal {
        try {
            $quantity = BigDecimal::of(trim((string) $value));
        } catch (NumberFormatException) {
            throw ValidationException::withMessages([
                $field => __('A valid quantity is required.'),
            ]);
        }

        if (
            $quantity->compareTo(BigDecimal::zero()) <= 0
            || $quantity->getScale() > 6
            || $quantity->compareTo(BigDecimal::of(self::MAX_QUANTITY)) > 0
        ) {
            throw ValidationException::withMessages([
                $field => __(
                    'Quantity must be greater than zero with at most six decimal places.',
                ),
            ]);
        }

        return $quantity->toScale(6, RoundingMode::HalfUp);
    }

    /**
     * Parse a yield percentage strictly between 0 (exclusive) and 100.
     */
    private function yieldPercentage(
        mixed $value,
        string $field,
    ): BigDecimal {
        try {
            $percentage = BigDecimal::of(trim((string) $value));
        } catch (NumberFormatException) {
            throw ValidationException::withMessages([
                $field => __('A valid yield percentage is required.'),
            ]);
        }

        if (
            $percentage->compareTo(BigDecimal::zero()) <= 0
            || $percentage->compareTo(BigDecimal::of('100')) > 0
            || $percentage->getScale() > 2
        ) {
            throw ValidationException::withMessages([
                $field => __(
                    'Yield percentage must be greater than zero and at most 100, with at most two decimal places.',
                ),
            ]);
        }

        return $percentage->toScale(2, RoundingMode::HalfUp);
    }

    /**
     * Preserve the useful conversion failure while mapping it to the component unit.
     */
    private function firstValidationMessage(
        ValidationException $exception,
    ): string {
        foreach ($exception->errors() as $messages) {
            $message = $messages[0] ?? null;

            if (is_string($message)) {
                return $message;
            }
        }

        return __('Invalid unit for this inventory item.');
    }

    /**
     * Normalize optional free-text notes.
     */
    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }
}
