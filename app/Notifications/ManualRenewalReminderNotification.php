<?php

namespace App\Notifications;

use App\Models\BillingInvoice;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Number;

final class ManualRenewalReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Organization $organization,
        public readonly BillingInvoice $invoice,
        public readonly int $daysBeforeDue,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(User $notifiable): MailMessage
    {
        $renewalDate = $this->invoice->period_starts_at->toFormattedDateString();
        $amount = Number::currency($this->invoice->amount / 100, $this->invoice->currency, locale: 'en_PH');

        return (new MailMessage)
            ->subject('Subscription renewal reminder for '.$this->organization->name)
            ->line($this->daysBeforeDue === 0
                ? 'Your subscription renewal is due today.'
                : "Your subscription renewal is due in {$this->daysBeforeDue} day(s).")
            ->line('Plan: '.$this->invoice->plan_code)
            ->line('Amount: '.$amount)
            ->line('Renewal date: '.$renewalDate)
            ->action('Review billing', route('organizations.billing.show', $this->organization));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'organization_id' => $this->organization->getKey(),
            'billing_invoice_id' => $this->invoice->getKey(),
            'days_before_due' => $this->daysBeforeDue,
        ];
    }
}
