<?php

namespace App\Actions\Platform\Alerts\Evaluators;

use App\Actions\Platform\Alerts\RecordPlatformAlertObservation;
use App\Actions\Platform\Alerts\ResolvePlatformAlertObservation;
use App\Actions\Platform\CheckQueueHealth;
use App\Enums\PlatformAlertSeverity;
use App\Enums\PlatformAlertType;

/**
 * Mirrors the Platform Health Horizon probe (POC-V8.7) into a persistent
 * alert (POC-V9.2). "unknown" (the probe itself could not run) is never
 * treated as a failure and never opens or resolves an alert.
 */
final class EvaluateQueueHealthAlert
{
    private const FINGERPRINT = 'observability.queue-backlog';

    public function __construct(
        private readonly CheckQueueHealth $check,
        private readonly RecordPlatformAlertObservation $record,
        private readonly ResolvePlatformAlertObservation $resolve,
    ) {}

    public function handle(): void
    {
        $result = $this->check->handle();

        match ($result['status']) {
            'warning' => $this->record->handle(
                fingerprint: self::FINGERPRINT,
                type: PlatformAlertType::QueueBacklog,
                source: $result['source'],
                severity: PlatformAlertSeverity::Warning,
                title: 'Horizon queue backlog or failures detected',
                summary: sprintf(
                    'Active masters: %s, pending jobs: %s, recently failed jobs: %s.',
                    $result['activeMasters'] ?? 'unknown',
                    $result['pendingJobs'] ?? 'unknown',
                    $result['recentlyFailedJobs'] ?? 'unknown',
                ),
                context: [
                    'activeMasters' => $result['activeMasters'],
                    'pendingJobs' => $result['pendingJobs'],
                    'recentlyFailedJobs' => $result['recentlyFailedJobs'],
                ],
            ),
            'healthy' => $this->resolve->handle(self::FINGERPRINT),
            default => null,
        };
    }
}
