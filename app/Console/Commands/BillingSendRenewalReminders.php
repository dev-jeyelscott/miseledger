<?php

namespace App\Console\Commands;

use App\Actions\Billing\CreateRenewalInvoice;
use App\Enums\BillingCollectionMethod;
use App\Enums\OrganizationPermission;
use App\Models\BillingRenewalReminder;
use App\Models\BillingSubscription;
use App\Notifications\ManualRenewalReminderNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

final class BillingSendRenewalReminders extends Command
{
    /** @var list<int> */
    private const REMINDER_DAYS = [7, 3, 1, 0];

    protected $signature = 'billing:send-renewal-reminders {--chunk=100 : Number of subscriptions to process per batch}';

    protected $description = 'Create renewal invoices and queue idempotent reminders for manual subscriptions nearing expiry.';

    public function __construct(private readonly CreateRenewalInvoice $createRenewalInvoice)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $queued = 0;

        foreach (self::REMINDER_DAYS as $daysBeforeDue) {
            $dueDate = now()->addDays($daysBeforeDue)->toDateString();

            BillingSubscription::query()
                ->with('organization')
                ->where('collection_method', BillingCollectionMethod::Manual->value)
                ->where('provider_status', 'active')
                ->whereDate('current_period_ends_at', $dueDate)
                ->chunkById($this->chunkSize(), function (Collection $subscriptions) use ($daysBeforeDue, &$queued): void {
                    foreach ($subscriptions as $subscription) {
                        if ($subscription->organization === null) {
                            continue;
                        }

                        $invoice = $this->createRenewalInvoice->handle($subscription);
                        $wasCreated = BillingRenewalReminder::query()->insertOrIgnore([
                            'billing_invoice_id' => $invoice->getKey(),
                            'days_before_due' => $daysBeforeDue,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]) === 1;

                        if (! $wasCreated) {
                            continue;
                        }

                        $recipients = $subscription->organization->memberships()
                            ->with('user')
                            ->get()
                            ->filter(fn ($membership): bool => $membership->role->allows(OrganizationPermission::BillingManage))
                            ->pluck('user')
                            ->filter();

                        if ($recipients->isNotEmpty()) {
                            Notification::send($recipients, new ManualRenewalReminderNotification(
                                $subscription->organization,
                                $invoice,
                                $daysBeforeDue,
                            ));
                        }

                        BillingRenewalReminder::query()
                            ->where('billing_invoice_id', $invoice->getKey())
                            ->where('days_before_due', $daysBeforeDue)
                            ->update(['sent_at' => now()]);
                        $queued++;
                    }
                });
        }

        $this->line("Queued {$queued} manual renewal reminder(s).");

        return self::SUCCESS;
    }

    private function chunkSize(): int
    {
        return max(1, (int) $this->option('chunk'));
    }
}
