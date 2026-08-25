<?php

namespace App\Jobs;

use App\Enums\BillingPaymentStatus;
use App\Enums\OrganizationPermission;
use App\Exceptions\AmbiguousBillingNotificationDeliveryException;
use App\Models\BillingPayment;
use App\Notifications\ManualRenewalPaymentReceiptNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

final class SendManualRenewalPaymentReceipt implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    public int $uniqueFor = 3600;

    public function __construct(public readonly int $billingPaymentId) {}

    public function handle(): void
    {
        $payment = DB::transaction(function (): ?BillingPayment {
            $payment = BillingPayment::query()
                ->with(['organization', 'billingInvoice'])
                ->lockForUpdate()
                ->whereKey($this->billingPaymentId)
                ->first();

            if ($payment === null || $payment->status !== BillingPaymentStatus::Paid || $payment->receipt_notification_dispatched_at !== null) {
                return null;
            }

            if ($payment->receipt_notification_claimed_at !== null) {
                throw new AmbiguousBillingNotificationDeliveryException((string) $payment->external_payment_id);
            }

            $payment->update(['receipt_notification_claimed_at' => now()]);

            return $payment;
        });

        if ($payment === null) {
            return;
        }

        $recipients = $payment->organization->memberships()
            ->with('user')
            ->get()
            ->filter(fn ($membership): bool => $membership->role->allows(OrganizationPermission::BillingManage))
            ->pluck('user')
            ->filter();

        if ($recipients->isNotEmpty()) {
            Notification::sendNow($recipients, new ManualRenewalPaymentReceiptNotification($payment));
        }

        $payment->update(['receipt_notification_dispatched_at' => now()]);
    }

    public function uniqueId(): string
    {
        return (string) $this->billingPaymentId;
    }
}
