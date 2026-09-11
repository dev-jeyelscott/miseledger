<?php

use App\Enums\BillingPaymentStatus;
use App\Enums\ProblemReportStatus;
use App\Models\BillingInvoice;
use App\Models\BillingPayment;
use App\Models\Organization;
use App\Models\PlatformAdmin;
use App\Models\ProblemReport;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

test('platform overview aggregates organizations users and exact open problem report statuses', function () {
    $platformUser = User::factory()->create();
    $verifiedUser = User::factory()->create();
    User::factory()->unverified()->create();

    PlatformAdmin::query()->create([
        'user_id' => $platformUser->getKey(),
    ]);

    Organization::factory()->count(2)->create([
        'active' => true,
    ]);

    Organization::factory()->create([
        'active' => false,
    ]);

    $submitted = ProblemReport::factory()->create([
        'user_id' => $verifiedUser->id,
        'status' => ProblemReportStatus::Submitted,
        'created_at' => Carbon::parse('2026-09-11 03:00:00', 'UTC'),
    ]);

    $inReview = ProblemReport::factory()->create([
        'user_id' => $verifiedUser->id,
        'status' => ProblemReportStatus::InReview,
        'created_at' => Carbon::parse('2026-09-11 02:00:00', 'UTC'),
    ]);

    $inProgress = ProblemReport::factory()->create([
        'user_id' => $verifiedUser->id,
        'status' => ProblemReportStatus::InProgress,
        'created_at' => Carbon::parse('2026-09-11 01:00:00', 'UTC'),
    ]);

    ProblemReport::factory()->create([
        'user_id' => $verifiedUser->id,
        'status' => ProblemReportStatus::Resolved,
        'created_at' => Carbon::parse('2026-09-11 05:00:00', 'UTC'),
    ]);

    ProblemReport::factory()->create([
        'user_id' => $verifiedUser->id,
        'status' => ProblemReportStatus::Closed,
        'created_at' => Carbon::parse('2026-09-11 04:00:00', 'UTC'),
    ]);

    $this->actingAs($platformUser)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('admin/dashboard')
                ->where('metrics.organizations.total', 3)
                ->where('metrics.organizations.active', 2)
                ->where('metrics.organizations.inactive', 1)
                ->where('metrics.users.total', 3)
                ->where('metrics.users.verified', 2)
                ->where('metrics.users.unverified', 1)
                ->where('metrics.openProblemReports', 3)
                ->has('openProblemReports', 3)
                ->where(
                    'openProblemReports.0.reference',
                    $submitted->reference,
                )
                ->where(
                    'openProblemReports.1.reference',
                    $inReview->reference,
                )
                ->where(
                    'openProblemReports.2.reference',
                    $inProgress->reference,
                ),
        );
});

test('open problem reports are bounded deterministically and expose only safe overview fields', function () {
    $platformUser = User::factory()->create();
    $reportUser = User::factory()->create();

    PlatformAdmin::query()->create([
        'user_id' => $platformUser->getKey(),
    ]);

    $createdAt = Carbon::parse('2026-09-11 06:00:00', 'UTC');

    for ($index = 1; $index <= 6; $index++) {
        ProblemReport::factory()->create([
            'reference' => sprintf('PR-260911-%06d', $index),
            'user_id' => $reportUser->id,
            'organization_name_snapshot' => "Snapshot {$index}",
            'title' => $index === 6 ? null : "Report {$index}",
            'status' => ProblemReportStatus::InReview,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    $this->actingAs($platformUser)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->has('openProblemReports', 5)
                ->where('openProblemReports.0', [
                    'reference' => 'PR-260911-000006',
                    'title' => null,
                    'status' => 'In review',
                    'organizationName' => 'Snapshot 6',
                    'timestamp' => $createdAt->toIso8601String(),
                ])
                ->where(
                    'openProblemReports.1.reference',
                    'PR-260911-000005',
                )
                ->where(
                    'openProblemReports.2.reference',
                    'PR-260911-000004',
                )
                ->where(
                    'openProblemReports.3.reference',
                    'PR-260911-000003',
                )
                ->where(
                    'openProblemReports.4.reference',
                    'PR-260911-000002',
                ),
        );
});

test('failed billing payments are filtered bounded ordered and preserve minor-unit money integrity', function () {
    $platformUser = User::factory()->create();

    PlatformAdmin::query()->create([
        'user_id' => $platformUser->getKey(),
    ]);

    $failedAt = Carbon::parse('2026-09-11 07:00:00', 'UTC');

    for ($index = 1; $index <= 6; $index++) {
        $invoice = BillingInvoice::factory()->create([
            'currency' => 'PHP',
            'amount' => 49_900 + $index,
        ]);

        $payment = BillingPayment::factory()->create([
            'billing_invoice_id' => $invoice->id,
            'currency' => $invoice->currency,
            'amount' => $invoice->amount,
            'status' => BillingPaymentStatus::Failed,
            'failed_at' => $failedAt,
            'provider_error_code' => "processor_error_{$index}",
        ]);

        Organization::query()
            ->whereKey($payment->organization_id)
            ->update([
                'name' => "Organization {$index}",
            ]);
    }

    $nonFailedInvoice = BillingInvoice::factory()->create([
        'currency' => 'PHP',
        'amount' => 99_900,
    ]);

    BillingPayment::factory()->create([
        'billing_invoice_id' => $nonFailedInvoice->id,
        'currency' => $nonFailedInvoice->currency,
        'amount' => $nonFailedInvoice->amount,
        'status' => BillingPaymentStatus::Paid,
        'failed_at' => $failedAt->copy()->addHour(),
        'provider_error_code' => 'must_not_be_exposed',
    ]);

    $this->actingAs($platformUser)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->has('failedBillingPayments', 5)
                ->where('failedBillingPayments.0', [
                    'organizationName' => 'Organization 6',
                    'provider' => 'Stripe',
                    'currency' => 'PHP',
                    'amountMinor' => 49_906,
                    'failureTimestamp' => $failedAt->toIso8601String(),
                    'providerErrorCode' => 'processor_error_6',
                ])
                ->where(
                    'failedBillingPayments.1.organizationName',
                    'Organization 5',
                )
                ->where(
                    'failedBillingPayments.2.organizationName',
                    'Organization 4',
                )
                ->where(
                    'failedBillingPayments.3.organizationName',
                    'Organization 3',
                )
                ->where(
                    'failedBillingPayments.4.organizationName',
                    'Organization 2',
                ),
        );
});

test('platform overview remains available to platform admins without organization memberships', function () {
    $platformUser = User::factory()->create();

    PlatformAdmin::query()->create([
        'user_id' => $platformUser->getKey(),
    ]);

    expect($platformUser->organizationMemberships()->exists())->toBeFalse();

    $this->actingAs($platformUser)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('admin/dashboard')
                ->where('auth.isPlatformAdmin', true)
                ->where('organizationContext.active', null)
                ->where('organizationContext.memberships', [])
                ->where('metrics.organizations.total', 0)
                ->where('metrics.users.total', 1),
        );
});

test('platform overview uses manual inertia refresh without polling or unsupported recovery language', function () {
    $dashboard = (string) file_get_contents(
        resource_path('js/pages/admin/dashboard.tsx'),
    );

    expect($dashboard)
        ->toContain('router.reload({')
        ->not->toContain('setInterval(')
        ->not->toContain('setTimeout(')
        ->and(strtolower($dashboard))
        ->not->toContain('unresolved');
});
