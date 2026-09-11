<?php

use App\Enums\ProblemReportStatus;
use App\Jobs\ReconcileProblemReportFromNotion;
use App\Models\ProblemReport;
use App\Support\Notion\NotionRequestException;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

function configureNotionForReconciliation(array $overrides = []): void
{
    config([
        'services.notion' => array_merge([
            'enabled' => true,
            'api_key' => 'test-notion-secret',
            'api_version' => '2022-06-28',
            'data_source_id' => 'db-123',
        ], $overrides),
    ]);
}

function notionPageWithStatus(
    string $id,
    string $status = 'Submitted',
): array {
    return [
        'id' => $id,
        'properties' => [
            'Status' => [
                'status' => [
                    'name' => $status,
                ],
            ],
        ],
    ];
}

describe('Reconcile Problem Report From Notion', function () {
    beforeEach(function () {
        Queue::fake();
        Http::preventStrayRequests();
    });

    it('should be unique per report', function () {
        $job = new ReconcileProblemReportFromNotion(123);

        expect($job)->toBeInstanceOf(ShouldBeUnique::class);
        expect($job->uniqueId())->toBe('123');
    });

    it('should prevent concurrent execution with WithoutOverlapping middleware', function () {
        $job = new ReconcileProblemReportFromNotion(456);
        $middleware = $job->middleware();

        expect($middleware)
            ->toHaveLength(1)
            ->and($middleware[0])
            ->toBeInstanceOf(WithoutOverlapping::class);
    });

    it('skips when Notion is disabled', function () {
        configureNotionForReconciliation(['enabled' => false]);
        $report = ProblemReport::factory()->create([
            'status' => ProblemReportStatus::Submitted,
            'notion_id' => 'page-123',
        ]);

        $job = new ReconcileProblemReportFromNotion($report->id);
        $job->handle();

        $report->refresh();
        expect($report->status)->toBe(ProblemReportStatus::Submitted);
    });

    it('fails with permanent error when configuration is invalid', function () {
        configureNotionForReconciliation(['api_key' => null]);
        $report = ProblemReport::factory()->create([
            'status' => ProblemReportStatus::Submitted,
            'notion_id' => 'page-123',
        ]);

        $job = new ReconcileProblemReportFromNotion($report->id);
        $job->handle();

        $report->refresh();
        // Job calls $this->fail() on invalid config, preserving status
        expect($report->status)->toBe(ProblemReportStatus::Submitted);
    });

    it('skips when report does not exist', function () {
        configureNotionForReconciliation();

        $job = new ReconcileProblemReportFromNotion(99999);
        $job->handle();

        expect(true)->toBeTrue();
    });

    it('skips when report has no notion_id', function () {
        configureNotionForReconciliation();
        $report = ProblemReport::factory()->create([
            'status' => ProblemReportStatus::Submitted,
            'notion_id' => null,
        ]);

        $job = new ReconcileProblemReportFromNotion($report->id);
        $job->handle();

        $report->refresh();
        expect($report->status)->toBe(ProblemReportStatus::Submitted);
    });

    it('skips when report status is Closed', function () {
        configureNotionForReconciliation();
        Http::fake([
            'https://api.notion.com/v1/pages/page-123' => Http::response(
                notionPageWithStatus('page-123', 'In Progress'),
                200,
            ),
        ]);

        $report = ProblemReport::factory()->create([
            'status' => ProblemReportStatus::Closed,
            'notion_id' => 'page-123',
        ]);

        $job = new ReconcileProblemReportFromNotion($report->id);
        $job->handle();

        $report->refresh();
        expect($report->status)->toBe(ProblemReportStatus::Closed);
    });

    it('maps Submitted to Submitted', function () {
        configureNotionForReconciliation();
        Http::fake([
            'https://api.notion.com/v1/pages/page-123' => Http::response(
                notionPageWithStatus('page-123', 'Submitted'),
                200,
            ),
        ]);

        $report = ProblemReport::factory()->create([
            'status' => ProblemReportStatus::InProgress,
            'notion_id' => 'page-123',
        ]);

        $job = new ReconcileProblemReportFromNotion($report->id);
        $job->handle();

        $report->refresh();
        expect($report->status)->toBe(ProblemReportStatus::Submitted);
        expect($report->notion_status)->toBe('Submitted');
        expect($report->notion_last_checked_at)->not->toBeNull();
    });

    it('maps In Review to In Review', function () {
        configureNotionForReconciliation();
        Http::fake([
            'https://api.notion.com/v1/pages/page-123' => Http::response(
                notionPageWithStatus('page-123', 'In Review'),
                200,
            ),
        ]);

        $report = ProblemReport::factory()->create([
            'status' => ProblemReportStatus::Submitted,
            'notion_id' => 'page-123',
        ]);

        $job = new ReconcileProblemReportFromNotion($report->id);
        $job->handle();

        $report->refresh();
        expect($report->status)->toBe(ProblemReportStatus::InReview);
    });

    it('maps Ready to In Review', function () {
        configureNotionForReconciliation();
        Http::fake([
            'https://api.notion.com/v1/pages/page-123' => Http::response(
                notionPageWithStatus('page-123', 'Ready'),
                200,
            ),
        ]);

        $report = ProblemReport::factory()->create([
            'status' => ProblemReportStatus::Submitted,
            'notion_id' => 'page-123',
        ]);

        $job = new ReconcileProblemReportFromNotion($report->id);
        $job->handle();

        $report->refresh();
        expect($report->status)->toBe(ProblemReportStatus::InReview);
    });

    it('maps In Progress to In Progress', function () {
        configureNotionForReconciliation();
        Http::fake([
            'https://api.notion.com/v1/pages/page-123' => Http::response(
                notionPageWithStatus('page-123', 'In Progress'),
                200,
            ),
        ]);

        $report = ProblemReport::factory()->create([
            'status' => ProblemReportStatus::InReview,
            'notion_id' => 'page-123',
        ]);

        $job = new ReconcileProblemReportFromNotion($report->id);
        $job->handle();

        $report->refresh();
        expect($report->status)->toBe(ProblemReportStatus::InProgress);
    });

    it('maps Blocked to In Review', function () {
        configureNotionForReconciliation();
        Http::fake([
            'https://api.notion.com/v1/pages/page-123' => Http::response(
                notionPageWithStatus('page-123', 'Blocked'),
                200,
            ),
        ]);

        $report = ProblemReport::factory()->create([
            'status' => ProblemReportStatus::InProgress,
            'notion_id' => 'page-123',
        ]);

        $job = new ReconcileProblemReportFromNotion($report->id);
        $job->handle();

        $report->refresh();
        expect($report->status)->toBe(ProblemReportStatus::InReview);
    });

    it('maps Failed to In Review', function () {
        configureNotionForReconciliation();
        Http::fake([
            'https://api.notion.com/v1/pages/page-123' => Http::response(
                notionPageWithStatus('page-123', 'Failed'),
                200,
            ),
        ]);

        $report = ProblemReport::factory()->create([
            'status' => ProblemReportStatus::InProgress,
            'notion_id' => 'page-123',
        ]);

        $job = new ReconcileProblemReportFromNotion($report->id);
        $job->handle();

        $report->refresh();
        expect($report->status)->toBe(ProblemReportStatus::InReview);
    });

    it('maps Skipped to In Review', function () {
        configureNotionForReconciliation();
        Http::fake([
            'https://api.notion.com/v1/pages/page-123' => Http::response(
                notionPageWithStatus('page-123', 'Skipped'),
                200,
            ),
        ]);

        $report = ProblemReport::factory()->create([
            'status' => ProblemReportStatus::InProgress,
            'notion_id' => 'page-123',
        ]);

        $job = new ReconcileProblemReportFromNotion($report->id);
        $job->handle();

        $report->refresh();
        expect($report->status)->toBe(ProblemReportStatus::InReview);
    });

    it('maps Done to Resolved', function () {
        configureNotionForReconciliation();
        Http::fake([
            'https://api.notion.com/v1/pages/page-123' => Http::response(
                notionPageWithStatus('page-123', 'Done'),
                200,
            ),
        ]);

        $report = ProblemReport::factory()->create([
            'status' => ProblemReportStatus::InProgress,
            'notion_id' => 'page-123',
        ]);

        $job = new ReconcileProblemReportFromNotion($report->id);
        $job->handle();

        $report->refresh();
        expect($report->status)->toBe(ProblemReportStatus::Resolved);
    });

    it('maps Closed to Closed', function () {
        configureNotionForReconciliation();
        Http::fake([
            'https://api.notion.com/v1/pages/page-123' => Http::response(
                notionPageWithStatus('page-123', 'Closed'),
                200,
            ),
        ]);

        $report = ProblemReport::factory()->create([
            'status' => ProblemReportStatus::Resolved,
            'notion_id' => 'page-123',
        ]);

        $job = new ReconcileProblemReportFromNotion($report->id);
        $job->handle();

        $report->refresh();
        expect($report->status)->toBe(ProblemReportStatus::Closed);
    });

    it('preserves local status when remote status is unknown', function () {
        configureNotionForReconciliation();
        Http::fake([
            'https://api.notion.com/v1/pages/page-123' => Http::response(
                notionPageWithStatus('page-123', 'UnknownStatus'),
                200,
            ),
        ]);

        $report = ProblemReport::factory()->create([
            'status' => ProblemReportStatus::InProgress,
            'notion_id' => 'page-123',
            'notion_status' => 'In Progress',
            'notion_last_checked_at' => null,
            'notion_check_error' => null,
        ]);

        Log::spy();

        $job = new ReconcileProblemReportFromNotion($report->id);
        $job->handle();

        $report->refresh();
        expect($report->status)->toBe(ProblemReportStatus::InProgress);
        expect($report->notion_status)->toBe('UnknownStatus');
        expect($report->notion_last_checked_at)->not->toBeNull();
        expect($report->notion_check_error)->toBe('unknown_status');

        Log::shouldHaveReceived('warning')
            ->with('Unknown Notion status for problem report', Mockery::on(function ($context) use ($report) {
                return $context['problem_report_id'] === $report->id
                    && $context['local_status'] === 'in-progress'
                    && $context['notion_status'] === 'UnknownStatus';
            }));
    });

    it('preserves local status when remote status is missing', function () {
        configureNotionForReconciliation();
        Http::fake([
            'https://api.notion.com/v1/pages/page-123' => Http::response(
                [
                    'id' => 'page-123',
                    'properties' => [],
                ],
                200,
            ),
        ]);

        $report = ProblemReport::factory()->create([
            'status' => ProblemReportStatus::InProgress,
            'notion_id' => 'page-123',
            'notion_last_checked_at' => null,
            'notion_check_error' => null,
        ]);

        $job = new ReconcileProblemReportFromNotion($report->id);
        $job->handle();

        $report->refresh();
        expect($report->status)->toBe(ProblemReportStatus::InProgress);
        expect($report->notion_status)->toBeNull();
        expect($report->notion_last_checked_at)->not->toBeNull();
        expect($report->notion_check_error)->toBe('unknown_status');
    });

    it('preserves local status on permanent HTTP error (404)', function () {
        configureNotionForReconciliation();
        Http::fake([
            'https://api.notion.com/v1/pages/page-123' => Http::response(null, 404),
        ]);

        $report = ProblemReport::factory()->create([
            'status' => ProblemReportStatus::InProgress,
            'notion_id' => 'page-123',
            'notion_last_checked_at' => null,
        ]);

        $job = new ReconcileProblemReportFromNotion($report->id);
        $job->handle();

        $report->refresh();
        expect($report->status)->toBe(ProblemReportStatus::InProgress);
        expect($report->notion_last_checked_at)->toBeNull();
        expect($report->notion_check_error)->toBe('not_found');
    });

    it('preserves prior successful-check timestamp when a read fails', function () {
        configureNotionForReconciliation();
        Http::fake([
            'https://api.notion.com/v1/pages/page-123' => Http::response(null, 404),
        ]);

        $priorCheckTime = now()->subHours(2);
        $report = ProblemReport::factory()->create([
            'status' => ProblemReportStatus::InProgress,
            'notion_id' => 'page-123',
            'notion_last_checked_at' => $priorCheckTime,
        ]);

        $job = new ReconcileProblemReportFromNotion($report->id);
        $job->handle();

        $report->refresh();
        expect($report->status)->toBe(ProblemReportStatus::InProgress);
        expect($report->notion_last_checked_at->timestamp)->toBe($priorCheckTime->timestamp);
        expect($report->notion_check_error)->toBe('not_found');
    });

    it('updates notion_last_checked_at after successful read', function () {
        configureNotionForReconciliation();
        Http::fake([
            'https://api.notion.com/v1/pages/page-123' => Http::response(
                notionPageWithStatus('page-123', 'In Progress'),
                200,
            ),
        ]);

        $report = ProblemReport::factory()->create([
            'status' => ProblemReportStatus::Submitted,
            'notion_id' => 'page-123',
            'notion_last_checked_at' => null,
        ]);

        $beforeCheck = now();

        $job = new ReconcileProblemReportFromNotion($report->id);
        $job->handle();

        $report->refresh();
        expect($report->notion_last_checked_at)->not->toBeNull();
        expect($report->notion_last_checked_at->timestamp >= $beforeCheck->timestamp)->toBeTrue();
    });

    it('allows reopening from Resolved back to In Progress before Closed', function () {
        configureNotionForReconciliation();
        Http::fake([
            'https://api.notion.com/v1/pages/page-123' => Http::response(
                notionPageWithStatus('page-123', 'In Progress'),
                200,
            ),
        ]);

        $report = ProblemReport::factory()->create([
            'status' => ProblemReportStatus::Resolved,
            'notion_id' => 'page-123',
        ]);

        $job = new ReconcileProblemReportFromNotion($report->id);
        $job->handle();

        $report->refresh();
        expect($report->status)->toBe(ProblemReportStatus::InProgress);
    });

    it('retries on transient HTTP errors', function () {
        configureNotionForReconciliation();
        Http::fake([
            'https://api.notion.com/v1/pages/page-123' => Http::sequence()
                ->push(null, 429)
                ->push(notionPageWithStatus('page-123', 'In Progress'), 200),
        ]);

        $report = ProblemReport::factory()->create([
            'status' => ProblemReportStatus::Submitted,
            'notion_id' => 'page-123',
        ]);

        $job = new ReconcileProblemReportFromNotion($report->id);
        $job->handle();

        $report->refresh();
        expect($report->status)->toBe(ProblemReportStatus::InProgress);
    });

    it('persists safe error diagnostic when transient retries are exhausted', function () {
        configureNotionForReconciliation();
        Http::fake([
            'https://api.notion.com/v1/pages/page-123' => Http::response(null, 429),
        ]);

        $report = ProblemReport::factory()->create([
            'status' => ProblemReportStatus::InProgress,
            'notion_id' => 'page-123',
            'notion_last_checked_at' => now()->subHours(2),
            'notion_check_error' => null,
        ]);

        $priorCheckTime = $report->notion_last_checked_at;

        $job = new ReconcileProblemReportFromNotion($report->id);
        $job->failed(
            new NotionRequestException(
                status: 429,
                operation: 'retrievePageById',
                reason: 'rate_limit',
                isTransient: true,
            )
        );

        $report->refresh();
        expect($report->status)->toBe(ProblemReportStatus::InProgress);
        expect($report->notion_last_checked_at->timestamp)->toBe($priorCheckTime->timestamp);
        expect($report->notion_check_error)->toBe('rate_limited');
    });

});
