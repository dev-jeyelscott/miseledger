<?php

namespace App\Console\Commands;

use App\Actions\Platform\Alerts\Evaluators\EvaluateDatabaseHealthAlert;
use App\Actions\Platform\Alerts\Evaluators\EvaluateFailedPaymentAlerts;
use App\Actions\Platform\Alerts\Evaluators\EvaluateMigrationHealthAlert;
use App\Actions\Platform\Alerts\Evaluators\EvaluateProblemReportAttentionAlert;
use App\Actions\Platform\Alerts\Evaluators\EvaluateQueueHealthAlert;
use App\Actions\Platform\Alerts\Evaluators\EvaluateSlowQueryAlert;
use App\Actions\Platform\Alerts\Evaluators\EvaluateTableGrowthAlert;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Runs every bounded platform-alert evaluator (POC-V9.2). Each evaluator is
 * isolated in its own try/catch: one evaluator's unexpected failure must
 * never prevent the others from running, and must never itself fabricate
 * an alert about a condition it could not actually observe.
 */
final class EvaluatePlatformAlerts extends Command
{
    protected $signature = 'platform-alerts:evaluate';

    protected $description = 'Evaluate authoritative platform conditions and record/resolve persistent owner alerts.';

    public function handle(
        EvaluateFailedPaymentAlerts $failedPayments,
        EvaluateQueueHealthAlert $queueHealth,
        EvaluateSlowQueryAlert $slowQueries,
        EvaluateDatabaseHealthAlert $databaseHealth,
        EvaluateMigrationHealthAlert $migrationHealth,
        EvaluateTableGrowthAlert $tableGrowth,
        EvaluateProblemReportAttentionAlert $problemReportAttention,
    ): int {
        $evaluators = [
            'billing-failed-payments' => $failedPayments,
            'queue-health' => $queueHealth,
            'slow-queries' => $slowQueries,
            'database-health' => $databaseHealth,
            'migration-health' => $migrationHealth,
            'table-growth' => $tableGrowth,
            'problem-report-attention' => $problemReportAttention,
        ];

        foreach ($evaluators as $name => $evaluator) {
            try {
                $evaluator->handle();
            } catch (Throwable $exception) {
                Log::error('Platform alert evaluator failed', [
                    'evaluator' => $name,
                    'exception_class' => $exception::class,
                ]);
            }
        }

        $this->info('Platform alert evaluation complete.');

        return self::SUCCESS;
    }
}
