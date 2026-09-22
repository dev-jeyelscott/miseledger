<?php

use App\Enums\ContentKind;
use App\Enums\PlatformAuditAction;
use App\Models\ContentPage;
use App\Models\ContentRevision;
use App\Models\PlatformAdmin;
use App\Models\PlatformAuditEvent;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

function actingContentPlatformAdmin(): User
{
    $platformUser = User::factory()->withTwoFactor()->create();

    PlatformAdmin::query()->create([
        'user_id' => $platformUser->getKey(),
    ]);

    return $platformUser;
}

function validContentPagePayload(array $overrides = []): array
{
    return array_merge([
        'kind' => 'marketing',
        'key' => 'marketing.about',
        'slug' => 'about',
        'title' => 'About MiseLedger',
        'body_markdown' => "# About\n\nWe help kitchens run tighter inventory.",
    ], $overrides);
}

test('content routes preserve the platform administrator boundary', function () {
    $normalUser = User::factory()->create();
    $platformUser = actingContentPlatformAdmin();

    $page = ContentPage::factory()->create();
    $draft = ContentRevision::factory()->for($page, 'contentPage')->create(['revision' => 1]);

    $this->get(route('admin.content.index'))
        ->assertRedirect(route('login'));

    $this->actingAs($normalUser)
        ->get(route('admin.content.index'))
        ->assertForbidden();

    $this->actingAs($normalUser)
        ->get(route('admin.content.show', $page))
        ->assertForbidden();

    $this->actingAs($normalUser)
        ->get(route('admin.content.revisions.edit', $draft))
        ->assertForbidden();

    $this->actingAs($platformUser)
        ->get(route('admin.content.index'))
        ->assertOk();

    $this->actingAs($platformUser)
        ->get(route('admin.content.show', $page))
        ->assertOk();

    $route = Route::getRoutes()->getByName('admin.content.index');

    expect($route?->gatherMiddleware())
        ->toContain('auth')
        ->toContain('verified')
        ->toContain('platform.admin');
});

test('creating a draft page requires recent password confirmation', function () {
    $platformUser = actingContentPlatformAdmin();

    $this->actingAs($platformUser)
        ->post(route('admin.content.store'), validContentPagePayload())
        ->assertRedirect(route('password.confirm'));

    expect(ContentPage::query()->count())->toBe(0);
});

test('creating a draft page validates markdown-only fields', function () {
    $platformUser = actingContentPlatformAdmin();

    $this->actingAs($platformUser)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('admin.content.store'), validContentPagePayload(['key' => 'not a valid key!']))
        ->assertSessionHasErrors(['key']);

    expect(ContentPage::query()->count())->toBe(0);

    $this->actingAs($platformUser)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('admin.content.store'), validContentPagePayload())
        ->assertRedirect();

    $page = ContentPage::query()->sole();

    expect($page->kind)->toBe(ContentKind::Marketing)
        ->and($page->published_revision_id)->toBeNull()
        ->and($page->revisions()->count())->toBe(1);
});

test('creating a draft page rejects duplicate keys and slugs', function () {
    $platformUser = actingContentPlatformAdmin();

    $this->actingAs($platformUser)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('admin.content.store'), validContentPagePayload())
        ->assertRedirect();

    $this->actingAs($platformUser)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('admin.content.store'), validContentPagePayload())
        ->assertSessionHasErrors(['key', 'slug']);

    expect(ContentPage::query()->count())->toBe(1);
});

test('published revisions cannot be edited through the UI', function () {
    $platformUser = actingContentPlatformAdmin();

    $page = ContentPage::factory()->create();
    $published = ContentRevision::factory()
        ->for($page, 'contentPage')
        ->published()
        ->create(['revision' => 1]);

    $page->update(['published_revision_id' => $published->id]);

    $this->actingAs($platformUser)
        ->get(route('admin.content.revisions.edit', $published))
        ->assertForbidden();

    $this->actingAs($platformUser)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->put(route('admin.content.revisions.update', $published), [
            'title' => 'Hacked title',
            'body_markdown' => 'Hacked body',
        ])
        ->assertForbidden();

    expect($published->fresh()->title)->not->toBe('Hacked title');
});

test('publishing a draft revision updates the page pointer and writes audit evidence', function () {
    $platformUser = actingContentPlatformAdmin();

    $page = ContentPage::factory()->create();
    $draft = ContentRevision::factory()->for($page, 'contentPage')->create([
        'revision' => 1,
        'title' => 'Final title',
    ]);

    $this->actingAs($platformUser)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('admin.content.revisions.publish', $draft))
        ->assertSessionHasErrors(['confirm']);

    $this->actingAs($platformUser)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('admin.content.revisions.publish', $draft), ['confirm' => '1'])
        ->assertRedirect(route('admin.content.show', $page));

    $page->refresh();
    $draft->refresh();

    expect($page->published_revision_id)->toBe($draft->id)
        ->and($page->title)->toBe('Final title')
        ->and($draft->published_at)->not->toBeNull()
        ->and($draft->published_by_user_id)->toBe($platformUser->id);

    $event = PlatformAuditEvent::query()->latest('id')->first();

    expect($event)->not->toBeNull()
        ->and($event->action)->toBe(PlatformAuditAction::ContentRevisionPublished)
        ->and($event->subject_type)->toBe('content_revision')
        ->and($event->subject_id)->toBe((string) $draft->id);
});

