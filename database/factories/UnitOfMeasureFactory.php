<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\UnitOfMeasure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UnitOfMeasure>
 */
class UnitOfMeasureFactory extends Factory
{
    /**
     * Define a tenant-safe UOM.
     *
     * Custom test units default to count so they never receive an
     * accidental global weight or volume conversion.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->unique()->words(2, true),
            'symbol' => fake()->unique()->bothify('uom-###'),
            'dimension' => 'count',
            'active' => true,
        ];
    }
}
