<?php

namespace App\Notifications;

use App\Models\BillingPayment;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Number;

final class ManualRenewalPaymentReceiptNotification extends Notification
{
    public function __construct(public readonly BillingPayment $payment) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $invoice = $this->payment->billingInvoice;
        $organization = $this->payment->organization;
        $amount = Number::currency($this->payment->amount / 100, $this->payment->currency, locale: 'en_PH');
        $period = $invoice->period_starts_at->toFormattedDateString().' → '.$invoice->period_ends_at->toFormattedDateString();

        return (new MailMessage)
            ->subject('Payment received for '.$organization->name)
            ->line('Your subscription renewal payment was received.')
            ->line('Plan: '.$invoice->plan_code)
            ->line('Amount: '.$amount)
            ->line('New access period: '.$period)
            ->action('Review billing', route('organizations.billing.show', $organization));
    }

    /** @return array<string, int|string> */
    public function toArray(object $notifiable): array
    {
        return [
            'organization_id' => $this->payment->organization_id,
            'billing_invoice_id' => $this->payment->billing_invoice_id,
            'billing_payment_id' => $this->payment->getKey(),
        ];
    }
}
