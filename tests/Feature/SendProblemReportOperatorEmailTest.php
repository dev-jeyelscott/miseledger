<?php

use App\Exceptions\AmbiguousProblemReportEmailDeliveryException;
use App\Jobs\SendProblemReportOperatorEmail;
use App\Models\ProblemReport;
use App\Models\ProblemReportAttachment;
use App\Models\User;
use App\Notifications\ProblemReportOperatorNotification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\Mime\Email;

/**
 * Build the mail representation for a captured problem-report operator notification.
 */
function problemReportOperatorMailMessage(
    ProblemReportOperatorNotification $notification,
    object $notifiable,
): MailMessage {
    return $notification->toMail($notifiable);
}

/**
 * Apply the notification's Symfony mail callbacks to a test email instance.
 */
function problemReportOperatorSymfonyEmail(MailMessage $mailMessage): Email
{
    $email = new Email;

    foreach ($mailMessage->callbacks as $callback) {
        $callback($email);
    }

    return $email;
}

describe('Send Problem Report Operator Email', function () {
    beforeEach(function () {
        Queue::fake();
    });

    it('does not dispatch email job when config is disabled', function () {
        config(['problem-reports.email.enabled' => false]);

        ProblemReport::factory()->create();

        Queue::assertNotPushed(SendProblemReportOperatorEmail::class);
    });

    it('dispatches email job after report commit when enabled', function () {
        config(['problem-reports.email.enabled' => true]);

        $report = ProblemReport::factory()->create();

        Queue::assertPushed(
            SendProblemReportOperatorEmail::class,
            static function (SendProblemReportOperatorEmail $job) use ($report): bool {
                return $job->reportId === $report->id
                    && $job->afterCommit === true;
            },
        );
    });

    it('marks the job for after-commit execution', function () {
        config(['problem-reports.email.enabled' => true]);

        ProblemReport::factory()->create();

        Queue::assertPushed(
            SendProblemReportOperatorEmail::class,
            static function (SendProblemReportOperatorEmail $job): bool {
                return $job->afterCommit === true;
            },
        );
    });

    it('skips email when recipient is not configured', function () {
        Notification::fake();

        config([
            'problem-reports.email.enabled' => true,
            'problem-reports.email.to' => null,
        ]);

        $report = ProblemReport::factory()->create();
        $job = new SendProblemReportOperatorEmail($report->id);

        $job->handle();

        $report->refresh();
        expect($report->email_notified_at)->toBeNull();
        expect($report->email_notification_claimed_at)->toBeNull();
        Notification::assertNothingSent();
    });

    it('skips email when recipient is empty string', function () {
        Notification::fake();

        config([
            'problem-reports.email.enabled' => true,
            'problem-reports.email.to' => '',
        ]);

        $report = ProblemReport::factory()->create();
        $job = new SendProblemReportOperatorEmail($report->id);

        $job->handle();

        $report->refresh();
        expect($report->email_notified_at)->toBeNull();
        expect($report->email_notification_claimed_at)->toBeNull();
        Notification::assertNothingSent();
    });

    it('skips email when recipient is invalid email address', function () {
        Notification::fake();

        config([
            'problem-reports.email.enabled' => true,
            'problem-reports.email.to' => 'not-an-email',
        ]);

        $report = ProblemReport::factory()->create();
        $job = new SendProblemReportOperatorEmail($report->id);

        $job->handle();

        $report->refresh();
        expect($report->email_notified_at)->toBeNull();
        expect($report->email_notification_claimed_at)->toBeNull();
        Notification::assertNothingSent();
    });

    it('marks email_notified_at after successful send', function () {
        Notification::fake();

        config([
            'problem-reports.email.enabled' => true,
            'problem-reports.email.to' => 'operator@example.com',
        ]);

        $report = ProblemReport::factory()->create();

        $job = new SendProblemReportOperatorEmail($report->id);
        $job->handle();

        $report->refresh();
        expect($report->email_notified_at)->not()->toBeNull();
        Notification::assertSentOnDemandOnce(
            ProblemReportOperatorNotification::class,
        );
    });

    it('marks both email_notification_claimed_at and email_notified_at after successful send', function () {
        Notification::fake();

        config([
            'problem-reports.email.enabled' => true,
            'problem-reports.email.to' => 'operator@example.com',
        ]);

        $report = ProblemReport::factory()->create();

        $job = new SendProblemReportOperatorEmail($report->id);
        $job->handle();

        $report->refresh();
        expect($report->email_notification_claimed_at)->not()->toBeNull();
        expect($report->email_notified_at)->not()->toBeNull();
    });

    it('skips already notified reports', function () {
        Notification::fake();

        config([
            'problem-reports.email.enabled' => true,
            'problem-reports.email.to' => 'operator@example.com',
        ]);

        $report = ProblemReport::factory()->create([
            'email_notified_at' => now(),
        ]);

        $job = new SendProblemReportOperatorEmail($report->id);
        $job->handle();

        expect($report->email_notified_at)->not()->toBeNull();
        Notification::assertNothingSent();
    });

    it('retries mail send on transient provider failure', function () {
        config([
            'problem-reports.email.enabled' => true,
            'problem-reports.email.to' => 'operator@example.com',
        ]);

        $report = ProblemReport::factory()->create();
        $job = new SendProblemReportOperatorEmail($report->id);

        Notification::shouldReceive('send')
            ->once()
            ->andThrow(
                new Exception('Mail service temporarily down'),
            );

        expect(function () use ($job) {
            $job->handle();
        })->toThrow(Exception::class);

        $report->refresh();
        expect($report->email_notified_at)->toBeNull();
        expect($report->email_notification_claimed_at)->not()->toBeNull();

        Notification::fake();
        $job->handle();

        $report->refresh();
        expect($report->email_notified_at)->not()->toBeNull();
        Notification::assertSentOnDemandOnce(
            ProblemReportOperatorNotification::class,
        );
    });

    it('uses unique job ID for deduplication', function () {
        $report = ProblemReport::factory()->create();
        $job1 = new SendProblemReportOperatorEmail($report->id);
        $job2 = new SendProblemReportOperatorEmail($report->id);

        expect($job1->uniqueId())->toBe($job2->uniqueId());
    });

    it('has correct retry configuration', function () {
        $job = new SendProblemReportOperatorEmail(1);

        expect($job->tries)->toBe(3);
        expect($job->backoff)->toBe([60, 300]);
        expect($job->timeout)->toBe(60);
        expect($job->failOnTimeout)->toBeTrue();
        expect($job->uniqueFor)->toBe(3600);
    });

    it('retries on mail delivery failure without marking notified', function () {
        config([
            'problem-reports.email.enabled' => true,
            'problem-reports.email.to' => 'operator@example.com',
        ]);

        $report = ProblemReport::factory()->create();

        Notification::shouldReceive('send')
            ->once()
            ->andThrow(new Exception('Mail service down'));

        $job = new SendProblemReportOperatorEmail($report->id);

        expect(function () use ($job) {
            $job->handle();
        })->toThrow(Exception::class);

        $report->refresh();
        expect($report->email_notified_at)->toBeNull();
        expect($report->email_notification_claimed_at)->not()->toBeNull();
    });

    it('skips email when report not found', function () {
        Notification::fake();

        config([
            'problem-reports.email.enabled' => true,
            'problem-reports.email.to' => 'operator@example.com',
        ]);

        $job = new SendProblemReportOperatorEmail(9999);
        $job->handle();

        Notification::assertNothingSent();
    });

    it('sends email to configured recipient', function () {
        Notification::fake();

        config([
            'problem-reports.email.enabled' => true,
            'problem-reports.email.to' => 'operator@example.com',
        ]);

        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $report = ProblemReport::factory()->create([
            'user_id' => $user->id,
            'title' => 'Test Title',
            'description' => 'Test Description',
        ]);

        $job = new SendProblemReportOperatorEmail($report->id);
        $job->handle();

        Notification::assertSentOnDemand(
            ProblemReportOperatorNotification::class,
            static function (
                ProblemReportOperatorNotification $notification,
                array $channels,
                object $notifiable,
            ): bool {
                return $channels === ['mail']
                    && ($notifiable->routes['mail'] ?? null)
                        === 'operator@example.com';
            },
        );
    });

    it('includes report reference in subject line', function () {
        Notification::fake();

        config([
            'problem-reports.email.enabled' => true,
            'problem-reports.email.to' => 'operator@example.com',
        ]);

        $report = ProblemReport::factory()->create();

        $job = new SendProblemReportOperatorEmail($report->id);
        $job->handle();

        Notification::assertSentOnDemand(
            ProblemReportOperatorNotification::class,
            static function (
                ProblemReportOperatorNotification $notification,
                array $channels,
                object $notifiable,
            ) use ($report): bool {
                $mailMessage = problemReportOperatorMailMessage(
                    $notification,
                    $notifiable,
                );

                return $mailMessage->subject
                    === "MiseLedger Problem Report {$report->reference}";
            },
        );
    });

    it('includes report metadata in email body', function () {
        Notification::fake();

        config([
            'problem-reports.email.enabled' => true,
            'problem-reports.email.to' => 'operator@example.com',
        ]);

        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $report = ProblemReport::factory()
            ->has(ProblemReportAttachment::factory(3), 'attachments')
            ->create([
                'user_id' => $user->id,
                'title' => 'Test Title',
                'description' => 'Test Description',
                'organization_name_snapshot' => 'Test Org',
            ]);

        $job = new SendProblemReportOperatorEmail($report->id);
        $job->handle();

        Notification::assertSentOnDemand(
            ProblemReportOperatorNotification::class,
            static function (
                ProblemReportOperatorNotification $notification,
                array $channels,
                object $notifiable,
            ) use ($report): bool {
                $html = (string) problemReportOperatorMailMessage(
                    $notification,
                    $notifiable,
                )->render();

                $text = preg_replace(
                    '/\s+/',
                    ' ',
                    html_entity_decode(strip_tags($html)),
                ) ?? '';

                return str_contains($text, $report->reference)
                    && str_contains($text, 'Test Title')
                    && str_contains($text, 'Test Description')
                    && str_contains($text, 'Test User')
                    && str_contains($text, 'test@example.com')
                    && str_contains($text, 'Test Org')
                    && str_contains($text, 'Screenshot Count: 3');
            },
        );
    });

    it('includes authenticated URL with report reference', function () {
        Notification::fake();

        config([
            'problem-reports.email.enabled' => true,
            'problem-reports.email.to' => 'operator@example.com',
        ]);

        $report = ProblemReport::factory()->create();

        $job = new SendProblemReportOperatorEmail($report->id);
        $job->handle();

        Notification::assertSentOnDemand(
            ProblemReportOperatorNotification::class,
            static function (
                ProblemReportOperatorNotification $notification,
                array $channels,
                object $notifiable,
            ) use ($report): bool {
                $mailMessage = problemReportOperatorMailMessage(
                    $notification,
                    $notifiable,
                );

                return $mailMessage->actionUrl
                    === route(
                        'problem-reports.show-operator',
                        $report->reference,
                    );
            },
        );
    });

    it('sets deterministic Message-ID header', function () {
        Notification::fake();

        config([
            'problem-reports.email.enabled' => true,
            'problem-reports.email.to' => 'operator@example.com',
        ]);

        $report = ProblemReport::factory()->create();

        $job = new SendProblemReportOperatorEmail($report->id);
        $job->handle();

        Notification::assertSentOnDemand(
            ProblemReportOperatorNotification::class,
            static function (
                ProblemReportOperatorNotification $notification,
                array $channels,
                object $notifiable,
            ) use ($report): bool {
                $expectedMessageId = sprintf(
                    'problem-report.%s@%s',
                    $report->reference,
                    parse_url(
                        config('app.url'),
                        PHP_URL_HOST,
                    ) ?: 'miseledger.app',
                );

                $email = problemReportOperatorSymfonyEmail(
                    problemReportOperatorMailMessage(
                        $notification,
                        $notifiable,
                    ),
                );

                $messageId = $email
                    ->getHeaders()
                    ->get('Message-Id')
                    ?->getBodyAsString();

                return $messageId
                    === sprintf('<%s>', $expectedMessageId);
            },
        );
    });

    it('does not include screenshot attachments in email', function () {
        Notification::fake();

        config([
            'problem-reports.email.enabled' => true,
            'problem-reports.email.to' => 'operator@example.com',
        ]);

        $report = ProblemReport::factory()
            ->has(ProblemReportAttachment::factory(2), 'attachments')
            ->create();

        $job = new SendProblemReportOperatorEmail($report->id);
        $job->handle();

        Notification::assertSentOnDemand(
            ProblemReportOperatorNotification::class,
            static function (
                ProblemReportOperatorNotification $notification,
                array $channels,
                object $notifiable,
            ): bool {
                $mailMessage = problemReportOperatorMailMessage(
                    $notification,
                    $notifiable,
                );

                return $mailMessage->attachments === []
                    && $mailMessage->rawAttachments === [];
            },
        );
    });

    it('does not expose private storage paths in email', function () {
        Notification::fake();

        config([
            'problem-reports.email.enabled' => true,
            'problem-reports.email.to' => 'operator@example.com',
        ]);

        $report = ProblemReport::factory()
            ->has(ProblemReportAttachment::factory(2), 'attachments')
            ->create();

        $job = new SendProblemReportOperatorEmail($report->id);
        $job->handle();

        Notification::assertSentOnDemand(
            ProblemReportOperatorNotification::class,
            static function (
                ProblemReportOperatorNotification $notification,
                array $channels,
                object $notifiable,
            ): bool {
                $html = (string) problemReportOperatorMailMessage(
                    $notification,
                    $notifiable,
                )->render();

                return ! str_contains($html, '/storage/')
                    && ! str_contains($html, 'app/problem-reports/')
                    && ! str_contains($html, '.env');
            },
        );
    });

    it('does not include Notion credentials or metadata', function () {
        Notification::fake();

        config([
            'problem-reports.email.enabled' => true,
            'problem-reports.email.to' => 'operator@example.com',
        ]);

        $report = ProblemReport::factory()->create();

        $job = new SendProblemReportOperatorEmail($report->id);
        $job->handle();

        Notification::assertSentOnDemand(
            ProblemReportOperatorNotification::class,
            static function (
                ProblemReportOperatorNotification $notification,
                array $channels,
                object $notifiable,
            ): bool {
                $html = strtolower(
                    (string) problemReportOperatorMailMessage(
                        $notification,
                        $notifiable,
                    )->render(),
                );

                return ! str_contains($html, 'notion')
                    && ! str_contains($html, 'notion_page_id')
                    && ! str_contains($html, 'notion_sync');
            },
        );
    });

    it('prevents duplicate sends after successful delivery', function () {
        Notification::fake();

        config([
            'problem-reports.email.enabled' => true,
            'problem-reports.email.to' => 'operator@example.com',
        ]);

        $report = ProblemReport::factory()->create();

        $job = new SendProblemReportOperatorEmail($report->id);
        $job->handle();

        $report->refresh();
        $firstNotifiedAt = $report->email_notified_at;

        Notification::fake();
        $job->handle();

        $report->refresh();

        expect(
            $report->email_notified_at->toDateTimeString(),
        )->toBe(
            $firstNotifiedAt->toDateTimeString(),
        );

        Notification::assertNothingSent();
    });

    it('does not mutate report lifecycle on email failure', function () {
        config([
            'problem-reports.email.enabled' => true,
            'problem-reports.email.to' => 'operator@example.com',
        ]);

        $report = ProblemReport::factory()->create();

        Notification::shouldReceive('send')
            ->once()
            ->andThrow(new Exception('Mail service down'));

        $job = new SendProblemReportOperatorEmail($report->id);

        expect(function () use ($job) {
            $job->handle();
        })->toThrow(Exception::class);

        $report->refresh();
        expect($report->email_notified_at)->toBeNull();
        expect($report->email_notification_claimed_at)->not()->toBeNull();
    });

    it('refuses to redeliver when ambiguous claim exists', function () {
        Notification::fake();

        config([
            'problem-reports.email.enabled' => true,
            'problem-reports.email.to' => 'operator@example.com',
        ]);

        $report = ProblemReport::factory()->create([
            'email_notification_claimed_at' => now()->subHour(),
        ]);

        $job = new SendProblemReportOperatorEmail($report->id);

        expect(function () use ($job) {
            $job->handle();
        })->toThrow(
            AmbiguousProblemReportEmailDeliveryException::class,
        );

        $report->refresh();
        expect($report->email_notified_at)->toBeNull();
        Notification::assertNothingSent();
    });

    it('prevents concurrent execution from sending multiple emails', function () {
        Notification::fake();

        config([
            'problem-reports.email.enabled' => true,
            'problem-reports.email.to' => 'operator@example.com',
        ]);

        $report = ProblemReport::factory()->create();

        $job = new SendProblemReportOperatorEmail($report->id);
        $job->handle();

        $report->refresh();
        $firstNotifiedAt = $report->email_notified_at;

        Notification::fake();
        $job->handle();

        $report->refresh();

        expect(
            $report->email_notified_at->toDateTimeString(),
        )->toBe(
            $firstNotifiedAt->toDateTimeString(),
        );

        Notification::assertNothingSent();
    });
});

