<?php

namespace App\Actions\Platform\Alerts;

use App\Jobs\SendPlatformAlertEmail;
use App\Models\PlatformAlert;
use App\Models\PlatformAlertDelivery;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Queues one alert email per current platform administrator (POC-V9.3).
 * Recipients are resolved fresh from persisted platform-admin authority on
 * every call, so a revoked administrator never receives a future alert.
 * The unique `dedupe_key` on `platform_alert_deliveries` is the actual
 * idempotency mechanism: a duplicate insert for the same alert, severity,
 * and recipient is silently skipped, which is what prevents a routine
 * evaluator rerun from resending an identical email.
 */
final class NotifyPlatformAdminsOfAlert
{
    public function handle(PlatformAlert $alert, string $eventKind): void
    {
        $recipients = User::query()
            ->whereHas('platformAdmin')
            ->get();

        foreach ($recipients as $recipient) {
            $dedupeKey = sprintf(
                'platform-alert:%d:%s:%d',
                $alert->getKey(),
                $alert->severity->value,
                $recipient->getKey(),
            );

            try {
                $delivery = PlatformAlertDelivery::query()->create([
                    'platform_alert_id' => $alert->getKey(),
                    'recipient_user_id' => $recipient->getKey(),
                    'channel' => 'mail',
                    'event_kind' => $eventKind,
                    'dedupe_key' => $dedupeKey,
                ]);
            } catch (UniqueConstraintViolationException) {
                // Already claimed by this or a prior evaluator run for the
                // same alert/severity/recipient: no resend.
                continue;
            }

            SendPlatformAlertEmail::dispatch($delivery->getKey());
        }
    }
}
