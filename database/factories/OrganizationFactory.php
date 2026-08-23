<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    /**
     * Define a valid organization fixture on an active generic trial, so it
     * is commercially writable by default like a freshly onboarded tenant.
     * Tests exercising read-only/past-due/unpaid states must override
     * `trial_ends_at` or attach a Cashier subscription explicitly.
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
            'trial_ends_at' => Carbon::now()->addDays(30),
        ];
    }
}
