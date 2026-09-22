<?php

namespace App\Actions\Platform\Alerts;

use App\Enums\PlatformAlertSeverity;
use App\Enums\PlatformAlertState;
use App\Enums\PlatformAlertType;
use App\Models\PlatformAlert;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * The single write path that opens or updates a platform-owner alert from
 * one authoritative evaluator observation (POC-V9.1, POC-V9.2). Dedupe is
 * enforced by the database itself: `platform_alerts_open_fingerprint_unique`
 * (a partial unique index on `fingerprint` where `state = 'open'`) is the
 * source of concurrency safety, not application-level locking alone, so two
 * evaluator runs racing to open the same fingerprint can never both
 * succeed. Never call this for a condition that has not actually been
 * observed: Unknown evidence must never be recorded as an alert.
 */
final class RecordPlatformAlertObservation
{
    public function __construct(
        private readonly NotifyPlatformAdminsOfAlert $notify,
    ) {}

    /**
     * @param  array<string, mixed>  $context  Safe, minimized evidence only. Never secrets, raw payloads, or stack traces.
     */
    public function handle(
        string $fingerprint,
        PlatformAlertType $type,
        string $source,
        PlatformAlertSeverity $severity,
        string $title,
        string $summary,
        array $context,
    ): PlatformAlert {
        [$alert, $event] = DB::transaction(function () use (
            $fingerprint,
            $type,
            $source,
            $severity,
            $title,
            $summary,
            $context,
        ): array {
            $existing = PlatformAlert::query()
                ->where('fingerprint', $fingerprint)
                ->where('state', PlatformAlertState::Open)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof PlatformAlert) {
                return $this->reobserve($existing, $severity, $title, $summary, $context);
            }

            try {
                $alert = PlatformAlert::query()->create([
                    'fingerprint' => $fingerprint,
                    'type' => $type,
                    'source' => $source,
                    'severity' => $severity,
                    'state' => PlatformAlertState::Open,
                    'title' => $title,
                    'summary' => $summary,
                    'context' => $context,
                    'first_seen_at' => now(),
                    'last_seen_at' => now(),
                    'occurrence_count' => 1,
                ]);

                return [$alert, 'opened'];
            } catch (UniqueConstraintViolationException) {
                // A concurrent transaction opened this fingerprint first;
                // the partial unique index guarantees exactly one open row
                // now exists, so re-select it under lock and reobserve.
                $existing = PlatformAlert::query()
                    ->where('fingerprint', $fingerprint)
                    ->where('state', PlatformAlertState::Open)
                    ->lockForUpdate()
                    ->firstOrFail();

                return $this->reobserve($existing, $severity, $title, $summary, $context);
            }
        });

        if ($event === 'opened' || $event === 'escalated') {
            $this->notify->handle($alert, $event);
        }

        return $alert;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{0: PlatformAlert, 1: string}
     */
    private function reobserve(
        PlatformAlert $alert,
        PlatformAlertSeverity $severity,
        string $title,
        string $summary,
        array $context,
    ): array {
        $escalated = $severity->isMoreSevereThan($alert->severity);

        $alert->forceFill([
            'severity' => $escalated ? $severity : $alert->severity,
            'title' => $title,
            'summary' => $summary,
            'context' => $context,
            'last_seen_at' => now(),
            'occurrence_count' => $alert->occurrence_count + 1,
        ])->save();

        return [$alert, $escalated ? 'escalated' : 'reobserved'];
    }
}
