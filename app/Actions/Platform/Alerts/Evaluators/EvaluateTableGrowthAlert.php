<?php

namespace App\Actions\Platform\Alerts\Evaluators;

use App\Actions\Platform\Alerts\RecordPlatformAlertObservation;
use App\Actions\Platform\Alerts\ResolvePlatformAlertObservation;
use App\Actions\Platform\CheckTableGrowthHealth;
use App\Enums\PlatformAlertSeverity;
use App\Enums\PlatformAlertState;
use App\Enums\PlatformAlertType;
use App\Models\PlatformAlert;

/**
 * Flags a table as an anomaly when it exceeds a minimum size floor and has
 * grown by at least `platform_alerts.table_growth_alert_ratio` over the
 * Platform Health lookback window (POC-V8.5, POC-V9.2). Without at least
 * two snapshot dates, growth is genuinely Unknown and never reported as a
 * failure.
 */
final class EvaluateTableGrowthAlert
{
    public function __construct(
        private readonly CheckTableGrowthHealth $check,
        private readonly RecordPlatformAlertObservation $record,
        private readonly ResolvePlatformAlertObservation $resolve,
    ) {}

    public function handle(): void
    {
        $result = $this->check->handle();

        if (! $result['hasHistory']) {
            return;
        }

        $ratio = (float) config('platform_alerts.table_growth_alert_ratio');
        $minimumBytes = (int) config('platform_alerts.table_growth_minimum_bytes');

        $desiredFingerprints = [];

        foreach ($result['tables'] as $table) {
            $fingerprint = "database.table-growth.{$table['schemaName']}.{$table['tableName']}";
            $delta = $table['deltaBytes'];

            if ($delta === null || $table['currentBytes'] < $minimumBytes) {
                continue;
            }

            $previousBytes = $table['currentBytes'] - $delta;

            if ($previousBytes <= 0 || ($delta / $previousBytes) < $ratio) {
                continue;
            }

            $desiredFingerprints[] = $fingerprint;
            $growthPercent = round(($delta / $previousBytes) * 100, 1);

            $this->record->handle(
                fingerprint: $fingerprint,
                type: PlatformAlertType::TableGrowthAnomaly,
                source: $result['source'],
                severity: PlatformAlertSeverity::Warning,
                title: "Table growth anomaly: {$table['schemaName']}.{$table['tableName']}",
                summary: "Grew {$growthPercent}% (".number_format($delta)." bytes) over the trailing {$result['lookbackDays']} day(s).",
                context: [
                    'schemaName' => $table['schemaName'],
                    'tableName' => $table['tableName'],
                    'currentBytes' => $table['currentBytes'],
                    'deltaBytes' => $delta,
                    'lookbackDays' => $result['lookbackDays'],
                ],
            );
        }

        PlatformAlert::query()
            ->where('type', PlatformAlertType::TableGrowthAnomaly)
            ->where('state', PlatformAlertState::Open)
            ->whereNotIn('fingerprint', $desiredFingerprints)
            ->get()
            ->each(fn (PlatformAlert $alert) => $this->resolve->handle($alert->fingerprint));
    }
}
