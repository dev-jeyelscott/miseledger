<?php

namespace App\Actions\Platform\Alerts\Evaluators;

use App\Actions\Platform\Alerts\RecordPlatformAlertObservation;
use App\Actions\Platform\Alerts\ResolvePlatformAlertObservation;
use App\Enums\PlatformAlertSeverity;
use App\Enums\PlatformAlertState;
use App\Enums\PlatformAlertType;
use App\Enums\ProblemReportStatus;
use App\Models\PlatformAlert;
use App\Models\ProblemReport;
use Illuminate\Support\Carbon;

/**
 * Opens an attention alert for any problem report that has remained
 * submitted, in review, or in progress for at least
 * `platform_alerts.problem_report_attention_hours` (POC-V9.2). Resolves
 * once the report is resolved or closed, or no longer qualifies
 * (POC-V9.6).
 */
final class EvaluateProblemReportAttentionAlert
{
    public function __construct(
        private readonly RecordPlatformAlertObservation $record,
        private readonly ResolvePlatformAlertObservation $resolve,
    ) {}

    public function handle(): void
    {
        $thresholdHours = (int) config('platform_alerts.problem_report_attention_hours');
        $cutoff = Carbon::now()->subHours($thresholdHours);

        $qualifying = ProblemReport::query()
            ->select(['id', 'reference', 'title', 'organization_name_snapshot', 'created_at'])
            ->whereIn('status', [
                ProblemReportStatus::Submitted,
                ProblemReportStatus::InReview,
                ProblemReportStatus::InProgress,
            ])
            ->where('created_at', '<=', $cutoff)
            ->get();

        $desiredFingerprints = [];

        foreach ($qualifying as $report) {
            $fingerprint = "problem-report.attention.{$report->reference}";
            $desiredFingerprints[] = $fingerprint;
            $ageHours = (int) $report->created_at->diffInHours(now());

            $this->record->handle(
                fingerprint: $fingerprint,
                type: PlatformAlertType::ProblemReportAttention,
                source: 'problem_reports',
                severity: $ageHours >= $thresholdHours * 2
                    ? PlatformAlertSeverity::Critical
                    : PlatformAlertSeverity::Warning,
                title: 'Problem report needs attention: '.($report->title ?? $report->reference),
                summary: "Report {$report->reference} has been open for {$ageHours} hour(s) without resolution.",
                context: [
                    'reference' => $report->reference,
                    'organizationName' => $report->organization_name_snapshot,
                    'ageHours' => $ageHours,
                ],
            );
        }

        PlatformAlert::query()
            ->where('type', PlatformAlertType::ProblemReportAttention)
            ->where('state', PlatformAlertState::Open)
            ->whereNotIn('fingerprint', $desiredFingerprints)
            ->get()
            ->each(fn (PlatformAlert $alert) => $this->resolve->handle($alert->fingerprint));
    }
}
