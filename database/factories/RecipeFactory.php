<?php

namespace Database\Factories;

use App\Enums\RecipeType;
use App\Models\Organization;
use App\Models\Recipe;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Recipe>
 */
class RecipeFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'code' => strtoupper(fake()->unique()->bothify('RCP-#####')),
            'name' => fake()->words(3, true),
            'type' => RecipeType::MenuItem,
            'active' => true,
        ];
    }
}
