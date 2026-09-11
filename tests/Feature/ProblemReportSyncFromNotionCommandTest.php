<?php

use App\Enums\ProblemReportStatus;
use App\Jobs\ReconcileProblemReportFromNotion;
use App\Models\ProblemReport;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Mockery;

describe('Problem Report Sync From Notion Command', function () {
    beforeEach(function () {
        Queue::fake();
    });

    it('dispatches one job per eligible report', function () {
        ProblemReport::factory(3)->create([
            'status' => ProblemReportStatus::Submitted,
            'notion_id' => 'page-123',
        ]);

        $this->artisan('problem-report:sync-from-notion');

        Queue::assertPushedTimes(ReconcileProblemReportFromNotion::class, 3);
    });

    it('skips reports without notion_id', function () {
        ProblemReport::factory(2)->create([
            'status' => ProblemReportStatus::Submitted,
            'notion_id' => null,
        ]);

        ProblemReport::factory(1)->create([
            'status' => ProblemReportStatus::Submitted,
            'notion_id' => 'page-456',
        ]);

        $this->artisan('problem-report:sync-from-notion');

        Queue::assertPushedTimes(ReconcileProblemReportFromNotion::class, 1);
    });

    it('skips Closed reports', function () {
        ProblemReport::factory(2)->create([
            'status' => ProblemReportStatus::Closed,
            'notion_id' => 'page-123',
        ]);

        ProblemReport::factory(1)->create([
            'status' => ProblemReportStatus::Resolved,
            'notion_id' => 'page-456',
        ]);

        $this->artisan('problem-report:sync-from-notion');

        Queue::assertPushedTimes(ReconcileProblemReportFromNotion::class, 1);
    });

    it('includes Resolved reports for continued polling before Closed', function () {
        ProblemReport::factory(1)->create([
            'status' => ProblemReportStatus::Resolved,
            'notion_id' => 'page-123',
        ]);

        $this->artisan('problem-report:sync-from-notion');

        Queue::assertPushedTimes(ReconcileProblemReportFromNotion::class, 1);
    });

    it('uses bounded chunking for database selection', function () {
        ProblemReport::factory(25)->create([
            'status' => ProblemReportStatus::Submitted,
            'notion_id' => 'page-123',
        ]);

        $this->artisan('problem-report:sync-from-notion', ['--chunk' => '10']);

        Queue::assertPushedTimes(ReconcileProblemReportFromNotion::class, 25);
    });

    it('respects custom chunk size option', function () {
        ProblemReport::factory(5)->create([
            'status' => ProblemReportStatus::Submitted,
            'notion_id' => 'page-123',
        ]);

        $this->artisan('problem-report:sync-from-notion', ['--chunk' => '2'])
            ->assertSuccessful();

        Queue::assertPushedTimes(ReconcileProblemReportFromNotion::class, 5);
    });

    it('outputs result counts to console', function () {
        ProblemReport::factory(3)->create([
            'status' => ProblemReportStatus::Submitted,
            'notion_id' => 'page-123',
        ]);

        ProblemReport::factory(2)->create([
            'status' => ProblemReportStatus::Closed,
            'notion_id' => 'page-456',
        ]);

        $result = $this->artisan('problem-report:sync-from-notion');

        $result->assertSuccessful();
        $result->expectsOutput(
            'Problem report reconciliation scheduled: 3 reports dispatched, 2 skipped.'
        );
    });

    it('returns success exit code', function () {
        ProblemReport::factory(1)->create([
            'status' => ProblemReportStatus::Submitted,
            'notion_id' => 'page-123',
        ]);

        $result = $this->artisan('problem-report:sync-from-notion');

        $result->assertSuccessful();
    });

    it('emits structured log with safe counts', function () {
        Log::spy();

        ProblemReport::factory(3)->create([
            'status' => ProblemReportStatus::Submitted,
            'notion_id' => 'page-123',
        ]);

        ProblemReport::factory(2)->create([
            'status' => ProblemReportStatus::Closed,
            'notion_id' => 'page-456',
        ]);

        $this->artisan('problem-report:sync-from-notion');

        Log::shouldHaveReceived('info')
            ->with('Problem report reconciliation scheduled', Mockery::on(function ($context) {
                return $context['dispatched'] === 3
                    && $context['skipped'] === 2;
            }));
    });
});
