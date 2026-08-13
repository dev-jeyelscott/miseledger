<?php

namespace Database\Factories;

use App\Models\StandardUnit;
use App\Support\Inventory\StandardUnits;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StandardUnit>
 */
class StandardUnitFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $unit = fake()->randomElement(StandardUnits::definitions());

        return [
            'code' => $unit['symbol'],
            'name' => $unit['name'],
            'dimension' => $unit['dimension'],
            'canonical_factor' => $unit['canonical_factor'],
            'active' => true,
        ];
    }
}
