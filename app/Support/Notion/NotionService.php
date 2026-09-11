<?php

namespace App\Support\Notion;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class NotionService
{
    private const CONNECT_TIMEOUT_SECONDS = 3;

    private const REQUEST_TIMEOUT_SECONDS = 10;

    private const READ_ATTEMPTS = 2;

    private const DEFAULT_RETRY_DELAY_MILLISECONDS = 150;

    private const MAX_RETRY_AFTER_MILLISECONDS = 2_000;

    /**
     * Create the HTTP service from validated server-side Notion configuration.
     */
    public function __construct(
        private readonly NotionConfig $config,
    ) {}

    /**
     * Query by exact Report ID and return at most two matches for integrity classification.
     *
     * @return list<array<string, mixed>>
     */
    public function queryByReportId(string $reportId): array
    {
        $this->assertConfigured();

        $matches = [];
        $cursor = null;

        do {
            $payload = [
                'filter' => [
                    'property' => 'Report ID',
                    'rich_text' => [
                        'equals' => $reportId,
                    ],
                ],
                'page_size' => 2,
            ];

            if ($cursor !== null) {
                $payload['start_cursor'] = $cursor;
            }

            $response = $this->postReadWithBoundedRetries(
                "/databases/{$this->config->dataSourceId}/query",
                $payload,
            );

            $body = $response->json();

            if (! is_array($body)) {
                throw NotionRequestException::transient(
                    operation: 'query',
                    reason: 'invalid_response',
                );
            }

            /** @var array<string, mixed> $body */
            $results = $body['results'] ?? null;

            if (! is_array($results)) {
                throw NotionRequestException::transient(
                    operation: 'query',
                    reason: 'invalid_response',
                );
            }

            foreach ($results as $result) {
                if (! is_array($result)) {
                    throw NotionRequestException::transient(
                        operation: 'query',
                        reason: 'invalid_response',
                    );
                }

                /** @var array<string, mixed> $result */
                $matches[] = $result;

                if (count($matches) >= 2) {
                    return $matches;
                }
            }

            $hasMore = $body['has_more'] ?? false;

            if (! is_bool($hasMore)) {
                throw NotionRequestException::transient(
                    operation: 'query',
                    reason: 'invalid_response',
                );
            }

            if (! $hasMore) {
                return $matches;
            }

            $nextCursor = $body['next_cursor'] ?? null;

            if (! is_string($nextCursor) || $nextCursor === '') {
                throw NotionRequestException::transient(
                    operation: 'query',
                    reason: 'invalid_response',
                );
            }

            $cursor = $nextCursor;
        } while (true);
    }

    /**
     * Retrieve one Notion page by exact stored ID with bounded retries.
     *
     * @return array<string, mixed>
     */
    public function retrievePageById(string $pageId): array
    {
        $this->assertConfigured();

        $attempt = 0;

        while (true) {
            $attempt++;

            try {
                $response = $this->request()->get("/pages/{$pageId}");
            } catch (ConnectionException $exception) {
                if ($attempt >= self::READ_ATTEMPTS) {
                    throw NotionRequestException::transient(
                        operation: 'retrieve',
                        reason: 'connection',
                        previous: $exception,
                    );
                }

                usleep(self::DEFAULT_RETRY_DELAY_MILLISECONDS * 1_000);

                continue;
            }

            if ($response->successful()) {
                $body = $response->json();

                if (! is_array($body)) {
                    throw NotionRequestException::transient(
                        operation: 'retrieve',
                        reason: 'invalid_response',
                    );
                }

                /** @var array<string, mixed> $body */
                return $body;
            }

            if (! $this->isTransientStatus($response->status())) {
                throw NotionRequestException::permanent(
                    operation: 'retrieve',
                    status: $response->status(),
                    reason: 'http',
                );
            }

            if ($attempt >= self::READ_ATTEMPTS) {
                throw NotionRequestException::transient(
                    operation: 'retrieve',
                    status: $response->status(),
                    reason: 'http',
                );
            }

            usleep($this->retryDelayMicroseconds($response));
        }
    }

