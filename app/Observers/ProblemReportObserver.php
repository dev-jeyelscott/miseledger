<?php

namespace App\Observers;

use App\Jobs\SendProblemReportOperatorEmail;
use App\Jobs\SyncProblemReportToNotion;
use App\Models\ProblemReport;

final class ProblemReportObserver
{
    public function created(ProblemReport $report): void
    {
        SyncProblemReportToNotion::dispatch($report->id)
            ->afterCommit();

        if (config('problem-reports.email.enabled')) {
            SendProblemReportOperatorEmail::dispatch($report->id)
                ->afterCommit();
        }
    }
}
