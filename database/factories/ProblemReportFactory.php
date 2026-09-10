<?php

namespace Database\Factories;

use App\Enums\ProblemReportStatus;
use App\Models\Organization;
use App\Models\ProblemReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProblemReport>
 */
class ProblemReportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => 'PR-'.now()->format('ymd').'-'.Str::random(6),
            'user_id' => User::factory(),
            'organization_id' => null,
            'organization_name_snapshot' => null,
            'title' => $this->faker->sentence(6),
            'description' => $this->faker->paragraphs(3, asText: true),
            'status' => ProblemReportStatus::Submitted,
        ];
    }

    /**
     * Indicate that the report should have an organization.
     */
    public function withOrganization(): static
    {
        return $this->state(function (array $attributes) {
            $organization = Organization::factory()->create();

            return [
                'organization_id' => $organization->id,
                'organization_name_snapshot' => $organization->name,
            ];
        });
    }

    /**
     * Indicate that the report has no title.
     */
    public function noTitle(): static
    {
        return $this->state([
            'title' => null,
        ]);
    }

    /**
     * Indicate that the report status.
     */
    public function status(ProblemReportStatus $status): static
    {
        return $this->state([
            'status' => $status,
        ]);
    }
}
