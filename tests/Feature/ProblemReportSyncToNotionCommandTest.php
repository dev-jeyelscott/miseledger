<?php

use App\Jobs\SyncProblemReportToNotion;
use App\Models\ProblemReport;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Mockery;

function configureProblemReportSyncToNotionCommand(bool $enabled = true): void
{
    config([
        'services.notion' => [
            'enabled' => $enabled,
            'api_key' => 'test-notion-secret',
            'api_version' => '2022-06-28',
            'data_source_id' => 'db-123',
        ],
    ]);
}

describe('Problem Report Sync To Notion Command', function () {
    beforeEach(function () {
        Queue::fake();
    });

    it('dispatches one job per report missing a notion id', function () {
        configureProblemReportSyncToNotionCommand();

        ProblemReport::factory(3)->create(['notion_id' => null]);
        ProblemReport::factory(2)->create(['notion_id' => 'page-existing']);
        Queue::fake();

        $this->artisan('problem-report:sync-to-notion');

        Queue::assertPushedTimes(SyncProblemReportToNotion::class, 3);
    });

    it('does nothing when the integration is disabled', function () {
        configureProblemReportSyncToNotionCommand(enabled: false);

        ProblemReport::factory(3)->create(['notion_id' => null]);
        Queue::fake();

        $result = $this->artisan('problem-report:sync-to-notion');

        $result->assertSuccessful();
        Queue::assertNotPushed(SyncProblemReportToNotion::class);
    });

    it('uses bounded chunking for database selection', function () {
        configureProblemReportSyncToNotionCommand();

        ProblemReport::factory(25)->create(['notion_id' => null]);
        Queue::fake();

        $this->artisan('problem-report:sync-to-notion', ['--chunk' => '10']);

        Queue::assertPushedTimes(SyncProblemReportToNotion::class, 25);
    });

    it('returns success exit code', function () {
        configureProblemReportSyncToNotionCommand();

        ProblemReport::factory(1)->create(['notion_id' => null]);
        Queue::fake();

        $result = $this->artisan('problem-report:sync-to-notion');

        $result->assertSuccessful();
    });

    it('outputs the dispatched count to console', function () {
        configureProblemReportSyncToNotionCommand();

        ProblemReport::factory(4)->create(['notion_id' => null]);
        Queue::fake();

        $result = $this->artisan('problem-report:sync-to-notion');

        $result->expectsOutput(
            'Problem report Notion sync recovery scheduled: 4 reports dispatched.'
        );
    });

    it('emits a structured log with the dispatched count', function () {
        configureProblemReportSyncToNotionCommand();

        ProblemReport::factory(2)->create(['notion_id' => null]);
        Queue::fake();
        Log::spy();

        $this->artisan('problem-report:sync-to-notion');

        Log::shouldHaveReceived('info')
            ->with('Problem report Notion sync recovery scheduled', Mockery::on(function ($context) {
                return $context['dispatched'] === 2;
            }));
    });
});
