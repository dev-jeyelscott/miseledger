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
    /** Emit the queued lifecycle signal for an AI run. */
    public function queued(AiRun $run): void
    {
        $this->record('ai.run.queued', $run);
    }

    /** Emit the started lifecycle signal for an AI run. */
    public function started(AiRun $run): void
    {
        $this->record('ai.run.started', $run);
    }

    /** Emit the completed lifecycle signal with safe execution metrics. */
    public function completed(AiRun $run): void
    {
        $this->record('ai.run.completed', $run, [
            'input_tokens' => $run->input_tokens,
            'output_tokens' => $run->output_tokens,
            'duration_ms' => $this->durationMs($run),
        ]);
    }

    /** Emit the denied lifecycle signal with its safe reason code. */
    public function denied(AiRun $run, string $reason): void
    {
        $this->record('ai.run.denied', $run, ['reason' => $reason]);
    }

    /** Emit the failed lifecycle signal with its safe error code. */
    public function failed(AiRun $run): void
    {
        $this->record('ai.run.failed', $run, ['error_code' => $run->error_code]);
    }

    /**
     * Emit a metric-shaped tool lifecycle signal without retaining an MCP
     * request, response, or provider-managed profile detail.
     *
     * @param  array<string, int|string>  $metadata
     */
    public function toolCalled(
        AiRun $run,
        string $toolName,
        string $outcome,
        array $metadata,
    ): void {
        $this->record('ai.tool.'.$outcome, $run, [
            'metric_name' => 'ai.tool.calls',
            'metric_value' => 1,
            'tool_name' => $toolName,
            'tool_outcome' => $outcome,
            ...$metadata,
        ]);
    }

    /**
     * Write one safe operational signal to the configured AI log channel.
     *
     * @param  array<string, int|string|null>  $context
     */
    private function record(string $event, AiRun $run, array $context = []): void
    {
        Log::channel((string) config('ai.logger'))->info('AI operational signal emitted.', [
            'event' => $event,
            'metric_name' => 'ai.run.lifecycle',
            'metric_value' => 1,
            'ai_run_id' => $run->getKey(),
            'organization_id' => $run->conversation->organization_id,
            'user_id' => $run->user_id,
            'provider' => $run->provider->value,
            'status' => $run->status->value,
            ...$context,
        ]);
    }

    /** Calculate a non-negative whole-millisecond duration for a completed run. */
    private function durationMs(AiRun $run): ?int
    {
        if ($run->started_at === null || $run->finished_at === null) {
            return null;
        }

        return max(
            0,
            (int) $run->started_at->diffInMilliseconds($run->finished_at),
        );
    }
}
