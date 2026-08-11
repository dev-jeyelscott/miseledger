<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    /**
     * Define a valid organization fixture.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->company();
        $slug = Str::slug($name) ?: 'organization';

        return [
            'name' => $name,
            'slug' => $slug.'-'.Str::lower(Str::random(8)),
            'timezone' => 'Asia/Manila',
            'currency' => 'PHP',
            'active' => true,
        ];
    }
}
