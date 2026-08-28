<?php

namespace Database\Factories;

use App\Models\InventoryProduct;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryProduct>
 */
class InventoryProductFactory extends Factory
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
