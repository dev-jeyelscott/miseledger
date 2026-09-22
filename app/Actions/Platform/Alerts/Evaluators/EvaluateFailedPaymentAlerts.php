<?php

namespace App\Actions\Platform\Alerts\Evaluators;

use App\Actions\Platform\Alerts\RecordPlatformAlertObservation;
use App\Actions\Platform\Alerts\ResolvePlatformAlertObservation;
use App\Enums\BillingPaymentStatus;
use App\Enums\PlatformAlertSeverity;
use App\Enums\PlatformAlertState;
use App\Enums\PlatformAlertType;
use App\Models\BillingPayment;
use App\Models\PlatformAlert;
use Illuminate\Support\Carbon;

/**
 * Opens a platform alert for any organization with at least
 * `platform_alerts.failed_payment_threshold` failed live-mode payment
 * attempts inside the trailing `failed_payment_window_hours` window
 * (POC-V9.2). Resolves once the organization no longer meets the
 * threshold, for example after a subsequent captured payment or once the
 * failures age out of the window (POC-V9.6).
 */
final class EvaluateFailedPaymentAlerts
{
    public function __construct(
        private readonly RecordPlatformAlertObservation $record,
        private readonly ResolvePlatformAlertObservation $resolve,
    ) {}

    public function handle(): void
    {
        $threshold = (int) config('platform_alerts.failed_payment_threshold');
        $windowHours = (int) config('platform_alerts.failed_payment_window_hours');
        $since = Carbon::now()->subHours($windowHours);

        $qualifying = BillingPayment::query()
            ->select('organization_id')
            ->selectRaw('COUNT(*) AS failed_count')
            ->with('organization:id,name')
            ->where('status', BillingPaymentStatus::Failed->value)
            ->where('livemode', true)
            ->whereNotNull('failed_at')
            ->where('failed_at', '>=', $since)
            ->groupBy('organization_id')
            ->havingRaw('COUNT(*) >= ?', [$threshold])
            ->get();

        $desiredFingerprints = [];

        foreach ($qualifying as $row) {
            $organizationId = (int) $row->organization_id;
            $failedCount = (int) $row->getAttribute('failed_count');
            $fingerprint = "billing.failed-payments.org.{$organizationId}";
            $desiredFingerprints[] = $fingerprint;
            $organizationName = $row->organization->name;

            $this->record->handle(
                fingerprint: $fingerprint,
                type: PlatformAlertType::BillingFailedPayments,
                source: 'billing_payments',
                severity: $failedCount >= $threshold * 2
                    ? PlatformAlertSeverity::Critical
                    : PlatformAlertSeverity::Warning,
                title: "Repeated failed payments for {$organizationName}",
                summary: "{$failedCount} failed live payment attempt(s) in the trailing {$windowHours} hour(s).",
                context: [
                    'organizationId' => $organizationId,
                    'organizationName' => $organizationName,
                    'failedCount' => $failedCount,
                    'windowHours' => $windowHours,
                ],
            );
        }

        PlatformAlert::query()
            ->where('type', PlatformAlertType::BillingFailedPayments)
            ->where('state', PlatformAlertState::Open)
            ->whereNotIn('fingerprint', $desiredFingerprints)
            ->get()
            ->each(fn (PlatformAlert $alert) => $this->resolve->handle($alert->fingerprint));
    }
}
