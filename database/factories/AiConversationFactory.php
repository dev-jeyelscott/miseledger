<?php

namespace Database\Factories;

use App\Models\AiConversation;
use App\Models\OrganizationMembership;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiConversation>
 */
class AiConversationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $membership = OrganizationMembership::factory()->create();

        return [
            'organization_id' => $membership->organization_id,
            'user_id' => $membership->user_id,
            'title' => fake()->sentence(4),
        ];
    }
}
