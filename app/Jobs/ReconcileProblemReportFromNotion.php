<?php

namespace App\Jobs;

use App\Enums\ProblemReportStatus;
use App\Models\ProblemReport;
use App\Support\Notion\NotionConfig;
use App\Support\Notion\NotionRequestException;
use App\Support\Notion\NotionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ReconcileProblemReportFromNotion implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 180];

    public int $timeout = 60;

    public bool $failOnTimeout = false;

    public int $uniqueFor = 900;

    /**
     * Create a reconciliation job keyed by the committed local problem report identifier.
     */
    public function __construct(
        public readonly int $reportId,
    ) {}

    /**
     * Return the stable per-report uniqueness key.
     */
    public function uniqueId(): string
    {
        return (string) $this->reportId;
    }

    /**
     * Prevent concurrent execution of duplicate jobs that may already exist in the queue.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("reconcile-problem-report-from-notion:{$this->reportId}"))
                ->dontRelease()
                ->expireAfter(90),
        ];
    }

    /**
     * Reconcile one report's status from Notion while preserving failure semantics.
     */
    public function handle(): void
    {
        $config = NotionConfig::fromConfig((array) config('services.notion'));

        if (! $config->enabled) {
            return;
        }

        if (! $config->isValid()) {
            $this->fail(NotionRequestException::permanent(
                operation: 'configuration',
                reason: 'invalid_configuration',
            ));

            return;
        }

        $report = ProblemReport::query()
            ->find($this->reportId);

        if ($report === null || $report->notion_id === null) {
            return;
        }

        if ($report->status === ProblemReportStatus::Closed) {
            return;
        }

        try {
            $this->reconcile($report, new NotionService($config));
        } catch (NotionRequestException $exception) {
            if ($exception->isTransient) {
                throw $exception;
            }

            $this->recordError($report, $exception);
            $this->fail($exception);
        }
    }

    /**
     * Record secret-safe diagnostics when the queued job finally fails.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('Problem report Notion reconciliation failed', [
            'problem_report_id' => $this->reportId,
            'exception_class' => $exception === null ? null : $exception::class,
            'notion_operation' => $exception instanceof NotionRequestException
                ? $exception->operation
                : null,
            'notion_http_status' => $exception instanceof NotionRequestException
                ? $exception->status
                : null,
            'notion_failure_reason' => $exception instanceof NotionRequestException
                ? $exception->reason
                : null,
            'notion_transient' => $exception instanceof NotionRequestException
                ? $exception->isTransient
                : null,
        ]);
    }

    /**
     * Fetch remote status and reconcile local state, preserving on failure.
     */
    private function reconcile(ProblemReport $report, NotionService $service): void
    {
        $page = $service->retrievePageById($report->notion_id);

        $remoteStatus = data_get($page, 'properties.Status.status.name');

        if (! is_string($remoteStatus) || $remoteStatus === '') {
            $this->preserveLocalState($report);

            return;
        }

        $mapped = $this->mapRemoteStatusToLocal($remoteStatus);

        if ($mapped === null) {
            $this->preserveLocalState($report);

            return;
        }

        $this->persistReconciliation($report, $remoteStatus, $mapped);
        $this->clearReconciliationError($report);
    }

    /**
     * Map exact remote Notion status to local five-state lifecycle.
     */
    private function mapRemoteStatusToLocal(?string $remoteStatus): ?ProblemReportStatus
    {
        return match ($remoteStatus) {
            'Submitted' => ProblemReportStatus::Submitted,
            'In Review' => ProblemReportStatus::InReview,
            'Ready' => ProblemReportStatus::InReview,
            'In Progress' => ProblemReportStatus::InProgress,
            'Blocked', 'Failed', 'Skipped' => ProblemReportStatus::InReview,
            'Done' => ProblemReportStatus::Resolved,
            'Closed' => ProblemReportStatus::Closed,
            default => null,
        };
    }

    /**
     * Persist reconciled remote status, mapped status, and successful check timestamp.
     */
    private function persistReconciliation(
        ProblemReport $report,
        string $remoteStatus,
        ProblemReportStatus $mapped,
    ): void {
        $report->forceFill([
            'notion_status' => $remoteStatus,
            'status' => $mapped,
            'notion_last_checked_at' => now(),
        ])->save();
    }

    /**
     * Preserve local status and log the safe integration diagnostic.
     */
    private function preserveLocalState(ProblemReport $report): void
    {
        $report->forceFill([
            'notion_last_checked_at' => now(),
        ])->save();

        Log::warning('Unknown Notion status for problem report', [
            'problem_report_id' => $report->id,
            'notion_id' => $report->notion_id,
            'notion_status' => $report->notion_status,
            'local_status' => $report->status->value,
        ]);
    }

    /**
     * Record safe, non-secret integration diagnostic from exception.
     */
    private function recordError(ProblemReport $report, NotionRequestException $exception): void
    {
        $error = match ($exception->status) {
            404 => 'not_found',
            401, 403 => 'auth_failure',
            429 => 'rate_limited',
            500, 502, 503 => 'service_error',
            default => 'request_failed',
        };

        $this->recordReconciliationError($report, $error);
    }

    /**
     * Record safe, non-secret integration diagnostic when a read fails.
     */
    private function recordReconciliationError(ProblemReport $report, string $error): void
    {
        $report->forceFill([
            'notion_check_error' => $error,
            'notion_last_checked_at' => now(),
        ])->save();
    }

    /**
     * Clear integration error diagnostic after a successful read.
     */
    private function clearReconciliationError(ProblemReport $report): void
    {
        if ($report->notion_check_error === null) {
            return;
        }

        $report->forceFill([
            'notion_check_error' => null,
        ])->save();
    }
}
