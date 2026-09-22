<?php

namespace App\Actions\Platform\Alerts\Evaluators;

use App\Actions\Platform\Alerts\RecordPlatformAlertObservation;
use App\Actions\Platform\Alerts\ResolvePlatformAlertObservation;
use App\Actions\Platform\CheckSlowQueryDiagnostics;
use App\Enums\PlatformAlertSeverity;
use App\Enums\PlatformAlertType;

/**
 * Mirrors the Platform Health Pulse slow-query probe (POC-V8.4) into a
 * persistent alert (POC-V9.2). Only the already-sanitized SQL text and
 * durations Pulse itself exposes are ever copied into alert context; no
 * raw bind values are ever recorded here.
 */
final class EvaluateSlowQueryAlert
{
    private const FINGERPRINT = 'observability.slow-queries';

    public function __construct(
        private readonly CheckSlowQueryDiagnostics $check,
        private readonly RecordPlatformAlertObservation $record,
        private readonly ResolvePlatformAlertObservation $resolve,
    ) {}

    public function handle(): void
    {
        $result = $this->check->handle();

        match ($result['status']) {
            'warning' => $this->record->handle(
                fingerprint: self::FINGERPRINT,
                type: PlatformAlertType::SlowQueries,
                source: $result['source'],
                severity: PlatformAlertSeverity::Warning,
                title: 'Slow queries detected',
                summary: sprintf(
                    '%d slow query pattern(s) recorded in the trailing %d hour(s).',
                    count($result['queries']),
                    $result['windowHours'],
                ),
                context: [
                    'windowHours' => $result['windowHours'],
                    'count' => count($result['queries']),
                    'topQueries' => array_slice($result['queries'], 0, 3),
                ],
            ),
            'healthy' => $this->resolve->handle(self::FINGERPRINT),
            default => null,
        };
    }
}
