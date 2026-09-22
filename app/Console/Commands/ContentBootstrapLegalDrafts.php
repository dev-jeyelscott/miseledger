<?php

namespace App\Console\Commands;

use App\Enums\ContentKind;
use App\Models\ContentPage;
use App\Models\ContentRevision;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Operator bootstrap that imports the repository's draft
 * `TERMS_OF_SERVICE.md` and `PRIVACY_POLICY.md` as CMS drafts (POC-V6.2).
 * Presence of these files in the repository is never legal approval:
 * imported content is always draft-only and is never published by this
 * command. Repeat runs are idempotent.
 */
final class ContentBootstrapLegalDrafts extends Command
{
    protected $signature = 'content:bootstrap-legal-drafts
        {--apply : Persist the CMS drafts instead of dry-running}';

    protected $description =
        'Import the repository draft Terms of Service and Privacy Policy Markdown into the CMS as unpublished drafts only.';

    /** @var list<array{key: string, slug: string, title: string, file: string}> */
    private const SOURCES = [
        [
            'key' => 'legal.terms_of_service',
            'slug' => 'terms-of-service',
            'title' => 'Terms of Service',
            'file' => 'TERMS_OF_SERVICE.md',
        ],
        [
            'key' => 'legal.privacy_policy',
            'slug' => 'privacy-policy',
            'title' => 'Privacy Policy',
            'file' => 'PRIVACY_POLICY.md',
        ],
    ];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        $this->info(
            $apply
                ? 'Applying legal draft bootstrap.'
                : 'Dry run only. No database changes will be written. Re-run with --apply to import.',
        );

        foreach (self::SOURCES as $source) {
            $this->importSource($source, $apply);
        }

        $this->newLine();
        $this->warn(
            'LEGAL REVIEW REQUIRED: imported content is DRAFT ONLY. Presence '
            .'in this repository is not legal approval. Terms of Service and '
            .'Privacy Policy must not be published until the business '
            .'registration and legal review requirements in '
            .'COMMERCIAL_POLICY_DECISIONS.md are satisfied.',
        );

        return self::SUCCESS;
    }

    /**
     * @param  array{key: string, slug: string, title: string, file: string}  $source
     */
    private function importSource(array $source, bool $apply): void
    {
        $path = base_path($source['file']);

        if (! is_file($path)) {
            $this->error("Skipping {$source['key']}: {$source['file']} not found.");

            return;
        }

        $bodyMarkdown = (string) file_get_contents($path);

        $page = ContentPage::query()
            ->where('key', $source['key'])
            ->with('revisions')
            ->first();

        if ($page === null) {
            $this->line("Would create draft page [{$source['key']}] from {$source['file']}.");

            if (! $apply) {
                return;
            }

            DB::transaction(function () use ($source, $bodyMarkdown): void {
                $page = ContentPage::query()->create([
                    'kind' => ContentKind::Legal->value,
                    'key' => $source['key'],
                    'slug' => $source['slug'],
                    'title' => $source['title'],
                ]);

                $page->revisions()->create([
                    'revision' => 1,
                    'title' => $source['title'],
                    'body_markdown' => $bodyMarkdown,
                ]);
            });

            $this->info("Created draft page [{$source['key']}].");

            return;
        }

        $latestRevision = $page->revisions->first();

        if (
            $latestRevision instanceof ContentRevision
            && $latestRevision->title === $source['title']
            && $latestRevision->body_markdown === $bodyMarkdown
        ) {
            $this->line("[{$source['key']}] already imported and unchanged. No change.");

            return;
        }

        if ($latestRevision instanceof ContentRevision && ! $latestRevision->isDraft()) {
            $this->line("[{$source['key']}] source text changed since the last import. Would create a new draft revision (never overwriting the published revision).");

            if (! $apply) {
                return;
            }

            $nextRevision = (int) $page->revisions()->max('revision') + 1;

            $page->revisions()->create([
                'revision' => $nextRevision,
                'title' => $source['title'],
                'body_markdown' => $bodyMarkdown,
            ]);

            $this->info("Created draft revision {$nextRevision} for [{$source['key']}].");

            return;
        }

        $this->line("[{$source['key']}] source text changed since the last import. Would update the existing draft revision.");

        if (! $apply || ! $latestRevision instanceof ContentRevision) {
            return;
        }

        $latestRevision->update([
            'title' => $source['title'],
            'body_markdown' => $bodyMarkdown,
        ]);

        $this->info("Updated draft revision for [{$source['key']}].");
    }
}
