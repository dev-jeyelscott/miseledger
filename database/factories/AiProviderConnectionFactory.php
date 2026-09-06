<?php

namespace Database\Factories;

use App\Enums\AiProvider;
use App\Models\AiProviderConnection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AiProviderConnection>
 */
class AiProviderConnectionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'provider' => AiProvider::OpenAi,
            'external_account_id' => 'acct_'.Str::lower(Str::random(14)),
            'account_label' => 'Default workspace',
            'metadata' => ['account_type' => 'individual'],
            'is_active' => true,
            'activated_at' => now(),
            'deactivated_at' => null,
        ];
    }
}
