<?php

namespace App\Http\Controllers\Platform;

use App\Actions\Platform\RecordPlatformAuditEvent;
use App\Enums\ContentKind;
use App\Enums\PlatformAuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\CreateContentPageRequest;
use App\Http\Requests\Platform\PublishContentRevisionRequest;
use App\Http\Requests\Platform\RestoreContentRevisionRequest;
use App\Http\Requests\Platform\SaveContentRevisionRequest;
use App\Models\ContentPage;
use App\Models\ContentRevision;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The versioned marketing/legal CMS (POC-V6). Draft and published content
 * are strictly separate states; published revisions are immutable historical
 * evidence and rollback always creates a new draft rather than rewriting
 * history. Release Notes and User Guide are static, code-owned modules and
 * are never reachable through this controller.
 */
final class PlatformContentController extends Controller
{
    /** Render every content page with its lifecycle summary. */
    public function index(): Response
    {
        $pages = ContentPage::query()
            ->with([
                'publishedRevision',
                'revisions.createdBy:id,name,email',
                'revisions.publishedBy:id,name,email',
            ])
            ->orderBy('kind')
            ->orderBy('title')
            ->get();

        return Inertia::render('admin/content/index', [
            'pages' => $pages
                ->map(fn (ContentPage $page): array => $this->pageSummary($page))
                ->all(),
        ]);
    }

    /** Render the create-draft form for a new content page. */
    public function create(): Response
    {
        return Inertia::render('admin/content/create', [
            'kindOptions' => $this->kindOptions(),
        ]);
    }

    /** Create a new content page and its first draft revision. */
    public function store(
        CreateContentPageRequest $request,
        RecordPlatformAuditEvent $recordAuditEvent,
    ): RedirectResponse {
        $user = $request->user();

        $revision = DB::transaction(function () use ($request, $user): ContentRevision {
            $page = ContentPage::query()->create([
                'kind' => $request->validated('kind'),
                'key' => $request->validated('key'),
                'slug' => $request->validated('slug'),
                'title' => $request->validated('title'),
            ]);

            return $page->revisions()->create([
                'revision' => 1,
                'title' => $request->validated('title'),
                'body_markdown' => $request->validated('body_markdown'),
                'created_by_user_id' => $user instanceof User ? $user->id : null,
            ]);
        });

        if ($user instanceof User) {
            $recordAuditEvent->handle(
                action: PlatformAuditAction::ContentPageDrafted,
                subjectType: 'content_page',
                subjectId: (string) $revision->content_page_id,
                actor: $user,
                source: 'admin.content.store',
                after: [
                    'kind' => $request->validated('kind'),
                    'key' => $request->validated('key'),
                    'slug' => $request->validated('slug'),
                ],
            );
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Draft created.',
        ]);

