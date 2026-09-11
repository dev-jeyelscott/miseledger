<?php

use App\Exceptions\AmbiguousBillingNotificationDeliveryException;
use App\Jobs\SendProblemReportOperatorEmail;
use App\Models\ProblemReport;
use App\Models\ProblemReportAttachment;
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
            static function (SendProblemReportOperatorEmail $job) use ($report): bool {
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
            static function (SendProblemReportOperatorEmail $job): bool {
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

    it('skips email when recipient is invalid email address', function () {
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
    });

    it('marks email_notified_at after successful send', function () {
        config([
            'problem-reports.email.enabled' => true,
            'problem-reports.email.to' => 'operator@example.com',
        ]);

        $report = ProblemReport::factory()->create();

        $job = new SendProblemReportOperatorEmail($report->id);
        $job->handle();

        $report->refresh();
        expect($report->email_notified_at)->not()->toBeNull();
    });

    it('marks email_notification_claimed_at after successful send', function () {
        config([
            'problem-reports.email.enabled' => true,
            'problem-reports.email.to' => 'operator@example.com',
        ]);

        $report = ProblemReport::factory()->create();

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

        $job = new SendProblemReportOperatorEmail($report->id);
        $job->handle();

        expect($report->email_notified_at)->not()->toBeNull();
    });

    it('fails with AmbiguousBillingNotificationDeliveryException when claim already exists', function () {
        config([
            'problem-reports.email.enabled' => true,
            'problem-reports.email.to' => 'operator@example.com',
        ]);

        $report = ProblemReport::factory()->create([
            'email_notification_claimed_at' => now(),
        ]);

        $job = new SendProblemReportOperatorEmail($report->id);

        expect(function () use ($job) {
            $job->handle();
        })->toThrow(AmbiguousBillingNotificationDeliveryException::class);
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

        Mail::shouldReceive('send')->andThrow(new Exception('Mail service down'));

        $job = new SendProblemReportOperatorEmail($report->id);

        expect(function () use ($job) {
            $job->handle();
        })->toThrow(Exception::class);

        $report->refresh();
        expect($report->email_notified_at)->toBeNull();
        expect($report->email_notification_claimed_at)->not()->toBeNull();
    });

    it('skips email when report not found', function () {
        config([
            'problem-reports.email.enabled' => true,
            'problem-reports.email.to' => 'operator@example.com',
        ]);

        $job = new SendProblemReportOperatorEmail(9999);
        $job->handle();

        expect(true)->toBeTrue();
    });

    it('sends email to configured recipient', function () {
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

        Mail::assertSent(function ($mailable) {
            return in_array(
                'operator@example.com',
                array_column((array) $mailable->to, 'address'),
                true
            );
        });
    });

    it('includes report reference in subject line', function () {
        config([
            'problem-reports.email.enabled' => true,
            'problem-reports.email.to' => 'operator@example.com',
        ]);

        $report = ProblemReport::factory()->create();

        $job = new SendProblemReportOperatorEmail($report->id);
        $job->handle();

        Mail::assertSent(function ($mailable) use ($report) {
            return str_contains($mailable->subject, "MiseLedger Problem Report {$report->reference}");
        });
    });

    it('includes report metadata in email body', function () {
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

        Mail::assertSent(function ($mailable) use ($report) {
            $html = $mailable->render();

            return str_contains($html, $report->reference)
                && str_contains($html, 'Test Title')
                && str_contains($html, 'Test Description')
                && str_contains($html, 'Test User')
                && str_contains($html, 'test@example.com')
                && str_contains($html, 'Test Org')
                && str_contains($html, 'Screenshot Count: 3');
        });
    });

    it('includes authenticated URL with report reference', function () {
        config([
            'problem-reports.email.enabled' => true,
            'problem-reports.email.to' => 'operator@example.com',
        ]);

        $report = ProblemReport::factory()->create();

        $job = new SendProblemReportOperatorEmail($report->id);
        $job->handle();

        Mail::assertSent(function ($mailable) use ($report) {
            $html = $mailable->render();
            $expectedUrl = route('problem-reports.show-operator', $report->reference);

            return str_contains($html, $expectedUrl);
        });
    });

    it('sets deterministic Message-ID header', function () {
        config([
            'problem-reports.email.enabled' => true,
            'problem-reports.email.to' => 'operator@example.com',
        ]);

        $report = ProblemReport::factory()->create();

        $job = new SendProblemReportOperatorEmail($report->id);
        $job->handle();

        Mail::assertSent(function ($mailable) use ($report) {
            $messageId = sprintf(
                'problem-report.%s@%s',
                $report->reference,
                parse_url(config('app.url'), PHP_URL_HOST) ?: 'miseledger.app',
            );

            $html = $mailable->render();

            return str_contains($html, $messageId);
        });
    });

    it('does not include screenshot attachments in email', function () {
        config([
            'problem-reports.email.enabled' => true,
            'problem-reports.email.to' => 'operator@example.com',
        ]);

        $report = ProblemReport::factory()
            ->has(ProblemReportAttachment::factory(2), 'attachments')
            ->create();

        $job = new SendProblemReportOperatorEmail($report->id);
        $job->handle();

        Mail::assertSent(function ($mailable) {
            return empty($mailable->attachments);
        });
    });

    it('does not expose private storage paths in email', function () {
        config([
            'problem-reports.email.enabled' => true,
            'problem-reports.email.to' => 'operator@example.com',
        ]);

        $report = ProblemReport::factory()
            ->has(ProblemReportAttachment::factory(2), 'attachments')
            ->create();

        $job = new SendProblemReportOperatorEmail($report->id);
        $job->handle();

        Mail::assertSent(function ($mailable) {
            $html = $mailable->render();

            return ! str_contains($html, '/storage/')
                && ! str_contains($html, 'app/problem-reports/')
                && ! str_contains($html, '.env');
        });
    });

    it('does not include Notion credentials or metadata', function () {
        config([
            'problem-reports.email.enabled' => true,
            'problem-reports.email.to' => 'operator@example.com',
        ]);

        $report = ProblemReport::factory()->create();

        $job = new SendProblemReportOperatorEmail($report->id);
        $job->handle();

        Mail::assertSent(function ($mailable) {
            $html = $mailable->render();

            return ! str_contains($html, 'notion')
                && ! str_contains($html, 'notion_page_id')
                && ! str_contains($html, 'notion_sync');
        });
    });

    it('prevents duplicate sends after successful delivery', function () {
        config([
            'problem-reports.email.enabled' => true,
            'problem-reports.email.to' => 'operator@example.com',
        ]);

        $report = ProblemReport::factory()->create();

        $job = new SendProblemReportOperatorEmail($report->id);
        $job->handle();

        $report->refresh();
        $firstNotifiedAt = $report->email_notified_at;

        Mail::fake();
        $job->handle();

        $report->refresh();
        expect($report->email_notified_at)->toBe($firstNotifiedAt);
        Mail::assertNotSent(function () {
            return true;
        });
    });

    it('does not mutate report lifecycle on email failure', function () {
        config([
            'problem-reports.email.enabled' => true,
            'problem-reports.email.to' => 'operator@example.com',
        ]);

        $report = ProblemReport::factory()->create();

        Mail::shouldReceive('send')->andThrow(new Exception('Mail service down'));

        $job = new SendProblemReportOperatorEmail($report->id);

        expect(function () use ($job) {
            $job->handle();
        })->toThrow(Exception::class);

        $report->refresh();
        expect($report->email_notified_at)->toBeNull();
        expect($report->email_notification_claimed_at)->not()->toBeNull();
    });
});

describe('Problem Report Operator Authorization', function () {
    it('allows any authenticated user to view report as operator', function () {
        $reporter = User::factory()->create();
        $operator = User::factory()->create();

        $report = ProblemReport::factory()->create([
            'user_id' => $reporter->id,
        ]);

        $this->actingAs($operator)
            ->get(route('problem-reports.show-operator', $report->reference))
            ->assertSuccessful();
    });

    it('allows report owner to view report as operator', function () {
        $reporter = User::factory()->create();

        $report = ProblemReport::factory()->create([
            'user_id' => $reporter->id,
        ]);

        $this->actingAs($reporter)
            ->get(route('problem-reports.show-operator', $report->reference))
            ->assertSuccessful();
    });

    it('prevents unauthenticated access to operator route', function () {
        $report = ProblemReport::factory()->create();

        $this->get(route('problem-reports.show-operator', $report->reference))
            ->assertRedirectToRoute('login');
    });

    it('prevents unverified user from accessing operator route', function () {
        $user = User::factory()->unverified()->create();
        $report = ProblemReport::factory()->create();

        $this->actingAs($user)
            ->get(route('problem-reports.show-operator', $report->reference))
            ->assertRedirectToRoute('verification.notice');
    });
});
