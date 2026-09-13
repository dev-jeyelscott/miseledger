<?php

namespace App\Http\Controllers\Platform;

use App\Enums\BillingPaymentStatus;
use App\Enums\BillingProvider;
use App\Enums\ProblemReportStatus;
use App\Http\Controllers\Controller;
use App\Models\BillingPayment;
use App\Models\Organization;
use App\Models\ProblemReport;
use App\Models\User;
use App\Support\Billing\PlatformBillingPaymentSignals;
use Inertia\Inertia;
use Inertia\Response;

final class PlatformDashboardController extends Controller
{
    /** Render the read-only platform owner overview from locally persisted data. */
    public function index(
        PlatformBillingPaymentSignals $paymentSignals,
    ): Response {
        $billingSignals = $paymentSignals->currentMonth('live');

        return Inertia::render('admin/dashboard', [
            'metrics' => [
                'organizations' => $this->organizationStats(),
                'users' => $this->userStats(),
                'openProblemReports' => $this->openProblemReportCount(),
            ],
            'billingSignals' => [
                'mode' => $billingSignals['mode'],
                'period' => $billingSignals['period'],
                'capturedPaymentCount' => $billingSignals[
                    'capturedPaymentCount'
                ],
                'failedPaymentAttempts' => $billingSignals[
                    'failedPaymentAttempts'
                ],
            ],
            'openProblemReports' => $this->openProblemReports(),
            'failedBillingPayments' => $this->failedBillingPayments(),
        ]);
    }

    /**
     * Compute total, active, and inactive organization counts.
     *
     * @return array{total: int, active: int, inactive: int}
     */
    private function organizationStats(): array
    {
        $stats = Organization::query()
            ->selectRaw(
                'COUNT(*) AS total_count, '
                .'SUM(CASE WHEN active THEN 1 ELSE 0 END) AS active_count, '
                .'SUM(CASE WHEN NOT active THEN 1 ELSE 0 END) AS inactive_count',
            )
            ->first();

        return [
            'total' => (int) ($stats?->getAttribute('total_count') ?? 0),
            'active' => (int) ($stats?->getAttribute('active_count') ?? 0),
            'inactive' => (int) ($stats?->getAttribute('inactive_count') ?? 0),
        ];
    }

    /**
     * Compute total, verified, and unverified user counts.
     *
     * @return array{total: int, verified: int, unverified: int}
     */
    private function userStats(): array
    {
        $stats = User::query()
            ->selectRaw(
                'COUNT(*) AS total_count, '
                .'SUM(CASE WHEN email_verified_at IS NOT NULL THEN 1 ELSE 0 END) AS verified_count, '
                .'SUM(CASE WHEN email_verified_at IS NULL THEN 1 ELSE 0 END) AS unverified_count',
            )
            ->first();

        return [
            'total' => (int) ($stats?->getAttribute('total_count') ?? 0),
            'verified' => (int) ($stats?->getAttribute('verified_count') ?? 0),
            'unverified' => (int) ($stats?->getAttribute('unverified_count') ?? 0),
        ];
    }

    /** Count exactly the problem-report states treated as open. */
    private function openProblemReportCount(): int
    {
        $stats = ProblemReport::query()
            ->selectRaw(
                'SUM(CASE WHEN status IN (?, ?, ?) THEN 1 ELSE 0 END) AS open_count',
                $this->openProblemReportStatuses(),
            )
            ->first();

        return (int) ($stats?->getAttribute('open_count') ?? 0);
    }

    /**
     * Return at most five newest open problem reports with minimized props.
     *
     * @return list<array{
     *     reference: string,
     *     title: string|null,
     *     status: string,
     *     organizationName: string|null,
     *     timestamp: string|null
     * }>
     */
    private function openProblemReports(): array
    {
        $reports = ProblemReport::query()
            ->select([
                'id',
                'reference',
                'title',
                'status',
                'organization_name_snapshot',
                'created_at',
            ])
            ->whereIn('status', $this->openProblemReportStatuses())
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(fn (ProblemReport $report): array => [
                'reference' => $report->reference,
                'title' => $report->title,
                'status' => $this->problemReportStatusLabel($report->status),
                'organizationName' => $report->organization_name_snapshot,
                'timestamp' => $report->created_at?->toIso8601String(),
            ])
            ->all();

        return array_values($reports);
    }

    /**
     * Return at most five newest confirmed failed payment attempts.
     *
     * @return list<array{
     *     organizationName: string,
     *     provider: string,
     *     currency: string,
     *     amountMinor: string,
     *     failureTimestamp: string|null,
     *     providerErrorCode: string|null
     * }>
     */
    private function failedBillingPayments(): array
    {
        $payments = BillingPayment::query()
            ->select([
                'id',
                'organization_id',
                'provider',
                'currency',
                'amount',
                'failed_at',
                'provider_error_code',
            ])
            ->with('organization:id,name')
            ->where('status', BillingPaymentStatus::Failed->value)
            ->whereNotNull('failed_at')
            ->orderByDesc('failed_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(fn (BillingPayment $payment): array => [
                'organizationName' => $payment->organization->name,
                'provider' => $this->billingProviderLabel($payment->provider),
                'currency' => $payment->currency,
                'amountMinor' => (string) $payment->amount,
                'failureTimestamp' => $payment->failed_at?->toIso8601String(),
                'providerErrorCode' => $payment->provider_error_code,
            ])
            ->all();

        return array_values($payments);
    }

    /**
     * Return the exact problem-report states treated as open.
     *
     * @return list<string>
     */
    private function openProblemReportStatuses(): array
    {
        return [
            ProblemReportStatus::Submitted->value,
            ProblemReportStatus::InReview->value,
            ProblemReportStatus::InProgress->value,
        ];
    }

    /** Convert a problem-report status into platform-facing copy. */
    private function problemReportStatusLabel(
        ProblemReportStatus $status,
    ): string {
        return match ($status) {
            ProblemReportStatus::Submitted => 'Submitted',
            ProblemReportStatus::InReview => 'In review',
            ProblemReportStatus::InProgress => 'In progress',
            ProblemReportStatus::Resolved => 'Resolved',
            ProblemReportStatus::Closed => 'Closed',
        };
    }

    /** Preserve official payment-provider branding. */
    private function billingProviderLabel(BillingProvider $provider): string
    {
        return match ($provider) {
            BillingProvider::Stripe => 'Stripe',
            BillingProvider::PayMongo => 'PayMongo',
        };
    }
}
