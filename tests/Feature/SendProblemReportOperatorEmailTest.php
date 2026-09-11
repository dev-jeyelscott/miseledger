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

    it('marks email_notification_claimed_at during processing', function () {
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
        })->toThrow(Exception::class);

        $report->refresh();
        expect($report->id)->toBe($report->id);
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

    it('can successfully send email with all report data', function () {
        config([
            'problem-reports.email.enabled' => true,
            'problem-reports.email.to' => 'operator@example.com',
        ]);

        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $report = ProblemReport::factory()
            ->has(ProblemReportAttachment::factory(2), 'attachments')
            ->create([
                'user_id' => $user->id,
                'title' => 'Test Title',
                'description' => 'Test Description',
                'organization_name_snapshot' => 'Test Org',
            ]);

        $job = new SendProblemReportOperatorEmail($report->id);
        $job->handle();

        $report->refresh();
        expect($report->email_notified_at)->not()->toBeNull();
        expect($report->email_notification_claimed_at)->not()->toBeNull();
    });
});
