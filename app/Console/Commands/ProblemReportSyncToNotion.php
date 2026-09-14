<?php

namespace App\Console\Commands;

use App\Jobs\SyncProblemReportToNotion;
use App\Models\ProblemReport;
use App\Support\Notion\NotionConfig;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

final class ProblemReportSyncToNotion extends Command
{
    protected $signature =
        'problem-report:sync-to-notion {--chunk=100 : Number of problem reports to process per database batch}';

    protected $description =
        'Recover problem reports that never received an outbound Notion sync.';

    public function handle(): int
    {
        $config = NotionConfig::fromConfig((array) config('services.notion'));

        if (! $config->enabled) {
            $this->line('Problem report Notion sync recovery skipped: integration disabled.');

            return self::SUCCESS;
        }

        $dispatched = 0;

        ProblemReport::query()
            ->whereNull('notion_id')
            ->chunkById(
                $this->chunkSize(),
                function (Collection $batch) use (&$dispatched): void {
                    foreach ($batch as $report) {
                        SyncProblemReportToNotion::dispatch(
                            $report->id,
                        )->onConnection('redis');

                        $dispatched++;
                    }
                },
            );

        Log::info('Problem report Notion sync recovery scheduled', [
            'dispatched' => $dispatched,
        ]);

        $this->line(
            sprintf(
                'Problem report Notion sync recovery scheduled: %d report%s dispatched.',
                $dispatched,
                $dispatched === 1
                    ? ''
                    : 's',
            ),
        );

        return self::SUCCESS;
    }

    private function chunkSize(): int
    {
        return max(
            1,
            (int) $this->option('chunk'),
        );
    }
}
