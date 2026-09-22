<?php

namespace App\Actions\Platform\Alerts\Evaluators;

use App\Actions\Platform\Alerts\RecordPlatformAlertObservation;
use App\Actions\Platform\Alerts\ResolvePlatformAlertObservation;
use App\Actions\Platform\CheckDatabaseHealth;
use App\Enums\PlatformAlertSeverity;
use App\Enums\PlatformAlertType;

/**
 * Mirrors the Platform Health PostgreSQL probe (POC-V8.2) into a persistent
 * alert (POC-V9.2). A "down" probe result is real, machine-observed failure
 * evidence, not Unknown: the probe completed but the connection/read-only
 * transaction itself failed.
 */
final class EvaluateDatabaseHealthAlert
{
    private const FINGERPRINT = 'database.health';

    public function __construct(
        private readonly CheckDatabaseHealth $check,
        private readonly RecordPlatformAlertObservation $record,
        private readonly ResolvePlatformAlertObservation $resolve,
    ) {}

    public function handle(): void
    {
        $result = $this->check->handle();

        match ($result['status']) {
            'down', 'warning' => $this->record->handle(
                fingerprint: self::FINGERPRINT,
                type: PlatformAlertType::DatabaseHealth,
                source: $result['source'],
                severity: $result['status'] === 'down'
                    ? PlatformAlertSeverity::Critical
                    : PlatformAlertSeverity::Warning,
                title: $result['status'] === 'down'
                    ? 'PostgreSQL health probe failed'
                    : 'PostgreSQL connection capacity is near its limit',
                summary: sprintf(
                    'Active connections: %s of %s max.',
                    $result['activeConnections'] ?? 'unknown',
                    $result['maxConnections'] ?? 'unknown',
                ),
                context: [
                    'activeConnections' => $result['activeConnections'],
                    'maxConnections' => $result['maxConnections'],
                ],
            ),
            'healthy' => $this->resolve->handle(self::FINGERPRINT),
            default => null,
        };
    }
}
