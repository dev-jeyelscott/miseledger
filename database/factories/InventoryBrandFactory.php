<?php

namespace Database\Factories;

use App\Models\InventoryBrand;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryBrand>
 */
class InventoryBrandFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->unique()->words(2, true),
            'active' => true,
        ];
    }
}
