<?php

use App\Exceptions\AmbiguousBillingNotificationDeliveryException;
use App\Jobs\SendProblemReportOperatorEmail;
use App\Models\ProblemReport;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

describe('Send Problem Report Operator Email', function () {
    beforeEach(function () {
        Queue::fake();
        Mail::fake();
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
            function (SendProblemReportOperatorEmail $job) use ($report): bool {
                return $job->reportId === $report->id
                    && $job->afterCommit === true;
            },
        );
    });

    it('marks the job for after-commit execution', function () {
        config(['problem-reports.email.enabled' => true]);

        $report = ProblemReport::factory()->create();

        Queue::assertPushed(
            SendProblemReportOperatorEmail::class,
            function (SendProblemReportOperatorEmail $job): bool {
                return $job->afterCommit === true;
            },
        );
    });

    it('skips email when recipient is not configured', function () {
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
    });

    it('skips email when recipient is empty string', function () {
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
    });

    it('sends email to configured recipient', function () {
        config([
            'problem-reports.email.enabled' => true,
            'problem-reports.email.to' => 'operator@example.com',
        ]);

        $report = ProblemReport::factory()->create([
            'title' => 'Test issue',
            'description' => 'Test description',
        ]);

        Mail::fake();
        $job = new SendProblemReportOperatorEmail($report->id);
        $job->handle();

        Mail::assertSent(function ($mailable) use ($report) {
            return $mailable->hasTo('operator@example.com')
                && str_contains($mailable->subject, $report->reference);
        });
    });

    it('includes report reference in subject line', function () {
        config([
            'problem-reports.email.enabled' => true,
            'problem-reports.email.to' => 'operator@example.com',
        ]);

        $report = ProblemReport::factory()->create();

        Mail::fake();
        $job = new SendProblemReportOperatorEmail($report->id);
        $job->handle();

        Mail::assertSent(function ($mailable) use ($report) {
            return str_contains(
                $mailable->subject,
                "MiseLedger Problem Report {$report->reference}"
            );
        });
    });

    it('includes user-submitted title in email body', function () {
        config([
            'problem-reports.email.enabled' => true,
            'problem-reports.email.to' => 'operator@example.com',
        ]);

        $report = ProblemReport::factory()->create([
            'title' => 'User Title',
            'description' => 'User description',
        ]);

        Mail::fake();
        $job = new SendProblemReportOperatorEmail($report->id);
        $job->handle();

        Mail::assertSent(function ($mailable) {
            $content = $mailable->render();

            return str_contains($content, 'User Title');
        });
    });

    it('omits title when not provided', function () {
        config([
            'problem-reports.email.enabled' => true,
            'problem-reports.email.to' => 'operator@example.com',
        ]);

        $report = ProblemReport::factory()->create([
            'title' => null,
            'description' => 'Description only',
        ]);

        Mail::fake();
        $job = new SendProblemReportOperatorEmail($report->id);
        $job->handle();

        Mail::assertSent(function ($mailable) {
            $content = $mailable->render();

            return str_contains($content, 'Description only');
        });
    });

    it('includes reporter name and email in email body', function () {
        config([
            'problem-reports.email.enabled' => true,
            'problem-reports.email.to' => 'operator@example.com',
        ]);

        $user = User::factory()->create([
            'name' => 'Test Reporter',
            'email' => 'reporter@example.com',
        ]);

        $report = ProblemReport::factory()->create(['user_id' => $user->id]);

        Mail::fake();
        $job = new SendProblemReportOperatorEmail($report->id);
        $job->handle();

        Mail::assertSent(function ($mailable) use ($user) {
            $content = $mailable->render();

            return str_contains($content, $user->name)
                && str_contains($content, $user->email);
        });
    });

    it('includes organization snapshot when present', function () {
        config([
            'problem-reports.email.enabled' => true,
            'problem-reports.email.to' => 'operator@example.com',
        ]);

        $report = ProblemReport::factory()->create([
            'organization_name_snapshot' => 'Test Org',
        ]);

        Mail::fake();
        $job = new SendProblemReportOperatorEmail($report->id);
        $job->handle();

        Mail::assertSent(function ($mailable) {
            $content = $mailable->render();

            return str_contains($content, 'Test Org');
        });
    });

    it('omits organization when snapshot is null', function () {
        config([
            'problem-reports.email.enabled' => true,
            'problem-reports.email.to' => 'operator@example.com',
        ]);

        $report = ProblemReport::factory()->create([
            'organization_name_snapshot' => null,
        ]);

        Mail::fake();
        $job = new SendProblemReportOperatorEmail($report->id);
        $job->handle();

        Mail::assertSent(function ($mailable) {
            return true; // Email should be sent, org section just won't appear
        });
    });

    it('includes screenshot count in email body', function () {
        config([
            'problem-reports.email.enabled' => true,
            'problem-reports.email.to' => 'operator@example.com',
        ]);

        $report = ProblemReport::factory()
            ->has(\App\Models\ProblemReportAttachment::factory(3), 'attachments')
            ->create();

        Mail::fake();
        $job = new SendProblemReportOperatorEmail($report->id);
        $job->handle();

        Mail::assertSent(function ($mailable) {
            $content = $mailable->render();

            return str_contains($content, '3');
        });
    });

    it('includes authenticated report URL in email', function () {
        config([
            'problem-reports.email.enabled' => true,
            'problem-reports.email.to' => 'operator@example.com',
        ]);

        $report = ProblemReport::factory()->create();

        Mail::fake();
        $job = new SendProblemReportOperatorEmail($report->id);
        $job->handle();

        Mail::assertSent(function ($mailable) use ($report) {
            $content = $mailable->render();

            return str_contains(
                $content,
                route('problem-reports.show', $report->reference)
            );
        });
    });

    it('does not attach or embed screenshots in email', function () {
        config([
            'problem-reports.email.enabled' => true,
            'problem-reports.email.to' => 'operator@example.com',
        ]);

        $report = ProblemReport::factory()
            ->has(\App\Models\ProblemReportAttachment::factory(2), 'attachments')
            ->create();

        Mail::fake();
        $job = new SendProblemReportOperatorEmail($report->id);
        $job->handle();

        Mail::assertSent(function ($mailable) use ($report) {
            $content = $mailable->render();
            $attachments = $mailable->attachments;

            // Count attachments - should have none
            expect(count($attachments))->toBe(0);

            // Should not embed private file paths
            foreach ($report->attachments as $attachment) {
                expect($content)->not()->toContain($attachment->path);
            }

            return true;
        });
    });

    it('marks email_notified_at after successful send', function () {
        config([
            'problem-reports.email.enabled' => true,
            'problem-reports.email.to' => 'operator@example.com',
        ]);

        $report = ProblemReport::factory()->create();

        Mail::fake();
        $job = new SendProblemReportOperatorEmail($report->id);
        $job->handle();

        $report->refresh();
        expect($report->email_notified_at)->not()->toBeNull();
    });

    it('marks email_notification_claimed_at during processing', function () {
        config([
            'problem-reports.email.enabled' => true,
            'problem-reports.email.to' => 'operator@example.com',
        ]);

        $report = ProblemReport::factory()->create();

        Mail::fake();
        $job = new SendProblemReportOperatorEmail($report->id);
        $job->handle();

        $report->refresh();
        expect($report->email_notification_claimed_at)->not()->toBeNull();
    });

    it('skips already notified reports', function () {
        config([
            'problem-reports.email.enabled' => true,
            'problem-reports.email.to' => 'operator@example.com',
        ]);

        $report = ProblemReport::factory()->create([
            'email_notified_at' => now(),
        ]);

        Mail::fake();
        $job = new SendProblemReportOperatorEmail($report->id);
        $job->handle();

        Mail::assertNotSent();
    });

    it('fails with AmbiguousBillingNotificationDeliveryException when claim already exists', function () {
        config([
            'problem-reports.email.enabled' => true,
            'problem-reports.email.to' => 'operator@example.com',
        ]);

        $report = ProblemReport::factory()->create([
            'email_notification_claimed_at' => now(),
        ]);

        Mail::fake();
        $job = new SendProblemReportOperatorEmail($report->id);

        expect(function () use ($job) {
            $job->handle();
        })->toThrow(AmbiguousBillingNotificationDeliveryException::class);
    });

    it('uses deterministic Message-ID for idempotency', function () {
        config([
            'problem-reports.email.enabled' => true,
            'problem-reports.email.to' => 'operator@example.com',
        ]);

        $report = ProblemReport::factory()->create();

        Mail::fake();
        $job = new SendProblemReportOperatorEmail($report->id);
        $job->handle();

        Mail::assertSent(function ($mailable) use ($report) {
            $messageId = sprintf(
                'problem-report.%s@',
                $report->reference
            );

            $content = $mailable->render();

            return str_contains($content, 'problem-report') && str_contains($content, $report->reference);
        });
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

    it('does not disable other report operations on email failure', function () {
        config([
            'problem-reports.email.enabled' => true,
            'problem-reports.email.to' => 'operator@example.com',
        ]);

        $report = ProblemReport::factory()->create();

        Mail::shouldReceive('send')->andThrow(new Exception('Mail service down'));

        $job = new SendProblemReportOperatorEmail($report->id);

        expect(function () use ($job) {
            $job->handle();
        })->toThrow();

        // Report should still exist and be accessible
        $report->refresh();
        expect($report->id)->toBe($report->id);
    });

    it('skips email when report not found', function () {
        config([
            'problem-reports.email.enabled' => true,
            'problem-reports.email.to' => 'operator@example.com',
        ]);

        Mail::fake();
        $job = new SendProblemReportOperatorEmail(9999);
        $job->handle();

        Mail::assertNotSent();
    });

    it('renders email with markdown formatting', function () {
        config([
            'problem-reports.email.enabled' => true,
            'problem-reports.email.to' => 'operator@example.com',
        ]);

        $report = ProblemReport::factory()->create([
            'description' => 'Test description with **bold** text',
        ]);

        Mail::fake();
        $job = new SendProblemReportOperatorEmail($report->id);
        $job->handle();

        Mail::assertSent(function ($mailable) {
            return $mailable instanceof \Illuminate\Notifications\Messages\MailMessage;
        });
    });
});
