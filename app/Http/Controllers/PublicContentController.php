<?php

namespace App\Http\Controllers;

use App\Enums\ContentKind;
use App\Models\ContentPage;
use App\Models\ContentRevision;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Serves only explicitly published CMS content (POC-V6.7). A draft revision
 * ID can never be used to bypass publication: pages are always resolved by
 * public slug and must carry a `published_revision_id`. No platform
 * metadata, actor identity, or internal IDs are exposed here.
 */
class PublicContentController extends Controller
{
    /** Serve a published marketing page by its public slug. */
    public function showMarketing(string $slug): Response
    {
        return $this->render(ContentKind::Marketing, $slug);
    }

    /**
     * Serve a published legal page by its public slug. Implementation may
     * exist ahead of launch: Terms/Privacy stay gated from real publication
     * until the business/legal review requirements in
     * COMMERCIAL_POLICY_DECISIONS.md are satisfied. This route never grants
     * that approval; it only renders whatever a platform admin has
     * explicitly published.
     */
    public function showLegal(string $slug): Response
    {
        return $this->render(ContentKind::Legal, $slug);
    }

    private function render(ContentKind $kind, string $slug): Response
    {
        $page = ContentPage::query()
            ->where('kind', $kind->value)
            ->where('slug', $slug)
            ->whereNotNull('published_revision_id')
            ->with('publishedRevision')
            ->first();

        if ($page === null || ! $page->publishedRevision instanceof ContentRevision) {
            abort(404);
        }

        return Inertia::render('content/show', [
            'title' => $page->publishedRevision->title,
            'bodyMarkdown' => $page->publishedRevision->body_markdown,
            'publishedAt' => $page->publishedRevision->published_at?->toIso8601String(),
        ]);
    }
}
