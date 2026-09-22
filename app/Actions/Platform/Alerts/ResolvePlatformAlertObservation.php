<?php

namespace App\Actions\Platform\Alerts;

use App\Enums\PlatformAlertState;
use App\Models\PlatformAlert;
use Illuminate\Support\Facades\DB;

/**
 * Closes an open platform-owner alert from authoritative evaluator-observed
 * recovery evidence only (POC-V9.6). This is never exposed as an operator
 * control: only an evaluator that has itself re-checked the source
 * condition and found it recovered may call this. Resolved history is
 * retained indefinitely; nothing is deleted.
 */
final class ResolvePlatformAlertObservation
{
    /** Idempotent no-op when no open alert exists for the fingerprint. */
    public function handle(string $fingerprint): void
    {
        DB::transaction(function () use ($fingerprint): void {
            $alert = PlatformAlert::query()
                ->where('fingerprint', $fingerprint)
                ->where('state', PlatformAlertState::Open)
                ->lockForUpdate()
                ->first();

            if (! $alert instanceof PlatformAlert) {
                return;
            }

            $alert->forceFill([
                'state' => PlatformAlertState::Resolved,
                'resolved_at' => now(),
            ])->save();
        });
    }
}
