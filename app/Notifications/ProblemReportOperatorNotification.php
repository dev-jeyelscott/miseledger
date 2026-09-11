<?php

namespace App\Notifications;

use App\Models\ProblemReport;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Symfony\Component\Mime\Email;

class ProblemReportOperatorNotification extends Notification
{
    public function __construct(
        public ProblemReport $report,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $messageId = sprintf(
            'problem-report.%s@%s',
            $this->report->reference,
            parse_url(config('app.url'), PHP_URL_HOST) ?: 'miseledger.app',
        );

        $reportUrl = route('problem-reports.show-operator', $this->report->reference);

        return (new MailMessage)
            ->subject("MiseLedger Problem Report {$this->report->reference}")
            ->line("User Report: {$this->report->reference}")
            ->when($this->report->title, function (MailMessage $message): void {
                $message->line('**Title:** '.$this->report->title);
            })
            ->line('**Description:** '.$this->report->description)
            ->line('**Reporter:** '.$this->report->user->name)
            ->line('**Reporter Email:** '.$this->report->user->email)
            ->when($this->report->organization_name_snapshot, function (MailMessage $message): void {
                $message->line('**Organization:** '.$this->report->organization_name_snapshot);
            })
            ->line('**Submitted:** '.$this->report->created_at->format('Y-m-d H:i:s').' UTC')
            ->line('**Screenshot Count:** '.$this->report->attachments()->count())
            ->action('View Report', $reportUrl)
            ->metadata('idempotency-key', $messageId)
            ->withSymfonyMessage(function (Email $message) use ($messageId): void {
                $message->getHeaders()->addIdHeader('Message-Id', $messageId);
            });
    }
}
