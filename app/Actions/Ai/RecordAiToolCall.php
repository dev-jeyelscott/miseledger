<?php

namespace App\Actions\Ai;

use App\Models\AiRun;
use App\Models\AiToolCall;
use Illuminate\Support\Arr;

final class RecordAiToolCall
{
    /**
     * Persist one tool-call audit entry without arguments, result bodies, or
     * credentials. The metadata allow-list is deliberately narrow.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function handle(
        AiRun $run,
        string $toolName,
        string $status,
        ?string $providerToolCallId = null,
        array $metadata = [],
    ): AiToolCall {
        return $run->toolCalls()->create([
            'tool_name' => $toolName,
            'status' => $status,
            'provider_tool_call_id' => $providerToolCallId,
            'metadata' => $this->safeMetadata($metadata),
        ]);
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, int|string>
     */
    private function safeMetadata(array $metadata): array
    {
        return array_filter(
            Arr::only($metadata, [
                'resource_id',
                'outcome',
                'error_code',
                'duration_ms',
                'row_count',
                'truncated',
                'resource_type',
            ]),
            static fn (mixed $value): bool => is_int($value) || is_string($value),
        );
    }
}
