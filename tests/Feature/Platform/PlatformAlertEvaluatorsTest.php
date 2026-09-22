<?php

use App\Actions\Platform\Alerts\Evaluators\EvaluateFailedPaymentAlerts;
use App\Actions\Platform\Alerts\Evaluators\EvaluateProblemReportAttentionAlert;
use App\Actions\Platform\Alerts\Evaluators\EvaluateTableGrowthAlert;
use App\Enums\BillingPaymentStatus;
use App\Enums\PlatformAlertSeverity;
use App\Enums\PlatformAlertState;
use App\Enums\PlatformAlertType;
use App\Enums\ProblemReportStatus;
use App\Models\BillingInvoice;
use App\Models\BillingPayment;
use App\Models\DatabaseTableSizeSnapshot;
use App\Models\Organization;
use App\Models\PlatformAlert;
use App\Models\ProblemReport;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
});

test('failed payment evaluator opens an alert once the organization reaches the configured threshold', function () {
    config(['platform_alerts.failed_payment_threshold' => 3]);

    $organization = Organization::factory()->create(['name' => 'Repeat Failures Co']);

    for ($index = 0; $index < 3; $index++) {
        $invoice = BillingInvoice::factory()->create();

        BillingPayment::factory()->create([
            'organization_id' => $organization->id,
            'billing_invoice_id' => $invoice->id,
            'status' => BillingPaymentStatus::Failed,
            'livemode' => true,
            'failed_at' => now()->subHours($index),
        ]);
    }

    app(EvaluateFailedPaymentAlerts::class)->handle();

    $alert = PlatformAlert::query()
        ->where('type', PlatformAlertType::BillingFailedPayments)
        ->where('fingerprint', "billing.failed-payments.org.{$organization->id}")
        ->first();

    expect($alert)->not->toBeNull()
        ->and($alert->state)->toBe(PlatformAlertState::Open)
        ->and($alert->context['failedCount'])->toBe(3);
});

test('failed payment evaluator does not open an alert below the configured threshold', function () {
    config(['platform_alerts.failed_payment_threshold' => 3]);

    $organization = Organization::factory()->create();

    $invoice = BillingInvoice::factory()->create();
    BillingPayment::factory()->create([
        'organization_id' => $organization->id,
        'billing_invoice_id' => $invoice->id,
        'status' => BillingPaymentStatus::Failed,
        'livemode' => true,
        'failed_at' => now(),
    ]);

    app(EvaluateFailedPaymentAlerts::class)->handle();

    expect(PlatformAlert::query()->count())->toBe(0);
});

test('failed payment evaluator resolves the alert once the organization no longer meets the threshold', function () {
    config(['platform_alerts.failed_payment_threshold' => 3]);

    $organization = Organization::factory()->create();

    $recentFailures = collect(range(0, 2))->map(function (int $index) use ($organization) {
        $invoice = BillingInvoice::factory()->create();

        return BillingPayment::factory()->create([
            'organization_id' => $organization->id,
            'billing_invoice_id' => $invoice->id,
            'status' => BillingPaymentStatus::Failed,
            'livemode' => true,
            'failed_at' => now()->subHours($index),
        ]);
    });

    app(EvaluateFailedPaymentAlerts::class)->handle();

    expect(
        PlatformAlert::query()->where('state', PlatformAlertState::Open)->count(),
    )->toBe(1);

    // Age every failure out of the window; the organization no longer qualifies.
    BillingPayment::query()
        ->whereIn('id', $recentFailures->pluck('id'))
        ->update(['failed_at' => now()->subDays(30)]);

    app(EvaluateFailedPaymentAlerts::class)->handle();

    $alert = PlatformAlert::query()->first();
    expect($alert->state)->toBe(PlatformAlertState::Resolved);
});

test('table growth evaluator never reports an anomaly without at least two snapshot dates, preserving Unknown separately from Failure', function () {
    app(EvaluateTableGrowthAlert::class)->handle();

    expect(PlatformAlert::query()->count())->toBe(0);
});

test('table growth evaluator opens an anomaly alert once a table exceeds the configured growth ratio', function () {
    config([
        'platform_alerts.table_growth_alert_ratio' => 0.5,
        'platform_alerts.table_growth_minimum_bytes' => 1_000_000,
        'platform_health.table_growth_lookback_days' => 7,
    ]);

    DatabaseTableSizeSnapshot::query()->create([
        'captured_on' => now()->subDays(7)->toDateString(),
        'schema_name' => 'public',
        'table_name' => 'big_table',
        'total_bytes' => 10_000_000,
        'table_bytes' => 9_000_000,
        'index_bytes' => 1_000_000,
    ]);

    DatabaseTableSizeSnapshot::query()->create([
        'captured_on' => now()->toDateString(),
        'schema_name' => 'public',
        'table_name' => 'big_table',
        'total_bytes' => 20_000_000,
        'table_bytes' => 18_000_000,
        'index_bytes' => 2_000_000,
    ]);

    app(EvaluateTableGrowthAlert::class)->handle();

    $alert = PlatformAlert::query()
        ->where('type', PlatformAlertType::TableGrowthAnomaly)
        ->first();

    expect($alert)->not->toBeNull()
        ->and($alert->state)->toBe(PlatformAlertState::Open);
});

test('problem report attention evaluator opens an alert once a report has been open past the configured threshold', function () {
    config(['platform_alerts.problem_report_attention_hours' => 48]);

    $report = ProblemReport::factory()->create([
        'status' => ProblemReportStatus::InReview,
        'created_at' => now()->subHours(100),
    ]);

    app(EvaluateProblemReportAttentionAlert::class)->handle();

    $alert = PlatformAlert::query()
        ->where('fingerprint', "problem-report.attention.{$report->reference}")
        ->first();

    expect($alert)->not->toBeNull()
        ->and($alert->state)->toBe(PlatformAlertState::Open)
        ->and($alert->severity)->toBe(PlatformAlertSeverity::Critical);
});

test('problem report attention evaluator resolves the alert once the report is closed', function () {
    config(['platform_alerts.problem_report_attention_hours' => 48]);

    $report = ProblemReport::factory()->create([
        'status' => ProblemReportStatus::InReview,
        'created_at' => now()->subHours(72),
    ]);

    app(EvaluateProblemReportAttentionAlert::class)->handle();

    $report->update(['status' => ProblemReportStatus::Resolved]);

    app(EvaluateProblemReportAttentionAlert::class)->handle();

    $alert = PlatformAlert::query()
        ->where('fingerprint', "problem-report.attention.{$report->reference}")
        ->first();

    expect($alert->state)->toBe(PlatformAlertState::Resolved);
});
