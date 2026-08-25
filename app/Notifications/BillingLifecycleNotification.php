<?php

namespace App\Notifications;

use App\Enums\BillingLifecycleEvent;
use App\Models\Organization;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BillingLifecycleNotification extends Notification
{
    /**
     * Create a notification from an organization-scoped billing lifecycle event.
     */
    public function __construct(
        public Organization $organization,
        public BillingLifecycleEvent $event,
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
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Billing update for '.$this->organization->name)
            ->line($this->event->message());
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
