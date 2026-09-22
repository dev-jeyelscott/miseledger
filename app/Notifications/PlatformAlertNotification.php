<?php

namespace App\Notifications;

use App\Models\PlatformAlert;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Symfony\Component\Mime\Email;

/**
 * A concise, safe platform-owner alert email (POC-V9.3). This carries only
 * severity, title, summary, and an exact deep link: never secrets, raw
 * stack traces, payment identifiers, or job payloads.
 */
final class PlatformAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly PlatformAlert $alert,
        public readonly string $eventKind,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $messageId = sprintf(
            'platform-alert.%d.%s.%s@%s',
            $this->alert->getKey(),
            $this->alert->severity->value,
            $notifiable->getKey(),
            parse_url(config('app.url'), PHP_URL_HOST) ?: 'miseledger.app',
        );

        $verb = $this->eventKind === 'escalated' ? 'escalated to' : 'opened at';

        return (new MailMessage)
            ->subject("[MiseLedger] {$this->alert->severity->value} alert: {$this->alert->title}")
            ->line("A platform alert has {$verb} ".strtoupper($this->alert->severity->value).' severity.')
            ->line('**'.$this->alert->title.'**')
            ->line($this->alert->summary)
            ->action('View in Platform Console', $this->alert->targetUrl())
            ->metadata('idempotency-key', $messageId)
            ->withSymfonyMessage(function (Email $message) use ($messageId): void {
                $message->getHeaders()->addIdHeader('Message-Id', $messageId);
            });
    }
}
