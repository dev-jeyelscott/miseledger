<?php

namespace App\Jobs;

use App\Models\ProblemReport;
use App\Support\Notion\NotionConfig;
use App\Support\Notion\NotionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

final class SyncProblemReportToNotion implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 180];

    public int $timeout = 60;

    public bool $failOnTimeout = false;

    public function __construct(
        public readonly int $reportId,
    ) {}

    /** @return array<int, object> */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("sync-problem-report-to-notion:{$this->reportId}"))
                ->releaseAfter(60)
                ->expireAfter(180),
        ];
    }

    public function handle(): void
    {
        $config = NotionConfig::fromConfig((array) config('notion'));

        if (! $config->enabled) {
            return;
        }

        $report = ProblemReport::query()->find($this->reportId);

        if ($report === null) {
            return;
        }

        // Idempotency: if already synced, skip.
        if ($report->notion_id !== null) {
            return;
        }

        $service = new NotionService($config);

        // Lookup-first: query by Report ID
        $existingPages = $service->queryByReportId($report->reference);

        if (count($existingPages) > 1) {
            Log::error('Notion sync duplicate match', [
                'report_id' => $report->id,
                'report_reference' => $report->reference,
                'matching_pages' => count($existingPages),
            ]);

            return;
        }

        if (count($existingPages) === 1) {
            $existingPage = $existingPages[0];
            $notionId = $existingPage['id'] ?? null;

            if (is_string($notionId)) {
                $report->update([
                    'notion_id' => $notionId,
                    'notion_synced_at' => now(),
                ]);
            }

            return;
        }

        // No existing page: create new Submitted page
        $properties = $this->buildProperties($report);
        $content = $this->buildContent();

        $createdPage = $service->createPage($properties, $content);
        $notionId = $createdPage['id'] ?? null;

        if (is_string($notionId)) {
            $report->update([
                'notion_id' => $notionId,
                'notion_synced_at' => now(),
            ]);
        }
    }

    /**
     * Build Notion page properties for Submitted triage record.
     *
     * @return array<string, array<string, mixed>>
     */
    private function buildProperties(ProblemReport $report): array
    {
        return [
            'Title' => [
                'title' => [
                    [
                        'text' => [
                            'content' => "User Report {$report->reference}",
                        ],
                    ],
                ],
            ],
            'Status' => [
                'status' => [
                    'name' => 'Submitted',
                ],
            ],
            'Project' => [
                'select' => [
                    'name' => 'miseledger',
                ],
            ],
            'Report ID' => [
                'rich_text' => [
                    [
                        'text' => [
                            'content' => $report->reference,
                        ],
                    ],
                ],
            ],
            'User Title' => [
                'rich_text' => $this->buildRichText($report->title ?? ''),
            ],
            'User Description' => [
                'rich_text' => $this->buildRichText($report->description),
            ],
            'Reporter Name' => [
                'rich_text' => [
                    [
                        'text' => [
                            'content' => $report->user->name,
                        ],
                    ],
                ],
            ],
            'Reporter Email' => [
                'email' => $report->user->email,
            ],
            'Organization' => [
                'rich_text' => [
                    [
                        'text' => [
                            'content' => $report->organization_name_snapshot ?? '',
                        ],
                    ],
                ],
            ],
            'Report URL' => [
                'url' => route('problem-reports.show', $report->reference),
            ],
            'Screenshot Count' => [
                'number' => $report->attachments()->count(),
            ],
            'Submitted At' => [
                'date' => [
                    'start' => $report->created_at->toIso8601String(),
                ],
            ],
        ];
    }

    /**
     * Build Notion-compatible rich text array, chunked to API limits.
     * Notion limits individual rich text segments to 2000 characters.
     *
     * @return array<int, array<string, array<string, string>>>
     */
    private function buildRichText(string $text): array
    {
        if ($text === '') {
            return [];
        }

        $chunks = str_split($text, 2000);
        $richText = [];

        foreach ($chunks as $chunk) {
            $richText[] = [
                'text' => [
                    'content' => $chunk,
                ],
            ];
        }

        return $richText;
    }

    /**
     * Build initial page body: system-generated triage checklist only.
     * No user-controlled text is inserted.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildContent(): array
    {
        return [
            [
                'object' => 'block',
                'type' => 'paragraph',
                'paragraph' => [
                    'rich_text' => [
                        [
                            'type' => 'text',
                            'text' => [
                                'content' => 'Human Triage Required',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'object' => 'block',
                'type' => 'bulleted_list_item',
                'bulleted_list_item' => [
                    'rich_text' => [
                        [
                            'type' => 'text',
                            'text' => [
                                'content' => 'Review reporter metadata and report details',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'object' => 'block',
                'type' => 'bulleted_list_item',
                'bulleted_list_item' => [
                    'rich_text' => [
                        [
                            'type' => 'text',
                            'text' => [
                                'content' => 'Rewrite title for internal task clarity',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'object' => 'block',
                'type' => 'bulleted_list_item',
                'bulleted_list_item' => [
                    'rich_text' => [
                        [
                            'type' => 'text',
                            'text' => [
                                'content' => 'Assign positive integer Priority',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'object' => 'block',
                'type' => 'bulleted_list_item',
                'bulleted_list_item' => [
                    'rich_text' => [
                        [
                            'type' => 'text',
                            'text' => [
                                'content' => 'Confirm Project selection',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'object' => 'block',
                'type' => 'bulleted_list_item',
                'bulleted_list_item' => [
                    'rich_text' => [
                        [
                            'type' => 'text',
                            'text' => [
                                'content' => 'Set Status to Ready to promote for triage',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