        return to_route('admin.content.revisions.edit', $revision);
    }

    /** Render the full revision history for one content page. */
    public function show(ContentPage $contentPage): Response
    {
        $contentPage->load([
            'revisions.createdBy:id,name,email',
            'revisions.publishedBy:id,name,email',
        ]);

        return Inertia::render('admin/content/show', [
            'page' => $this->pageSummary($contentPage),
            'revisions' => $contentPage->revisions
                ->map(fn (ContentRevision $revision): array => $this->revisionPayload(
                    $revision,
                    $contentPage,
                ))
                ->all(),
        ]);
    }

    /** Render the Markdown draft editor for one draft revision. */
    public function edit(ContentRevision $contentRevision): Response
    {
        abort_unless($contentRevision->isDraft(), 403);

        $contentRevision->load('contentPage');

        return Inertia::render('admin/content/edit', [
            'page' => [
                'id' => $contentRevision->contentPage->id,
                'kind' => $contentRevision->contentPage->kind,
                'key' => $contentRevision->contentPage->key,
                'slug' => $contentRevision->contentPage->slug,
            ],
            'revision' => [
                'id' => $contentRevision->id,
                'revision' => $contentRevision->revision,
                'title' => $contentRevision->title,
                'bodyMarkdown' => $contentRevision->body_markdown,
            ],
        ]);
    }

    /** Save Markdown edits to a draft revision. */
    public function update(
        ContentRevision $contentRevision,
        SaveContentRevisionRequest $request,
        RecordPlatformAuditEvent $recordAuditEvent,
    ): RedirectResponse {
        abort_unless($contentRevision->isDraft(), 403);

        DB::transaction(function () use ($contentRevision, $request): void {
            $contentRevision->update([
                'title' => (string) $request->validated('title'),
                'body_markdown' => (string) $request->validated('body_markdown'),
            ]);

            $contentRevision->contentPage->update([
                'title' => (string) $request->validated('title'),
            ]);
        });

        $user = $request->user();

        if ($user instanceof User) {
            $recordAuditEvent->handle(
                action: PlatformAuditAction::ContentRevisionUpdated,
                subjectType: 'content_revision',
                subjectId: (string) $contentRevision->id,
                actor: $user,
                source: 'admin.content.revisions.update',
                after: [
                    'content_page_id' => $contentRevision->content_page_id,
                    'revision' => $contentRevision->revision,
                ],
            );
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Draft saved.',
        ]);

        return back();
    }

    /**
     * Publish a validated draft revision as the page's active public
     * content. Locks the page row so duplicate submissions cannot create
     * two published revisions for the same publish action.
     */
    public function publish(
        ContentRevision $contentRevision,
        PublishContentRevisionRequest $request,
        RecordPlatformAuditEvent $recordAuditEvent,
    ): RedirectResponse {
        abort_unless($contentRevision->isDraft(), 403);

        $user = $request->user();
        $previousPublishedRevisionId = null;

        DB::transaction(function () use ($contentRevision, $user, &$previousPublishedRevisionId): void {
            $page = ContentPage::query()
                ->whereKey($contentRevision->content_page_id)
                ->lockForUpdate()
                ->firstOrFail();

            $revision = ContentRevision::query()
                ->whereKey($contentRevision->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $revision->isDraft()) {
                throw ValidationException::withMessages([
                    'confirm' => 'This revision has already been published.',
                ]);
            }

            $previousPublishedRevisionId = $page->published_revision_id;
            $now = now();

            $revision->update([
                'published_at' => $now,
                'published_by_user_id' => $user instanceof User ? $user->id : null,
            ]);

            $page->update([
                'published_revision_id' => $revision->id,
                'title' => $revision->title,
            ]);
        });

        if ($user instanceof User) {
            $recordAuditEvent->handle(
                action: PlatformAuditAction::ContentRevisionPublished,
                subjectType: 'content_revision',
                subjectId: (string) $contentRevision->id,
                actor: $user,
                source: 'admin.content.revisions.publish',
                before: [
                    'previous_published_revision_id' => $previousPublishedRevisionId,
                ],
                after: [
                    'content_page_id' => $contentRevision->content_page_id,
                    'revision' => $contentRevision->revision,
                    'published_at' => $contentRevision->fresh()?->published_at?->toIso8601String(),
                ],
            );
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Revision published.',
        ]);

        return to_route('admin.content.show', $contentRevision->content_page_id);
    }

    /**
     * Create a new draft revision copied from historical (published or
     * superseded) content. Never publishes; the operator must review and
     * publish the new draft normally.
     */
    public function restore(
        ContentRevision $contentRevision,
        RestoreContentRevisionRequest $request,
        RecordPlatformAuditEvent $recordAuditEvent,
    ): RedirectResponse {
        $page = $contentRevision->contentPage;

        if (
            $page->revisions()->whereNull('published_at')->exists()
        ) {
            throw ValidationException::withMessages([
                'confirm' => 'A draft already exists for this page. Edit the existing draft instead of restoring another.',
            ]);
        }

        $user = $request->user();

        $newRevision = DB::transaction(function () use ($contentRevision, $page, $user): ContentRevision {
            $nextRevision = (int) $page->revisions()->max('revision') + 1;

            return $page->revisions()->create([
                'revision' => $nextRevision,
                'title' => $contentRevision->title,
                'body_markdown' => $contentRevision->body_markdown,
                'created_by_user_id' => $user instanceof User ? $user->id : null,
            ]);
        });

        if ($user instanceof User) {
            $recordAuditEvent->handle(
                action: PlatformAuditAction::ContentRevisionRestored,
                subjectType: 'content_revision',
                subjectId: (string) $newRevision->id,
                actor: $user,
                source: 'admin.content.revisions.restore',
                before: [
                    'restored_from_revision_id' => $contentRevision->id,
                ],
                after: [
                    'content_page_id' => $page->id,
                    'revision' => $newRevision->revision,
                ],
            );
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Historical content restored as a new draft. Review and publish it to make it public.',
        ]);

        return to_route('admin.content.revisions.edit', $newRevision);
    }

    /**
     * Summarize one content page's current/draft state.
     *
     * @return array<string, mixed>
     */
    private function pageSummary(ContentPage $page): array
    {
        $latestDraft = $page->revisions->firstWhere('published_at', null);
        $latestRevision = $page->revisions->first();

        return [
            'id' => $page->id,
            'kind' => $page->kind->value,
            'key' => $page->key,
            'slug' => $page->slug,
            'title' => $page->title,
            'hasPublishedRevision' => $page->published_revision_id !== null,
            'publishedRevisionId' => $page->published_revision_id,
            'hasDraft' => $latestDraft !== null,
            'draftRevisionId' => $latestDraft?->id,
            'latestRevisionNumber' => $latestRevision?->revision,
            'updatedAt' => $latestRevision?->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Render one revision as safe evidence without internal metadata.
     *
     * @return array<string, mixed>
     */
    private function revisionPayload(ContentRevision $revision, ContentPage $page): array
    {
        return [
            'id' => $revision->id,
            'revision' => $revision->revision,
            'title' => $revision->title,
            'status' => match (true) {
                $revision->isDraft() => 'draft',
                $page->published_revision_id === $revision->id => 'published',
                default => 'superseded',
            },
            'createdBy' => $revision->createdBy instanceof User
                ? ['name' => $revision->createdBy->name, 'email' => $revision->createdBy->email]
                : null,
            'createdAt' => $revision->created_at?->toIso8601String(),
            'publishedBy' => $revision->publishedBy instanceof User
                ? ['name' => $revision->publishedBy->name, 'email' => $revision->publishedBy->email]
                : null,
            'publishedAt' => $revision->published_at?->toIso8601String(),
        ];
    }

    /** @return list<array{value: string, label: string}> */
    private function kindOptions(): array
    {
        return [
            ['value' => ContentKind::Marketing->value, 'label' => 'Marketing'],
            ['value' => ContentKind::Legal->value, 'label' => 'Legal'],
        ];
    }
}
