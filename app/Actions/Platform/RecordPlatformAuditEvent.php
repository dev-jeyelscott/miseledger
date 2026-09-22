<?php

namespace App\Actions\Platform;

use App\Enums\PlatformAuditAction;
use App\Models\PlatformAuditEvent;
use App\Models\User;

/**
 * The single write path for dedicated platform audit evidence (POC-V10.3).
 * Callers pass only safe, already-minimized `before`/`after` evidence; this
 * action never inspects or redacts payloads, so secrets must never reach it.
 */
final class RecordPlatformAuditEvent
{
    /**
     * @param  array<string, mixed>|null  $before  Safe evidence only. Never secrets or raw credentials.
     * @param  array<string, mixed>|null  $after  Safe evidence only. Never secrets or raw credentials.
     */
    public function handle(
        PlatformAuditAction $action,
        string $subjectType,
        ?string $subjectId,
        User $actor,
        string $source,
        ?array $before = null,
        ?array $after = null,
        ?string $correlationKey = null,
    ): PlatformAuditEvent {
        return PlatformAuditEvent::query()->create([
            'actor_user_id' => $actor->getKey(),
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'before' => $before,
            'after' => $after,
            'correlation_key' => $correlationKey,
            'source' => $source,
            'occurred_at' => now(),
        ]);
    }
}