describe('Problem Report Operator Authorization', function () {
    it('prevents non-admin user from viewing report as operator', function () {
        $reporter = User::factory()->create();
        $nonAdmin = User::factory()->create();

        $report = ProblemReport::factory()->create([
            'user_id' => $reporter->id,
        ]);

        $this->actingAs($nonAdmin)
            ->get(
                route(
                    'problem-reports.show-operator',
                    $report->reference,
                ),
            )
            ->assertForbidden();
    });

    it('allows platform admin to view any report as operator', function () {
        $reporter = User::factory()->create();
        $admin = User::factory()->create();
        $admin->platformAdmin()->create();

        $report = ProblemReport::factory()->create([
            'user_id' => $reporter->id,
        ]);

        $this->actingAs($admin)
            ->get(
                route(
                    'problem-reports.show-operator',
                    $report->reference,
                ),
            )
            ->assertSuccessful();
    });

    it('allows report owner who is platform admin to view report as operator', function () {
        $reporter = User::factory()->create();
        $reporter->platformAdmin()->create();

        $report = ProblemReport::factory()->create([
            'user_id' => $reporter->id,
        ]);

        $this->actingAs($reporter)
            ->get(
                route(
                    'problem-reports.show-operator',
                    $report->reference,
                ),
            )
            ->assertSuccessful();
    });

    it('prevents unauthenticated access to operator route', function () {
        $report = ProblemReport::factory()->create();

        $this->get(
            route(
                'problem-reports.show-operator',
                $report->reference,
            ),
        )->assertRedirectToRoute('login');
    });

    it('prevents unverified user from accessing operator route', function () {
        $user = User::factory()->unverified()->create();
        $report = ProblemReport::factory()->create();

        $this->actingAs($user)
            ->get(
                route(
                    'problem-reports.show-operator',
                    $report->reference,
                ),
            )
            ->assertRedirectToRoute('verification.notice');
    });
});