    /**
     * Create one Notion page without retrying the non-idempotent HTTP request in place.
     *
     * @param  array<string, mixed>  $properties
     * @param  list<array<string, mixed>>  $content
     * @return array<string, mixed>
     */
    public function createPage(array $properties, array $content): array
    {
        $this->assertConfigured();

        $payload = [
            'parent' => [
                'database_id' => $this->config->dataSourceId,
            ],
            'properties' => $properties,
        ];

        if ($content !== []) {
            $payload['children'] = $content;
        }

        try {
            $response = $this->request()->post('/pages', $payload);
        } catch (ConnectionException $exception) {
            throw NotionRequestException::transient(
                operation: 'create',
                reason: 'connection',
                previous: $exception,
            );
        }

        if (! $response->successful()) {
            $this->throwForResponse($response, 'create');
        }

        $body = $response->json();

        if (! is_array($body)) {
            throw NotionRequestException::transient(
                operation: 'create',
                reason: 'ambiguous_response',
            );
        }

        /** @var array<string, mixed> $body */
        $pageId = $body['id'] ?? null;

        if (! is_string($pageId) || $pageId === '') {
            throw NotionRequestException::transient(
                operation: 'create',
                reason: 'ambiguous_response',
            );
        }

        return $body;
    }

    /**
     * Reject disabled or incomplete configuration before any network call.
     */
    private function assertConfigured(): void
    {
        if (! $this->config->enabled || ! $this->config->isValid()) {
            throw NotionRequestException::permanent(
                operation: 'configuration',
                reason: 'invalid_configuration',
            );
        }
    }

    /**
     * Build a finite-timeout HTTP client with server-only authentication headers.
     */
    private function request(): PendingRequest
    {
        return Http::baseUrl('https://api.notion.com/v1')
            ->acceptJson()
            ->asJson()
            ->withHeader('Authorization', "Bearer {$this->config->apiKey}")
            ->withHeader('Notion-Version', $this->config->apiVersion)
            ->connectTimeout(self::CONNECT_TIMEOUT_SECONDS)
            ->timeout(self::REQUEST_TIMEOUT_SECONDS);
    }

    /**
     * Retry only read-only query requests and only for bounded transient failures.
     *
     * @param  array<string, mixed>  $payload
     */
    private function postReadWithBoundedRetries(string $path, array $payload): Response
    {
        $attempt = 0;

        while (true) {
            $attempt++;

            try {
                $response = $this->request()->post($path, $payload);
            } catch (ConnectionException $exception) {
                if ($attempt >= self::READ_ATTEMPTS) {
                    throw NotionRequestException::transient(
                        operation: 'query',
                        reason: 'connection',
                        previous: $exception,
                    );
                }

                usleep(self::DEFAULT_RETRY_DELAY_MILLISECONDS * 1_000);

                continue;
            }

            if ($response->successful()) {
                return $response;
            }

            if (! $this->isTransientStatus($response->status())) {
                throw NotionRequestException::permanent(
                    operation: 'query',
                    status: $response->status(),
                    reason: 'http',
                );
            }

            if ($attempt >= self::READ_ATTEMPTS) {
                throw NotionRequestException::transient(
                    operation: 'query',
                    status: $response->status(),
                    reason: 'http',
                );
            }

            usleep($this->retryDelayMicroseconds($response));
        }
    }

    /**
     * Classify a non-success response without exposing its body.
     */
    private function throwForResponse(Response $response, string $operation): never
    {
        if ($this->isTransientStatus($response->status())) {
            throw NotionRequestException::transient(
                operation: $operation,
                status: $response->status(),
                reason: 'http',
            );
        }

        throw NotionRequestException::permanent(
            operation: $operation,
            status: $response->status(),
            reason: 'http',
        );
    }

    /**
     * Identify retryable timeout, conflict, rate-limit, and server responses.
     */
    private function isTransientStatus(int $status): bool
    {
        return in_array($status, [408, 409, 429], true)
            || $status >= 500;
    }

    /**
     * Resolve a bounded Retry-After delay for a read-only query.
     */
    private function retryDelayMicroseconds(Response $response): int
    {
        $retryAfter = $response->header('Retry-After');

        if (is_numeric($retryAfter)) {
            return min(
                max((int) $retryAfter, 0) * 1_000_000,
                self::MAX_RETRY_AFTER_MILLISECONDS * 1_000,
            );
        }

        return self::DEFAULT_RETRY_DELAY_MILLISECONDS * 1_000;
    }
}
