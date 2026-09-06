<?php

namespace Database\Factories;

use App\Models\AiRun;
use App\Models\AiToolCall;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AiToolCall>
 */
class AiToolCallFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ai_run_id' => AiRun::factory(),
            'tool_name' => 'inventory_lookup',
            'provider_tool_call_id' => 'call_'.Str::lower(Str::random(14)),
            'status' => 'succeeded',
            'metadata' => [
                'resource_type' => 'inventory_item',
                'resource_id' => '1',
            ],
        ];
    }
}
