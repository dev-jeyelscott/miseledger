<?php

use App\Enums\ContentKind;
use App\Models\ContentPage;
use App\Models\ContentRevision;

test('dry run reports intended imports without writing to the database', function () {
    $this->artisan('content:bootstrap-legal-drafts')
        ->expectsOutputToContain('Dry run only')
        ->assertExitCode(0);

    expect(ContentPage::query()->count())->toBe(0);
});

test('apply imports terms and privacy as unpublished drafts preserving source text', function () {
    $termsSource = file_get_contents(base_path('TERMS_OF_SERVICE.md'));
    $privacySource = file_get_contents(base_path('PRIVACY_POLICY.md'));

    $this->artisan('content:bootstrap-legal-drafts --apply')
        ->assertExitCode(0);

    $terms = ContentPage::query()->where('key', 'legal.terms_of_service')->sole();
    $privacy = ContentPage::query()->where('key', 'legal.privacy_policy')->sole();

    expect($terms->kind)->toBe(ContentKind::Legal)
        ->and($terms->published_revision_id)->toBeNull()
        ->and($privacy->published_revision_id)->toBeNull();

    $termsRevision = $terms->revisions()->sole();
    $privacyRevision = $privacy->revisions()->sole();

    expect($termsRevision->body_markdown)->toBe($termsSource)
        ->and($termsRevision->isDraft())->toBeTrue()
        ->and($privacyRevision->body_markdown)->toBe($privacySource)
        ->and($privacyRevision->isDraft())->toBeTrue();
});

test('repeat apply is idempotent and never duplicates pages or revisions', function () {
    $this->artisan('content:bootstrap-legal-drafts --apply')->assertExitCode(0);
    $this->artisan('content:bootstrap-legal-drafts --apply')->assertExitCode(0);

    expect(ContentPage::query()->count())->toBe(2)
        ->and(ContentRevision::query()->count())->toBe(2);
});

test('apply never publishes the imported drafts', function () {
    $this->artisan('content:bootstrap-legal-drafts --apply')->assertExitCode(0);

    expect(ContentRevision::query()->whereNotNull('published_at')->count())->toBe(0);
});
