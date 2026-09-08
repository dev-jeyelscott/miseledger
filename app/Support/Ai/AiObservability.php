<?php

namespace App\Support\Ai;

use App\Models\AiRun;
use Illuminate\Support\Facades\Log;

/**
 * Emits lifecycle-only operational signals. Prompts, outputs, tool arguments,
 * tool results, profile paths, and provider credentials are never log fields.
 */
final class AiObservability
{
    public function queued(AiRun $run): void
    {
        $this->record('ai.run.queued', $run);
    }

    public function started(AiRun $run): void
    {
        $this->record('ai.run.started', $run);
    }

    public function completed(AiRun $run): void
    {
        $this->record('ai.run.completed', $run, [
            'input_tokens' => $run->input_tokens,
            'output_tokens' => $run->output_tokens,
        ]);
    }

    public function denied(AiRun $run, string $reason): void
    {
        $this->record('ai.run.denied', $run, ['reason' => $reason]);
    }

    public function failed(AiRun $run): void
    {
        $this->record('ai.run.failed', $run, ['error_code' => $run->error_code]);
    }

    /** @param array<string, int|string|null> $context */
    private function record(string $event, AiRun $run, array $context = []): void
    {
        Log::channel((string) config('ai.logger'))->info('AI operational signal emitted.', [
            'event' => $event,
            'ai_run_id' => $run->getKey(),
            'organization_id' => $run->conversation->organization_id,
            'user_id' => $run->user_id,
            'provider' => $run->provider->value,
            'status' => $run->status->value,
            ...$context,
        ]);
    }
}
