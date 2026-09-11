<?php

namespace App\Jobs;

use App\Exceptions\AmbiguousBillingNotificationDeliveryException;
use App\Models\ProblemReport;
use App\Notifications\ProblemReportOperatorNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final class SendProblemReportOperatorEmail implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public int $timeout = 60;

    public bool $failOnTimeout = true;

    public int $uniqueFor = 3600;

    public function __construct(
        public readonly int $reportId,
    ) {}

    public function handle(): void
    {
        if (! config('problem-reports.email.enabled')) {
            return;
        }

        $recipient = config('problem-reports.email.to');
        if (! is_string($recipient) || $recipient === '') {
            Log::error('Problem report operator email recipient not configured');

            return;
        }

        if (! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            Log::error('Problem report operator email recipient is not a valid email address', [
                'recipient' => $recipient,
            ]);

            return;
        }

        $effect = DB::transaction(function (): ?ProblemReport {
            $report = ProblemReport::query()
                ->lockForUpdate()
                ->find($this->reportId);

            if ($report === null || $report->email_notified_at !== null) {
                return null;
            }

            if ($report->email_notification_claimed_at !== null) {
                throw new AmbiguousBillingNotificationDeliveryException(
                    "problem-report:{$report->reference}"
                );
            }

            $report->forceFill(['email_notification_claimed_at' => now()])->save();

            return $report;
        });

        if ($effect === null) {
            return;
        }

        $report = $effect;

        try {
            (new AnonymousNotifiable)
                ->route('mail', $recipient)
                ->notify(new ProblemReportOperatorNotification($report));

            $report->forceFill(['email_notified_at' => now()])->save();
        } catch (Throwable $exception) {
            Log::error('Problem report operator email delivery failed', [
                'problem_report_id' => $this->reportId,
                'exception_class' => $exception::class,
            ]);

            throw $exception;
        }
    }

    public function uniqueId(): string
    {
        return (string) $this->reportId;
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Problem report operator email job failed', [
            'problem_report_id' => $this->reportId,
            'exception_class' => $exception === null ? null : $exception::class,
        ]);
    }
}
