<?php

namespace Database\Seeders;

use App\Actions\Recipes\PublishRecipeVersion;
use App\Actions\Recipes\SaveRecipe;
use App\Actions\Recipes\SaveRecipeVersion;
use App\Enums\RecipeType;
use App\Models\InventoryItem;
use App\Models\Organization;
use App\Models\Recipe;
use App\Models\RecipeVersion;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DemoRecipeSeeder extends Seeder
{
    /**
     * Seed published recipes, a nested recipe, and one realistic draft revision.
     */
    public function run(
        SaveRecipe $saveRecipe,
        SaveRecipeVersion $saveRecipeVersion,
        PublishRecipeVersion $publishRecipeVersion,
    ): void {
        if (app()->environment('production')) {
            return;
        }

        $organization = Organization::query()
            ->where('name', 'Sinta Kitchen & Café')
            ->sole();

        $actor = User::query()
            ->where('email', 'manager@miseledger.com')
            ->sole();

        try {
            Carbon::setTestNow('2026-06-20 10:00:00');

            $marinade = $saveRecipe->handle(
                $organization,
                [
                    'code' => 'ADOBO-MARINADE',
                    'name' => 'House Adobo Marinade',
                    'type' => RecipeType::Batch,
                    'active' => true,
                ],
            );

            $marinadeV1 = $saveRecipeVersion->handle(
                $organization,
                $actor,
                $marinade,
                [
                    'yield_quantity' => '2.000000',
                    'yield_unit_id' => $this->unit($organization, 'l')->id,
                    'notes' => 'Standard two-liter house marinade batch.',
                    'components' => [
                        $this->itemComponent($organization, 'SOY-SAUCE', '1.200000', 'l', '100.0000'),
                        $this->itemComponent($organization, 'CANE-VINEGAR', '0.600000', 'l', '100.0000'),
                        $this->itemComponent($organization, 'GARLIC', '0.150000', 'kg', '88.0000'),
                        $this->itemComponent($organization, 'BROWN-SUGAR', '0.080000', 'kg', '100.0000'),
                    ],
                ],
            );

            Carbon::setTestNow('2026-06-21 09:00:00');

            $marinadeV1 = $publishRecipeVersion->handle(
                $organization,
                $actor,
                $marinadeV1,
                [
                    'effective_start_date' => '2026-07-01',
                    'effective_end_date' => null,
                ],
            );

            Carbon::setTestNow('2026-06-22 11:00:00');

            $garlicRice = $saveRecipe->handle(
                $organization,
                [
                    'code' => 'GARLIC-RICE',
                    'name' => 'House Garlic Rice',
                    'type' => RecipeType::Batch,
                    'active' => true,
                ],
            );

            $garlicRiceV1 = $saveRecipeVersion->handle(
                $organization,
                $actor,
                $garlicRice,
                [
                    'yield_quantity' => '5.000000',
                    'yield_unit_id' => $this->unit($organization, 'kg')->id,
                    'notes' => 'Five-kilogram production batch for branch service.',
                    'components' => [
                        $this->itemComponent($organization, 'JASMINE-RICE', '5.000000', 'kg', '100.0000'),
                        $this->itemComponent($organization, 'GARLIC', '0.120000', 'kg', '88.0000'),
                        $this->itemComponent($organization, 'COOKING-OIL', '0.100000', 'l', '100.0000'),
                    ],
                ],
            );

            $publishRecipeVersion->handle(
                $organization,
                $actor,
                $garlicRiceV1,
                [
                    'effective_start_date' => '2026-07-01',
                    'effective_end_date' => null,
                ],
            );

            Carbon::setTestNow('2026-06-24 10:30:00');

            $icedLatte = $saveRecipe->handle(
                $organization,
                [
                    'code' => 'ICED-LATTE',
                    'name' => 'Classic Iced Latte',
                    'type' => RecipeType::MenuItem,
                    'active' => true,
                ],
            );

            $icedLatteV1 = $saveRecipeVersion->handle(
                $organization,
                $actor,
                $icedLatte,
                [
                    'yield_quantity' => '1.000000',
                    'yield_unit_id' => $this->unit($organization, 'piece')->id,
                    'notes' => 'Standard single-serve iced latte formulation.',
                    'components' => [
                        $this->itemComponent($organization, 'COFFEE-BEAN', '0.018000', 'kg', '100.0000'),
                        $this->itemComponent($organization, 'FRESH-MILK', '0.180000', 'l', '100.0000'),
                        $this->itemComponent($organization, 'BROWN-SUGAR', '0.015000', 'kg', '100.0000'),
                    ],
                ],
            );

            $publishRecipeVersion->handle(
                $organization,
                $actor,
                $icedLatteV1,
                [
                    'effective_start_date' => '2026-07-01',
                    'effective_end_date' => null,
                ],
            );

            Carbon::setTestNow('2026-06-25 14:00:00');

            $chickenAdobo = $saveRecipe->handle(
                $organization,
                [
                    'code' => 'CHICKEN-ADOBO',
                    'name' => 'Sinta Chicken Adobo',
                    'type' => RecipeType::MenuItem,
                    'active' => true,
                ],
            );

            $chickenAdoboV1 = $saveRecipeVersion->handle(
                $organization,
                $actor,
                $chickenAdobo,
                [
                    'yield_quantity' => '1.000000',
                    'yield_unit_id' => $this->unit($organization, 'piece')->id,
                    'notes' => 'Current plated Chicken Adobo recipe.',
                    'components' => [
                        $this->itemComponent($organization, 'CHK-THIGH', '0.250000', 'kg', '92.0000'),
                        $this->recipeComponent($organization, $marinadeV1, '0.120000', 'l'),
                        $this->itemComponent($organization, 'RED-ONION', '0.060000', 'kg', '90.0000'),
                        $this->itemComponent($organization, 'COOKING-OIL', '0.020000', 'l', '100.0000'),
                    ],
                ],
            );

            $publishRecipeVersion->handle(
                $organization,
                $actor,
                $chickenAdoboV1,
                [
                    'effective_start_date' => '2026-07-01',
                    'effective_end_date' => null,
                ],
            );

            Carbon::setTestNow('2026-08-16 15:30:00');

            $saveRecipeVersion->handle(
                $organization,
                $actor,
                $chickenAdobo,
                [
                    'yield_quantity' => '1.000000',
                    'yield_unit_id' => $this->unit($organization, 'piece')->id,
                    'notes' => 'Draft revision under kitchen tasting review; not yet effective.',
                    'components' => [
                        $this->itemComponent($organization, 'CHK-THIGH', '0.230000', 'kg', '92.0000'),
                        $this->recipeComponent($organization, $marinadeV1, '0.100000', 'l'),
                        $this->itemComponent($organization, 'RED-ONION', '0.050000', 'kg', '90.0000'),
                        $this->itemComponent($organization, 'COOKING-OIL', '0.018000', 'l', '100.0000'),
                    ],
                ],
            );

            $saveRecipe->handle(
                $organization,
                [
                    'code' => 'MANGO-SHAKE-SEASONAL',
                    'name' => 'Seasonal Mango Shake',
                    'type' => RecipeType::MenuItem,
                    'active' => false,
                ],
            );
        } finally {
            Carbon::setTestNow();
        }
    }

    /**
     * Build one inventory-item recipe component.
     *
     * @return array<string, mixed>
     */
    private function itemComponent(
        Organization $organization,
        string $sku,
        string $quantity,
        string $unitSymbol,
        string $yieldPercentage,
    ): array {
        $item = InventoryItem::query()
            ->where('organization_id', $organization->id)
            ->where('sku', $sku)
            ->sole();

        return [
            'inventory_item_id' => $item->id,
            'recipe_version_id' => null,
            'quantity' => $quantity,
            'unit_of_measure_id' => $this
                ->unit($organization, $unitSymbol)
                ->id,
            'yield_percentage' => $yieldPercentage,
            'notes' => null,
        ];
    }

    /**
     * Build one published nested-recipe component.
     *
     * @return array<string, mixed>
     */
    private function recipeComponent(
        Organization $organization,
        RecipeVersion $recipeVersion,
        string $quantity,
        string $unitSymbol,
    ): array {
        return [
            'inventory_item_id' => null,
            'recipe_version_id' => $recipeVersion->id,
            'quantity' => $quantity,
            'unit_of_measure_id' => $this
                ->unit($organization, $unitSymbol)
                ->id,
            'yield_percentage' => '100.0000',
            'notes' => null,
        ];
    }

    /**
     * Resolve one active organization UOM.
     */
    private function unit(
        Organization $organization,
        string $symbol,
    ): UnitOfMeasure {
        return UnitOfMeasure::query()
            ->where('organization_id', $organization->id)
            ->where('symbol', $symbol)
            ->where('active', true)
            ->sole();
    }
}
