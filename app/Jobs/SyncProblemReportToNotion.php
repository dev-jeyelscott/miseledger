<?php

namespace App\Jobs;

use App\Models\ProblemReport;
use App\Support\Notion\NotionConfig;
use App\Support\Notion\NotionRequestException;
use App\Support\Notion\NotionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

final class SyncProblemReportToNotion implements ShouldBeUnique, ShouldQueue
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

    public int $uniqueFor = 900;

    /**
     * Create a sync job keyed by the committed local problem report identifier.
     */
    public function __construct(
        public readonly int $reportId,
    ) {}

    /**
     * Return the stable per-report uniqueness key.
     */
    public function uniqueId(): string
    {
        return (string) $this->reportId;
    }

    /**
     * Prevent concurrent execution of duplicate jobs that may already exist in the queue.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("sync-problem-report-to-notion:{$this->reportId}"))
                ->dontRelease()
                ->expireAfter(90),
        ];
    }

    /**
     * Synchronize one committed report while preserving failure semantics.
     */
    public function handle(): void
    {
        $config = NotionConfig::fromConfig((array) config('services.notion'));

        if (! $config->enabled) {
            return;
        }

        if (! $config->isValid()) {
            $this->fail(NotionRequestException::permanent(
                operation: 'configuration',
                reason: 'invalid_configuration',
            ));

            return;
        }

        $report = ProblemReport::query()
            ->with('user')
            ->find($this->reportId);

        if ($report === null || $report->notion_id !== null) {
            return;
        }

        try {
            $this->sync($report, new NotionService($config));
        } catch (NotionRequestException $exception) {
            if ($exception->isTransient) {
                throw $exception;
            }

            $this->fail($exception);
        }
    }

    /**
     * Record secret-safe diagnostics when the queued job finally fails.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('Problem report Notion synchronization failed', [
            'problem_report_id' => $this->reportId,
            'exception_class' => $exception === null ? null : $exception::class,
            'notion_operation' => $exception instanceof NotionRequestException
                ? $exception->operation
                : null,
            'notion_http_status' => $exception instanceof NotionRequestException
                ? $exception->status
                : null,
            'notion_failure_reason' => $exception instanceof NotionRequestException
                ? $exception->reason
                : null,
            'notion_transient' => $exception instanceof NotionRequestException
                ? $exception->isTransient
                : null,
        ]);
    }

    /**
     * Run the lookup-first adoption-or-create algorithm for one report.
     */
    private function sync(ProblemReport $report, NotionService $service): void
    {
        $existingPages = $service->queryByReportId($report->reference);

        if (count($existingPages) > 1) {
            throw NotionRequestException::permanent(
                operation: 'query',
                reason: 'duplicate_report_id',
            );
        }

        if (count($existingPages) === 1) {
            $this->persistRemotePage($report, $existingPages[0]);

            return;
        }

        $createdPage = $service->createPage(
            $this->buildProperties($report),
            $this->buildContent(),
        );

        $this->persistRemotePage($report, $createdPage);
    }

    /**
     * Persist remote identity, raw remote status, and synchronization timestamp.
     *
     * @param  array<string, mixed>  $page
     */
    private function persistRemotePage(ProblemReport $report, array $page): void
    {
        $notionId = $page['id'] ?? null;

        if (! is_string($notionId) || $notionId === '') {
            throw NotionRequestException::permanent(
                operation: 'persist',
                reason: 'missing_page_id',
            );
        }

        $remoteStatus = data_get($page, 'properties.Status.status.name');

        $report->forceFill([
            'notion_id' => $notionId,
            'notion_status' => is_string($remoteStatus) && $remoteStatus !== ''
                ? $remoteStatus
                : null,
            'notion_synced_at' => now(),
        ])->save();
    }

    /**
     * Build safe Submitted properties with user text confined to metadata.
     *
     * @return array<string, array<string, mixed>>
     */
    private function buildProperties(ProblemReport $report): array
    {
        return [
            'Title' => [
                'title' => [[
                    'text' => [
                        'content' => "User Report {$report->reference}",
                    ],
                ]],
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
                'rich_text' => $this->buildRichText($report->reference),
            ],
            'User Title' => [
                'rich_text' => $this->buildRichText($report->title ?? ''),
            ],
            'User Description' => [
                'rich_text' => $this->buildRichText($report->description),
            ],
            'Reporter Name' => [
                'rich_text' => $this->buildRichText($report->user->name),
            ],
            'Reporter Email' => [
                'email' => $report->user->email,
            ],
            'Organization' => [
                'rich_text' => $this->buildRichText(
                    $report->organization_name_snapshot ?? '',
                ),
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
     * Build API-safe rich text without splitting UTF-8 characters.
     *
     * @return list<array{text: array{content: string}}>
     */
    private function buildRichText(string $text): array
    {
        if ($text === '') {
            return [];
        }

        $length = mb_strlen($text, 'UTF-8');
        $richText = [];

        for ($offset = 0; $offset < $length; $offset += 2000) {
            $richText[] = [
                'text' => [
                    'content' => mb_substr($text, $offset, 2000, 'UTF-8'),
                ],
            ];
        }

        return $richText;
    }

    /**
     * Build trusted system-only triage content.
     *
     * @return list<array<string, mixed>>
     */
    private function buildContent(): array
    {
        return [
            [
                'object' => 'block',
                'type' => 'paragraph',
                'paragraph' => [
                    'rich_text' => [[
                        'type' => 'text',
                        'text' => [
                            'content' => 'Human Triage Required',
                        ],
                    ]],
                ],
            ],
            [
                'object' => 'block',
                'type' => 'bulleted_list_item',
                'bulleted_list_item' => [
                    'rich_text' => [[
                        'type' => 'text',
                        'text' => [
                            'content' => 'Review reporter metadata and report details',
                        ],
                    ]],
                ],
            ],
            [
                'object' => 'block',
                'type' => 'bulleted_list_item',
                'bulleted_list_item' => [
                    'rich_text' => [[
                        'type' => 'text',
                        'text' => [
                            'content' => 'Rewrite title for internal task clarity',
                        ],
                    ]],
                ],
            ],
            [
                'object' => 'block',
                'type' => 'bulleted_list_item',
                'bulleted_list_item' => [
                    'rich_text' => [[
                        'type' => 'text',
                        'text' => [
                            'content' => 'Assign positive integer Priority',
                        ],
                    ]],
                ],
            ],
            [
                'object' => 'block',
                'type' => 'bulleted_list_item',
                'bulleted_list_item' => [
                    'rich_text' => [[
                        'type' => 'text',
                        'text' => [
                            'content' => 'Confirm Project selection',
                        ],
                    ]],
                ],
            ],
            [
                'object' => 'block',
                'type' => 'bulleted_list_item',
                'bulleted_list_item' => [
                    'rich_text' => [[
                        'type' => 'text',
                        'text' => [
                            'content' => 'Set Status to Ready to promote for triage',
                        ],
                    ]],
                ],
            ],
        ];
    }
}