test('duplicate publish submission is rejected without creating a second published revision', function () {
    $platformUser = actingContentPlatformAdmin();

    $page = ContentPage::factory()->create();
    $draft = ContentRevision::factory()->for($page, 'contentPage')->create(['revision' => 1]);

    $this->actingAs($platformUser)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('admin.content.revisions.publish', $draft), ['confirm' => '1'])
        ->assertRedirect();

    $this->actingAs($platformUser)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('admin.content.revisions.publish', $draft), ['confirm' => '1'])
        ->assertForbidden();

    expect(ContentRevision::query()->where('content_page_id', $page->id)->count())->toBe(1);
});

test('restore as draft copies historical content into a new unpublished revision without publishing it', function () {
    $platformUser = actingContentPlatformAdmin();

    $page = ContentPage::factory()->create();
    $original = ContentRevision::factory()->for($page, 'contentPage')->published()->create([
        'revision' => 1,
        'title' => 'Original title',
        'body_markdown' => 'Original body',
    ]);
    $page->update(['published_revision_id' => $original->id]);

    $superseding = ContentRevision::factory()->for($page, 'contentPage')->published()->create([
        'revision' => 2,
        'title' => 'Newer title',
        'body_markdown' => 'Newer body',
    ]);
    $page->update(['published_revision_id' => $superseding->id]);

    $this->actingAs($platformUser)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('admin.content.revisions.restore', $original))
        ->assertRedirect();

    $newDraft = ContentRevision::query()->where('content_page_id', $page->id)->orderByDesc('revision')->first();

    expect($newDraft->revision)->toBe(3)
        ->and($newDraft->title)->toBe('Original title')
        ->and($newDraft->body_markdown)->toBe('Original body')
        ->and($newDraft->isDraft())->toBeTrue();

    // Every intermediate revision remains queryable, and the pointer is untouched.
    expect($page->fresh()->published_revision_id)->toBe($superseding->id)
        ->and(ContentRevision::query()->where('content_page_id', $page->id)->count())->toBe(3);
});

test('restore is rejected when a draft already exists for the page', function () {
    $platformUser = actingContentPlatformAdmin();

    $page = ContentPage::factory()->create();
    $published = ContentRevision::factory()->for($page, 'contentPage')->published()->create(['revision' => 1]);
    $page->update(['published_revision_id' => $published->id]);

    ContentRevision::factory()->for($page, 'contentPage')->create(['revision' => 2]);

    $this->actingAs($platformUser)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('admin.content.revisions.restore', $published))
        ->assertSessionHasErrors(['confirm']);

    expect(ContentRevision::query()->where('content_page_id', $page->id)->count())->toBe(2);
});

test('published content revisions are immutable at the model boundary', function () {
    $page = ContentPage::factory()->create();
    $revision = ContentRevision::factory()->for($page, 'contentPage')->published()->create(['revision' => 1]);

    expect(fn () => $revision->update(['body_markdown' => 'rewritten']))
        ->toThrow(LogicException::class);

    expect(fn () => $revision->delete())
        ->toThrow(LogicException::class);
});

test('public routes serve only explicitly published content and never leak drafts', function () {
    $page = ContentPage::factory()->create(['slug' => 'draft-only']);
    ContentRevision::factory()->for($page, 'contentPage')->create(['revision' => 1]);

    $this->get(route('content.marketing.show', 'draft-only'))
        ->assertNotFound();

    $published = ContentRevision::factory()->for($page, 'contentPage')->published()->create([
        'revision' => 2,
        'title' => 'Public title',
        'body_markdown' => 'Public body',
    ]);
    $page->update(['published_revision_id' => $published->id]);

    $response = $this->get(route('content.marketing.show', 'draft-only'))
        ->assertOk();

    $response->assertInertia(function (Assert $inertiaPage) {
        $inertiaPage->component('content/show')
            ->where('title', 'Public title')
            ->where('bodyMarkdown', 'Public body');

        $json = json_encode($inertiaPage->toArray());

        expect($json)
            ->not->toContain('draftRevisionId')
            ->not->toContain('createdBy')
            ->not->toContain('publishedBy')
            ->not->toContain('actor');
    });
});

test('an unpublished legal slug returns 404 even when a draft exists', function () {
    $page = ContentPage::factory()->legal()->create(['slug' => 'terms-of-service']);
    ContentRevision::factory()->for($page, 'contentPage')->create(['revision' => 1]);

    $this->get(route('content.legal.show', 'terms-of-service'))
        ->assertNotFound();
});

test('a nonexistent public slug returns 404', function () {
    $this->get(route('content.marketing.show', 'does-not-exist'))
        ->assertNotFound();
});

test('content mutations are rate limited independently per admin', function () {
    $platformUser = actingContentPlatformAdmin();

    $this->actingAs($platformUser)
        ->withSession(['auth.password_confirmed_at' => time()]);

    for ($i = 0; $i < 20; $i++) {
        $this->post(route('admin.content.store'), []);
    }

    $this->post(route('admin.content.store'), [])
        ->assertStatus(429);
});
