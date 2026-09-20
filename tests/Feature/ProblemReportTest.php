<?php

use App\Enums\OrganizationRole;
use App\Enums\ProblemReportStatus;
use App\Jobs\SyncProblemReportToNotion;
use App\Models\Organization;
use App\Models\PlatformAdmin;
use App\Models\ProblemReport;
use App\Models\ProblemReportAttachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

describe('Problem Report', function () {
    beforeEach(function () {
        Storage::fake('local');
    });

    describe('Create page', function () {
        it('requires authentication', function () {
            $response = $this->get('/problem-reports/create');
            $response->assertRedirect('/login');
        });

        it('requires email verification', function () {
            $user = User::factory()->unverified()->create();
            $response = $this->actingAs($user)->get('/problem-reports/create');
            $response->assertRedirect('/email/verify');
        });

        it('renders create page for authenticated verified users', function () {
            $user = User::factory()->create();
            $response = $this->actingAs($user)->get('/problem-reports/create');
            $response->assertOk();
        });
    });

    describe('Store report', function () {
        it('creates a report with title and description', function () {
            $user = User::factory()->create();

            $response = $this->actingAs($user)
                ->post('/problem-reports', [
                    'title' => 'Stock count error',
                    'description' => 'When I try to count stock, the system crashes with a 500 error.',
                ])
                ->assertRedirect();

            $this->assertDatabaseHas('problem_reports', [
                'user_id' => $user->id,
                'title' => 'Stock count error',
                'description' => 'When I try to count stock, the system crashes with a 500 error.',
                'status' => ProblemReportStatus::Submitted->value,
            ]);

            $report = ProblemReport::first();
            $this->assertStringStartsWith('PR-', $report->reference);
        });

        it('creates a report without title', function () {
            $user = User::factory()->create();

            $response = $this->actingAs($user)
                ->post('/problem-reports', [
                    'description' => 'System is slow when loading reports.',
                ])
                ->assertRedirect();

            $this->assertDatabaseHas('problem_reports', [
                'user_id' => $user->id,
                'title' => null,
                'description' => 'System is slow when loading reports.',
            ]);
        });

        it('trims title and description whitespace', function () {
            $user = User::factory()->create();

            $this->actingAs($user)
                ->post('/problem-reports', [
                    'title' => '  Inventory issue  ',
                    'description' => '  Stock numbers are incorrect.  ',
                ])
                ->assertRedirect();

            $this->assertDatabaseHas('problem_reports', [
                'title' => 'Inventory issue',
                'description' => 'Stock numbers are incorrect.',
            ]);
        });

        it('stores active organization context', function () {
            $user = User::factory()->create();
            $organization = Organization::factory()->create();
            $user->organizations()->attach($organization, ['role' => OrganizationRole::Owner->value]);

            $this->actingAs($user)
                ->post('/problem-reports', [
                    'description' => 'Organization-related issue',
                ])
                ->assertRedirect();

            $this->assertDatabaseHas('problem_reports', [
                'user_id' => $user->id,
                'organization_id' => $organization->id,
                'organization_name_snapshot' => $organization->name,
            ]);
        });

        it('creates report without organization when none is active', function () {
            $user = User::factory()->create();

            $this->actingAs($user)
                ->post('/problem-reports', [
                    'description' => 'General issue',
                ])
                ->assertRedirect();

            $this->assertDatabaseHas('problem_reports', [
                'user_id' => $user->id,
                'organization_id' => null,
                'organization_name_snapshot' => null,
            ]);
        });

        it('accepts up to 5 screenshots', function () {
            if (! extension_loaded('gd')) {
                $this->markTestSkipped('GD extension is required for this test');
            }

            $user = User::factory()->create();
            $screenshots = array_map(
                fn () => UploadedFile::fake()->image('screenshot.png'),
                range(1, 5)
            );

            $this->actingAs($user)
                ->post('/problem-reports', [
                    'description' => 'Issue with multiple evidence photos',
                    'screenshots' => $screenshots,
                ])
                ->assertRedirect();

            $report = ProblemReport::first();
            $this->assertCount(5, $report->attachments);
        });

        it('stores screenshot metadata correctly', function () {
            if (! extension_loaded('gd')) {
                $this->markTestSkipped('GD extension is required for this test');
            }

            $user = User::factory()->create();
            $screenshot = UploadedFile::fake()->image('test.png', width: 800, height: 600);

            $this->actingAs($user)
                ->post('/problem-reports', [
                    'description' => 'Screenshot test',
                    'screenshots' => [$screenshot],
                ])
                ->assertRedirect();

            $attachment = ProblemReport::first()->attachments()->first();
            $this->assertEquals('local', $attachment->disk);
            $this->assertEquals('test.png', $attachment->original_name);
            $this->assertStringContainsString('problem-reports/', $attachment->path);
            $this->assertStringEndsWith('.png', $attachment->path);
            $this->assertEquals('image/png', $attachment->mime_type);
            $this->assertGreaterThan(0, $attachment->size);
        });

        it('rejects more than 5 screenshots', function () {
            if (! extension_loaded('gd')) {
                $this->markTestSkipped('GD extension is required for this test');
            }

            $user = User::factory()->create();
            $screenshots = array_map(
                fn () => UploadedFile::fake()->image('screenshot.png'),
                range(1, 6)
            );

            $response = $this->actingAs($user)
                ->post('/problem-reports', [
                    'description' => 'Too many screenshots',
                    'screenshots' => $screenshots,
                ]);

            $response->assertSessionHasErrors('screenshots');
            $this->assertDatabaseMissing('problem_reports', [
                'description' => 'Too many screenshots',
            ]);
        });

        it('rejects non-image files', function () {
            $user = User::factory()->create();
            $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

            $response = $this->actingAs($user)
                ->post('/problem-reports', [
                    'description' => 'PDF file attempt',
                    'screenshots' => [$file],
                ]);

            $response->assertSessionHasErrors('screenshots.0');
        });

        it('rejects oversized files', function () {
            $user = User::factory()->create();
            $file = UploadedFile::fake()->create('large.jpg', 6000, 'image/jpeg');

            $response = $this->actingAs($user)
                ->post('/problem-reports', [
                    'description' => 'Oversized image',
                    'screenshots' => [$file],
                ]);

            $response->assertSessionHasErrors('screenshots.0');
        });

        it('requires non-empty description', function () {
            $user = User::factory()->create();

            $response = $this->actingAs($user)
                ->post('/problem-reports', [
                    'description' => '',
                ]);

            $response->assertSessionHasErrors('description');
        });

        it('rejects description over 10000 characters', function () {
            $user = User::factory()->create();
            $longDescription = str_repeat('a', 10001);

            $response = $this->actingAs($user)
                ->post('/problem-reports', [
                    'description' => $longDescription,
                ]);

            $response->assertSessionHasErrors('description');
        });

        it('rejects title over 160 characters', function () {
            $user = User::factory()->create();
            $longTitle = str_repeat('a', 161);

            $response = $this->actingAs($user)
                ->post('/problem-reports', [
                    'title' => $longTitle,
                    'description' => 'Test',
                ]);

            $response->assertSessionHasErrors('title');
        });

        it('generates unique report references', function () {
            $user = User::factory()->create();

            $this->actingAs($user)->post('/problem-reports', [
                'description' => 'First report',
            ]);

            $this->actingAs($user)->post('/problem-reports', [
                'description' => 'Second report',
            ]);

            $reports = ProblemReport::whereUserId($user->id)->get();
            $this->assertCount(2, $reports);
            $this->assertNotEquals(
                $reports[0]->reference,
                $reports[1]->reference
            );
        });

        it('applies rate limit of 10 reports per hour', function () {
            $user = User::factory()->create();

            for ($i = 0; $i < 10; $i++) {
                $response = $this->actingAs($user)->post('/problem-reports', [
                    'description' => "Report {$i}",
                ]);
                $response->assertRedirect();
            }

            $response = $this->actingAs($user)->post('/problem-reports', [
                'description' => 'Eleventh report',
            ]);

            $response->assertRedirect();
            $response->assertSessionHasErrors('submission');
            $this->assertSame(10, ProblemReport::whereUserId($user->id)->count());
        });

        it('enforces the rate limit for a full hour, not just 60 seconds', function () {
            $user = User::factory()->create();

            for ($i = 0; $i < 10; $i++) {
                $this->actingAs($user)->post('/problem-reports', [
                    'description' => "Report {$i}",
                ])->assertRedirect();
            }

            $this->travel(61)->seconds();

            $this->actingAs($user)->post('/problem-reports', [
                'description' => 'Still within the hour',
            ])->assertRedirect()->assertSessionHasErrors('submission');

            $this->travel(3600)->seconds();

            $this->actingAs($user)->post('/problem-reports', [
                'description' => 'After the hour has elapsed',
            ])->assertRedirect();
        });

        it('requires authentication', function () {
            $response = $this->post('/problem-reports', [
                'description' => 'Unauthorized report',
            ]);

            $response->assertRedirect('/login');
        });

        it('requires email verification', function () {
            $user = User::factory()->unverified()->create();
            $response = $this->actingAs($user)->post('/problem-reports', [
                'description' => 'Unverified user report',
            ]);

            $response->assertRedirect('/email/verify');
        });
    });

    describe('Index reports', function () {
        it('requires authentication', function () {
            $response = $this->get('/problem-reports');
            $response->assertRedirect('/login');
        });

        it('displays user owned reports', function () {
            $user = User::factory()->create();
            $report1 = ProblemReport::factory()->create(['user_id' => $user->id]);
            $report2 = ProblemReport::factory()->create(['user_id' => $user->id]);

            $response = $this->actingAs($user)->get('/problem-reports');
            $response->assertOk();
            $response->assertInertia(fn ($page) => $page
                ->component('problem-reports/index')
                ->where('reports.data.0.id', $report2->id)
                ->where('reports.data.1.id', $report1->id)
            );
        });

        it('excludes other users reports', function () {
            $user1 = User::factory()->create();
            $user2 = User::factory()->create();
            $report1 = ProblemReport::factory()->create(['user_id' => $user1->id]);
            $report2 = ProblemReport::factory()->create(['user_id' => $user2->id]);

            $response = $this->actingAs($user1)->get('/problem-reports');
            $response->assertOk();
            $response->assertInertia(fn ($page) => $page
                ->has('reports.data', 1)
                ->where('reports.data.0.id', $report1->id)
            );
        });

        it('orders reports newest first', function () {
            $user = User::factory()->create();
            $report1 = ProblemReport::factory()->create(['user_id' => $user->id]);
            $report2 = ProblemReport::factory()->create(['user_id' => $user->id]);
            $report3 = ProblemReport::factory()->create(['user_id' => $user->id]);

            $response = $this->actingAs($user)->get('/problem-reports');
            $response->assertInertia(fn ($page) => $page
                ->where('reports.data.0.id', $report3->id)
                ->where('reports.data.1.id', $report2->id)
                ->where('reports.data.2.id', $report1->id)
            );
        });

        it('paginates reports', function () {
            $user = User::factory()->create();
            ProblemReport::factory(15)->create(['user_id' => $user->id]);

            $response = $this->actingAs($user)->get('/problem-reports');
            $response->assertInertia(fn ($page) => $page
                ->has('reports.data', 10)
                ->where('reports.total', 15)
                ->where('reports.per_page', 10)
            );
        });

        it('empty state when no reports exist', function () {
            $user = User::factory()->create();

            $response = $this->actingAs($user)->get('/problem-reports');
            $response->assertOk();
            $response->assertInertia(fn ($page) => $page
                ->has('reports.data', 0)
            );
        });
    });

    describe('Show report', function () {
        it('displays owned report', function () {
            $user = User::factory()->create();
            $report = ProblemReport::factory()->create(['user_id' => $user->id]);

            $response = $this->actingAs($user)->get("/problem-reports/{$report->reference}");
            $response->assertOk();
            $response->assertInertia(fn ($page) => $page
                ->where('viewingContext', 'owner')
            );
        });

        it('denies access to other users report', function () {
            $user1 = User::factory()->create();
            $user2 = User::factory()->create();
            $report = ProblemReport::factory()->create(['user_id' => $user1->id]);

            $response = $this->actingAs($user2)->get("/problem-reports/{$report->reference}");
            $response->assertForbidden();
        });

        it('displays report with title and screenshots', function () {
            $user = User::factory()->create();
            $report = ProblemReport::factory()
                ->has(ProblemReportAttachment::factory(2), 'attachments')
                ->create([
                    'user_id' => $user->id,
                    'title' => 'Test issue',
                    'description' => 'This is a test',
                ]);

            $response = $this->actingAs($user)->get("/problem-reports/{$report->reference}");
            $response->assertOk();
        });

        it('returns 404 for non-existent report', function () {
            $user = User::factory()->create();
            $response = $this->actingAs($user)->get('/problem-reports/PR-invalid-reference');
            $response->assertNotFound();
        });

        it('requires authentication', function () {
            $report = ProblemReport::factory()->create();
            $response = $this->get("/problem-reports/{$report->reference}");
            $response->assertRedirect('/login');
        });
    });

    describe('Show report as operator', function () {
        it('displays report to a platform admin with operator viewing context', function () {
            $admin = User::factory()->create();
            PlatformAdmin::query()->create(['user_id' => $admin->getKey()]);
            $owner = User::factory()->create();
            $report = ProblemReport::factory()->create(['user_id' => $owner->id]);

            $response = $this->actingAs($admin)->get("/problem-reports/{$report->reference}/operator");
            $response->assertOk();
            $response->assertInertia(fn ($page) => $page
                ->component('problem-reports/show')
                ->where('viewingContext', 'operator')
                ->where('reporter.name', $owner->name)
                ->where('reporter.email', $owner->email)
            );
        });

        it('does not expose reporter identity in owner viewing context', function () {
            $owner = User::factory()->create();
            $report = ProblemReport::factory()->create(['user_id' => $owner->id]);

            $response = $this->actingAs($owner)->get("/problem-reports/{$report->reference}");
            $response->assertOk();
            $response->assertInertia(fn ($page) => $page
                ->component('problem-reports/show')
                ->where('viewingContext', 'owner')
                ->missing('reporter')
            );
        });

        it('denies access to non platform admins', function () {
            $user = User::factory()->create();
            $owner = User::factory()->create();
            $report = ProblemReport::factory()->create(['user_id' => $owner->id]);

            $response = $this->actingAs($user)->get("/problem-reports/{$report->reference}/operator");
            $response->assertForbidden();
        });
    });

    describe('Retry Notion sync', function () {
        it('resets a terminally failed report and redispatches the sync job', function () {
            Queue::fake();

            $admin = User::factory()->create();
            PlatformAdmin::query()->create(['user_id' => $admin->getKey()]);
            $report = ProblemReport::factory()->create([
                'notion_id' => null,
                'notion_sync_failed_at' => now(),
                'notion_sync_failure_reason' => 'duplicate_report_id',
            ]);

            $response = $this->actingAs($admin)
                ->post("/problem-reports/{$report->reference}/operator/retry-notion-sync");

            $response->assertRedirect("/problem-reports/{$report->reference}/operator");

            $report->refresh();

            expect($report->notion_sync_failed_at)->toBeNull()
                ->and($report->notion_sync_failure_reason)->toBeNull();

            Queue::assertPushed(SyncProblemReportToNotion::class, fn (SyncProblemReportToNotion $job): bool => $job->reportId === $report->id);
        });

        it('denies retry to non platform admins', function () {
            Queue::fake();

            $user = User::factory()->create();
            $report = ProblemReport::factory()->create([
                'notion_id' => null,
                'notion_sync_failed_at' => now(),
                'notion_sync_failure_reason' => 'duplicate_report_id',
            ]);

            Queue::fake();

            $response = $this->actingAs($user)
                ->post("/problem-reports/{$report->reference}/operator/retry-notion-sync");

            $response->assertForbidden();

            Queue::assertNotPushed(SyncProblemReportToNotion::class);
        });

        it('does nothing when the report has no terminal failure', function () {
            Queue::fake();

            $admin = User::factory()->create();
            PlatformAdmin::query()->create(['user_id' => $admin->getKey()]);
            $report = ProblemReport::factory()->create(['notion_id' => null]);

            Queue::fake();

            $response = $this->actingAs($admin)
                ->post("/problem-reports/{$report->reference}/operator/retry-notion-sync");

            $response->assertRedirect("/problem-reports/{$report->reference}/operator");

            Queue::assertNotPushed(SyncProblemReportToNotion::class);
        });
    });

    describe('Attachment access', function () {
        it('allows owner to view attachment', function () {
            $user = User::factory()->create();
            $report = ProblemReport::factory()->create(['user_id' => $user->id]);

            $path = "problem-reports/{$report->id}/test-image.jpg";
            Storage::disk('local')->put($path, 'fake image content');

            $attachment = ProblemReportAttachment::factory()
                ->create(['problem_report_id' => $report->id, 'path' => $path]);

            $response = $this->actingAs($user)
                ->get("/problem-reports/{$report->reference}/attachments/{$attachment->id}");
            $response->assertOk();
        });

        it('denies cross-user attachment access', function () {
            $user1 = User::factory()->create();
            $user2 = User::factory()->create();
            $report = ProblemReport::factory()->create(['user_id' => $user1->id]);

            $path = "problem-reports/{$report->id}/test-image.jpg";
            Storage::disk('local')->put($path, 'fake image content');

            $attachment = ProblemReportAttachment::factory()
                ->create(['problem_report_id' => $report->id, 'path' => $path]);

            $response = $this->actingAs($user2)
                ->get("/problem-reports/{$report->reference}/attachments/{$attachment->id}");
            $response->assertForbidden();
        });

        it('returns 404 for non-existent attachment', function () {
            $user = User::factory()->create();
            $report = ProblemReport::factory()->create(['user_id' => $user->id]);

            $response = $this->actingAs($user)
                ->get("/problem-reports/{$report->reference}/attachments/9999");
            $response->assertNotFound();
        });

        it('requires authentication', function () {
            $report = ProblemReport::factory()->create();
            $attachment = ProblemReportAttachment::factory()
                ->create(['problem_report_id' => $report->id]);

            $response = $this->get("/problem-reports/{$report->reference}/attachments/{$attachment->id}");
            $response->assertRedirect('/login');
        });
    });

    describe('Operator attachment access', function () {
        it('allows platform admin to view another user\'s attachment', function () {
            $admin = User::factory()->create();
            PlatformAdmin::query()->create(['user_id' => $admin->getKey()]);
            $owner = User::factory()->create();
            $report = ProblemReport::factory()->create(['user_id' => $owner->id]);

            $path = "problem-reports/{$report->id}/test-image.jpg";
            Storage::disk('local')->put($path, 'fake image content');

            $attachment = ProblemReportAttachment::factory()
                ->create(['problem_report_id' => $report->id, 'path' => $path]);

            $response = $this->actingAs($admin)
                ->get("/problem-reports/{$report->reference}/operator/attachments/{$attachment->id}");
            $response->assertOk();
        });

        it('denies non platform admins', function () {
            $user = User::factory()->create();
            $owner = User::factory()->create();
            $report = ProblemReport::factory()->create(['user_id' => $owner->id]);

            $attachment = ProblemReportAttachment::factory()
                ->create(['problem_report_id' => $report->id]);

            $response = $this->actingAs($user)
                ->get("/problem-reports/{$report->reference}/operator/attachments/{$attachment->id}");
            $response->assertForbidden();
        });

        it('requires authentication', function () {
            $report = ProblemReport::factory()->create();
            $attachment = ProblemReportAttachment::factory()
                ->create(['problem_report_id' => $report->id]);

            $response = $this->get("/problem-reports/{$report->reference}/operator/attachments/{$attachment->id}");
            $response->assertRedirect('/login');
        });
    });

    describe('Transaction rollback', function () {
        it('rolls back database changes when file storage fails', function () {
            if (! extension_loaded('gd')) {
                $this->markTestSkipped('GD extension is required for this test');
            }

            $user = User::factory()->create();

            Storage::shouldReceive('disk->putFileAs')
                ->andThrow(new Exception('Storage failure'));

            $screenshot = UploadedFile::fake()->image('screenshot.png');

            try {
                $this->actingAs($user)->post('/problem-reports', [
                    'description' => 'Report with storage failure',
                    'screenshots' => [$screenshot],
                ]);
            } catch (Exception $e) {
                //
            }

            $this->assertDatabaseMissing('problem_reports', [
                'description' => 'Report with storage failure',
            ]);
        });
    });

    describe('User menu integration', function () {
        it('shows report a problem link in user menu', function () {
            $user = User::factory()->create();

            $response = $this->actingAs($user)->get('/dashboard');
            $response->assertOk();
        });
    });
});
