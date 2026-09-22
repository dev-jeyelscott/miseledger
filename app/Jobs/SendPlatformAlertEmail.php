<?php

namespace App\Jobs;

use App\Models\PlatformAlertDelivery;
use App\Notifications\PlatformAlertNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Delivers exactly one queued platform-alert email for one already-claimed
 * `platform_alert_deliveries` row (POC-V9.3). The row's unique dedupe key
 * is what prevents duplicate claims from ever existing; this job only
 * needs to guard against redelivering after its own success.
 */
final class SendPlatformAlertEmail implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    public int $timeout = 60;

    public bool $failOnTimeout = true;

    public int $uniqueFor = 3600;

    public function __construct(
        public readonly int $platformAlertDeliveryId,
    ) {}

    public function handle(): void
    {
        $delivery = PlatformAlertDelivery::query()
            ->with(['alert', 'recipient'])
            ->find($this->platformAlertDeliveryId);

        if ($delivery === null || $delivery->sent_at !== null) {
            return;
        }

        try {
            $delivery->recipient->notify(
                new PlatformAlertNotification($delivery->alert, $delivery->event_kind),
            );

            $delivery->forceFill(['sent_at' => now()])->save();
        } catch (Throwable $exception) {
            Log::error('Platform alert email delivery failed', [
                'platform_alert_delivery_id' => $this->platformAlertDeliveryId,
                'exception_class' => $exception::class,
            ]);

            throw $exception;
        }
    }

    public function uniqueId(): string
    {
        return (string) $this->platformAlertDeliveryId;
    }

    public function failed(?Throwable $exception): void
    {
        PlatformAlertDelivery::query()
            ->whereKey($this->platformAlertDeliveryId)
            ->whereNull('sent_at')
            ->update(['failed_at' => now()]);

        Log::error('Platform alert email job failed', [
            'platform_alert_delivery_id' => $this->platformAlertDeliveryId,
            'exception_class' => $exception === null ? null : $exception::class,
        ]);
    }
}
