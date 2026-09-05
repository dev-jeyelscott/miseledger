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
        bool $wasCreated = true,
        bool $isDeduplicationKey = false,
    ): ?AuditLog {
        if (! $wasCreated) {
            return null;
        }

        $attributes = [
            'organization_id' => $organization->getKey(),
            'actor_id' => $actor?->getKey(),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'before_data' => $beforeData,
            'after_data' => $afterData,
            'correlation_id' => $correlationId,
            'is_deduplication_key' => $isDeduplicationKey,
        ];

        if (! $isDeduplicationKey) {
            return AuditLog::query()->create($attributes);
        }

        $deduplicationAttributes = [
            ...$attributes,
            'before_data' => $beforeData === null
                ? null
                : json_encode($beforeData, JSON_THROW_ON_ERROR),
            'after_data' => $afterData === null
                ? null
                : json_encode($afterData, JSON_THROW_ON_ERROR),
        ];

        $wasInserted = AuditLog::query()->insertOrIgnore($deduplicationAttributes) === 1;

        return $wasInserted
            ? AuditLog::query()
                ->where('organization_id', $organization->getKey())
                ->where('correlation_id', $correlationId)
                ->where('is_deduplication_key', true)
                ->first()
            : null;
    }
}
