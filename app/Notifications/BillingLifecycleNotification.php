<?php

namespace App\Notifications;

use App\Enums\BillingLifecycleEvent;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Symfony\Component\Mime\Email;

class BillingLifecycleNotification extends Notification
{
    /**
     * Create a notification from an organization-scoped billing lifecycle event.
     */
    public function __construct(
        public Organization $organization,
        public BillingLifecycleEvent $event,
        public string $stripeEventId,
    ) {}

    /**
     * The deterministic idempotency key for this notification's underlying Stripe
     * event, unchanged across redelivery attempts of the same event.
     */
    public function idempotencyKey(): string
    {
        return "billing-lifecycle:{$this->stripeEventId}";
    }

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
        $messageId = sprintf(
            'billing-lifecycle.%s.%s@%s',
            $this->stripeEventId,
            $notifiable->getKey(),
            parse_url(config('app.url'), PHP_URL_HOST) ?: 'miseledger.app',
        );

        return (new MailMessage)
            ->subject('Billing update for '.$this->organization->name)
            ->line($this->event->message())
            ->metadata('idempotency-key', $messageId)
            ->withSymfonyMessage(function (Email $message) use ($messageId): void {
                $message->getHeaders()->addIdHeader('Message-Id', $messageId);
            });
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
            'event' => $this->event->value,
        ];
    }
}
