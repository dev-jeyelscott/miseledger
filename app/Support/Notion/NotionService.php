<?php

namespace App\Support\Notion;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

final class NotionService
{
    private const CONNECT_TIMEOUT_SECONDS = 3;

    private const REQUEST_TIMEOUT_SECONDS = 10;

    private const READ_ATTEMPTS = 2;

    private const MAX_RETRY_AFTER_MILLISECONDS = 2_000;

    public function __construct(
        private readonly NotionConfig $config,
    ) {}

    /**
     * Query the Notion database for an existing page with the given Report ID.
     * Returns all matching pages (0, 1, or more).
     *
     * @return array<int, array<string, mixed>>
     */
    public function queryByReportId(string $reportId): array
    {
        if (! $this->config->isValid()) {
            return [];
        }

        try {
            $request = $this->request();

            $response = $this->postWithBoundedRetries(
                $request,
                "/databases/{$this->config->dataSourceId}/query",
                [
                    'filter' => [
                        'property' => 'Report ID',
                        'rich_text' => [
                            'equals' => $reportId,
                        ],
                    ],
                ],
            );
        } catch (ConnectionException $exception) {
            return [];
        } catch (Throwable) {
            return [];
        }

        if (! $response->successful()) {
            return [];
        }

        $body = $response->json();

        return is_array($body) && isset($body['results'])
            ? (array) $body['results']
            : [];
    }

    /**
     * Create a new page in the Notion database.
     *
     * @param  array<string, mixed>  $properties
     * @param  array<int, array<string, mixed>>  $content
     * @return array<string, mixed>
     */
    public function createPage(array $properties, array $content): array
    {
        if (! $this->config->isValid()) {
            return [];
        }

        try {
            $request = $this->request();

            $payload = [
                'parent' => [
                    'database_id' => $this->config->dataSourceId,
                ],
                'properties' => $properties,
            ];

            if (! empty($content)) {
                $payload['children'] = $content;
            }

            $response = $this->postWithBoundedRetries(
                $request,
                '/pages',
                $payload,
            );
        } catch (ConnectionException $exception) {
            return [];
        } catch (Throwable) {
            return [];
        }

        if (! $response->successful()) {
            return [];
        }

        $body = $response->json();

        return is_array($body) ? $body : [];
    }

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
     * @param  array<string, mixed>  $payload
     */
    private function postWithBoundedRetries(
        PendingRequest $request,
        string $path,
        array $payload,
    ): Response {
        $attempt = 0;

        while (true) {
            $attempt++;

            try {
                $response = $request->post($path, $payload);
            } catch (ConnectionException $exception) {
                if ($attempt >= self::READ_ATTEMPTS) {
                    throw $exception;
                }

                usleep(150_000);

                continue;
            }

            if (! $this->isRetryableResponse($response)
                || $attempt >= self::READ_ATTEMPTS) {
                return $response;
            }

            usleep(
                $this->retryDelayMicroseconds(
                    $response,
                ),
            );
        }
    }

    private function isRetryableResponse(Response $response): bool
    {
        return $response->status() === 429
            || $response->serverError();
    }

    private function retryDelayMicroseconds(Response $response): int
    {
        $retryAfter = $response->header('Retry-After');

        if (is_numeric($retryAfter)) {
            return min(
                (int) $retryAfter * 1_000_000,
                self::MAX_RETRY_AFTER_MILLISECONDS * 1_000,
            );
        }

        return 150_000;
    }
}
