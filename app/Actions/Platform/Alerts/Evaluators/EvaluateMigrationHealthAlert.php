<?php

namespace App\Actions\Platform\Alerts\Evaluators;

use App\Actions\Platform\Alerts\RecordPlatformAlertObservation;
use App\Actions\Platform\Alerts\ResolvePlatformAlertObservation;
use App\Actions\Platform\CheckMigrationHealth;
use App\Enums\PlatformAlertSeverity;
use App\Enums\PlatformAlertType;

/**
 * Mirrors the Platform Health migration probe (POC-V8.3) into a persistent
 * alert (POC-V9.2).
 */
final class EvaluateMigrationHealthAlert
{
    private const FINGERPRINT = 'database.migrations';

    public function __construct(
        private readonly CheckMigrationHealth $check,
        private readonly RecordPlatformAlertObservation $record,
        private readonly ResolvePlatformAlertObservation $resolve,
    ) {}

    public function handle(): void
    {
        $result = $this->check->handle();

        match ($result['status']) {
            'warning' => $this->record->handle(
                fingerprint: self::FINGERPRINT,
                type: PlatformAlertType::MigrationBacklog,
                source: $result['source'],
                severity: PlatformAlertSeverity::Warning,
                title: 'Pending database migrations detected',
                summary: "{$result['pendingCount']} migration(s) have not been applied.",
                context: [
                    'pendingCount' => $result['pendingCount'],
                    'pendingMigrations' => array_slice($result['pendingMigrations'], 0, 10),
                ],
            ),
            'healthy' => $this->resolve->handle(self::FINGERPRINT),
            default => null,
        };
    }
}
