<?php

namespace App\Actions\Audit;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;

final class RecordAuditEntry
{
    /**
     * Write one tenant-scoped, append-only audit entry through the shared boundary.
     *
     * @param  array<string, mixed>|null  $beforeData
     * @param  array<string, mixed>|null  $afterData
     */
    public function handle(
        Organization $organization,
        ?User $actor,
        string $action,
        string $entityType,
        int $entityId,
        ?array $beforeData,
        ?array $afterData,
        ?string $correlationId = null,
    ): AuditLog {
        return AuditLog::query()->create([
            'organization_id' => $organization->getKey(),
            'actor_id' => $actor?->getKey(),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'before_data' => $beforeData,
            'after_data' => $afterData,
            'correlation_id' => $correlationId,
        ]);
    }
}
