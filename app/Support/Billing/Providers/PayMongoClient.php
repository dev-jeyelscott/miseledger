<?php

namespace App\Support\Billing\Providers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Small, configuration-backed PayMongo HTTP boundary. It intentionally does
 * not model checkout or subscriptions: callers supply only an operation and
 * relative API path, while this class owns authentication, bounded safe-read
 * retry behavior, and sanitized failures.
 */
final class PayMongoClient
{
    private const CONNECT_TIMEOUT_SECONDS = 3;

    private const REQUEST_TIMEOUT_SECONDS = 10;

    private const READ_ATTEMPTS = 2;

    private const MAX_RETRY_AFTER_MILLISECONDS = 2_000;

    /** @return array<string, mixed> */
    public function get(string $operation, string $path, ?string $reference = null): array
    {
        return $this->send('GET', $operation, $path, [], $reference);
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function post(string $operation, string $path, array $payload, ?string $reference = null): array
    {
        return $this->send('POST', $operation, $path, $payload, $reference);
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    private function send(string $method, string $operation, string $path, array $payload, ?string $reference): array
    {
        try {
            $request = $this->request();
            $response = $method === 'GET'
                ? $this->getWithBoundedRetries($request, $path)
                : $request->post($this->path($path), $payload);
        } catch (PayMongoRequestException $exception) {
            throw $exception;
        } catch (ConnectionException $exception) {
            throw new PayMongoRequestException(
                $operation,
                str_contains(mb_strtolower($exception->getMessage()), 'timed out') ? 'timeout' : 'connection',
                reference: $reference,
            );
        } catch (Throwable $exception) {
            throw new PayMongoRequestException($operation, 'transport', reference: $reference);
        }

        if ($response->successful()) {
            $body = $response->json();

            return is_array($body) ? $body : [];
        }

        throw new PayMongoRequestException(
            $operation,
            $this->classification($response),
            $response->status(),
            $reference,
        );
    }

    private function request(): PendingRequest
    {
        $baseUrl = config('billing.providers.paymongo.api_base_url');
        $secretKey = config('billing.providers.paymongo.secret_key');

        if (! is_string($baseUrl) || ! filter_var($baseUrl, FILTER_VALIDATE_URL) || ! is_string($secretKey) || $secretKey === '') {
            throw new PayMongoRequestException('configuration', 'configuration');
        }

        return Http::baseUrl(rtrim($baseUrl, '/'))
            ->acceptJson()
            ->asJson()
            ->withBasicAuth($secretKey, '')
            ->connectTimeout(self::CONNECT_TIMEOUT_SECONDS)
            ->timeout(self::REQUEST_TIMEOUT_SECONDS);
    }

    private function path(string $path): string
    {
        return '/'.ltrim($path, '/');
    }

    private function getWithBoundedRetries(PendingRequest $request, string $path): Response
    {
        for ($attempt = 1; $attempt <= self::READ_ATTEMPTS; $attempt++) {
            $response = $request->retry(self::READ_ATTEMPTS, 150, $this->shouldRetry(...))->get($this->path($path));

            if (! $this->isRetryableResponse($response) || $attempt === self::READ_ATTEMPTS) {
                return $response;
            }

            usleep($this->retryDelayMicroseconds($response));
        }

        throw new PayMongoRequestException('request', 'transport');
    }

    private function shouldRetry(Throwable $exception, PendingRequest $request): bool
    {
        return $exception instanceof ConnectionException;
    }

    private function isRetryableResponse(Response $response): bool
    {
        return $response->status() === 429 || $response->serverError();
    }

    private function retryDelayMicroseconds(Response $response): int
    {
        $retryAfter = $response->header('Retry-After');

        if (is_numeric($retryAfter)) {
            return min((int) $retryAfter * 1_000_000, self::MAX_RETRY_AFTER_MILLISECONDS * 1_000);
        }

        return 150_000;
    }

    private function classification(Response $response): string
    {
        return match (true) {
            $response->status() === 400 => 'validation',
            $response->status() === 401 => 'authentication',
            $response->status() === 429 => 'rate_limit',
            $response->serverError() => 'provider',
            default => 'client',
        };
    }
}
