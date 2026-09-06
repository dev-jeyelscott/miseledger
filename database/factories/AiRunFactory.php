<?php

namespace Database\Factories;

use App\Enums\AiProvider;
use App\Enums\AiRunStatus;
use App\Models\AiConversation;
use App\Models\AiProviderConnection;
use App\Models\AiRun;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AiRun>
 */
class AiRunFactory extends Factory
{
    public function configure(): static
    {
        return $this->afterMaking(function (AiRun $run): void {
            $conversation = AiConversation::query()
                ->findOrFail($run->ai_conversation_id);

            $run->user_id = $conversation->user_id;

            if ($run->ai_provider_connection_id === null) {
                $run->ai_provider_connection_user_id = null;

                return;
            }

            $run->ai_provider_connection_user_id = AiProviderConnection::query()
                ->findOrFail($run->ai_provider_connection_id)
                ->user_id;
        });
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ai_conversation_id' => AiConversation::factory(),
            'user_id' => null,
            'ai_provider_connection_id' => null,
            'ai_provider_connection_user_id' => null,
            'provider' => AiProvider::OpenAi,
            'provider_run_id' => 'run_'.Str::lower(Str::random(14)),
            'model' => 'gpt-5',
            'status' => AiRunStatus::Succeeded,
            'error_code' => null,
            'input_tokens' => 100,
            'output_tokens' => 200,
            'metadata' => ['attempt' => 1],
            'started_at' => now()->subSecond(),
            'finished_at' => now(),
        ];
    }
}
