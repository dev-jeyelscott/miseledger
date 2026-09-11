<?php

namespace App\Console\Commands;

use App\Enums\ProblemReportStatus;
use App\Jobs\ReconcileProblemReportFromNotion;
use App\Models\ProblemReport;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

final class ProblemReportSyncFromNotion extends Command
{
    protected $signature =
        'problem-report:sync-from-notion {--chunk=100 : Number of problem reports to process per database batch}';

    protected $description =
        'Reconcile local problem report status with authoritative Notion workflow state.';

    public function handle(): int
    {
        $reconciled = 0;

        ProblemReport::query()
            ->whereNotNull('notion_id')
            ->where('status', '!=', ProblemReportStatus::Closed)
            ->chunkById(
                $this->chunkSize(),
                function (Collection $batch) use (
                    &$reconciled,
                ): void {
                    foreach ($batch as $report) {
                        ReconcileProblemReportFromNotion::dispatch(
                            $report->id,
                        );

                        $reconciled++;
                    }
                },
            );

        $skipped = ProblemReport::query()
            ->whereNotNull('notion_id')
            ->where('status', '=', ProblemReportStatus::Closed)
            ->count();

        $this->line(
            sprintf(
                'Problem report reconciliation scheduled: %d report%s dispatched, %d skipped.',
                $reconciled,
                $reconciled === 1
                    ? ''
                    : 's',
                $skipped,
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
