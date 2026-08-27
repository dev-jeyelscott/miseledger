<?php

namespace App\Support\Billing\Providers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

final class PayMongoClient
{
    private const CONNECT_TIMEOUT_SECONDS = 3;

    private const REQUEST_TIMEOUT_SECONDS = 10;

    private const READ_ATTEMPTS = 2;

    private const MAX_RETRY_AFTER_MILLISECONDS = 2_000;

    /** @return array<string, mixed> */
    public function get(
        string $operation,
        string $path,
        ?string $reference = null,
    ): array {
        return $this->send(
            'GET',
            $operation,
            $path,
            [],
            $reference,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function post(
        string $operation,
        string $path,
        array $payload,
        ?string $reference = null,
        ?string $idempotencyKey = null,
    ): array {
        return $this->send(
            'POST',
            $operation,
            $path,
            $payload,
            $reference,
            $idempotencyKey,
        );
    }

    /**
     * @param  array<string, int|string>  $metadata
     * @return array<string, mixed>
     */
    public function createPaymentIntent(
        int $amount,
        string $currency,
        array $metadata,
        string $reference,
        string $idempotencyKey,
    ): array {
        return $this->post(
            'create_payment_intent',
            '/payment_intents',
            [
                'data' => [
                    'attributes' => [
                        'amount' => $amount,
                        'currency' => $currency,
                        'payment_method_allowed' => [
                            'qrph',
                        ],
                        'payment_method_options' => [
                            'qrph' => [
                                'expiry_duration' => 1_800,
                            ],
                        ],
                        'metadata' => $metadata,
                    ],
                ],
            ],
            $reference,
            $idempotencyKey,
        );
    }

    /** @return array<string, mixed> */
    public function retrievePaymentIntent(
        string $paymentIntentId,
        string $reference,
    ): array {
        return $this->get(
            'retrieve_payment_intent',
            "/payment_intents/{$paymentIntentId}",
            $reference,
        );
    }

    /** @return array<string, mixed> */
    public function createQrPhPaymentMethod(
        string $reference,
        string $idempotencyKey,
    ): array {
        return $this->post(
            'create_qrph_payment_method',
            '/payment_methods',
            [
                'data' => [
                    'attributes' => [
                        'type' => 'qrph',
                    ],
                ],
            ],
            $reference,
            $idempotencyKey,
        );
    }

    /** @return array<string, mixed> */
    public function attachPaymentMethod(
        string $paymentIntentId,
        string $paymentMethodId,
        string $reference,
        string $idempotencyKey,
    ): array {
        return $this->post(
            'attach_payment_method',
            "/payment_intents/{$paymentIntentId}/attach",
            [
                'data' => [
                    'attributes' => [
                        'payment_method' => $paymentMethodId,
                    ],
                ],
            ],
            $reference,
            $idempotencyKey,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function send(
        string $method,
        string $operation,
        string $path,
        array $payload,
        ?string $reference,
        ?string $idempotencyKey = null,
    ): array {
        try {
            $request = $this->request();

            $response = $method === 'GET'
                ? $this->getWithBoundedRetries(
                    $request,
                    $path,
                )
                : $this->postWithIdempotencyKey(
                    $request,
                    $path,
                    $payload,
                    $idempotencyKey,
                );
        } catch (PayMongoRequestException $exception) {
            throw $exception;
        } catch (ConnectionException $exception) {
            throw new PayMongoRequestException(
                $operation,
                str_contains(
                    mb_strtolower(
                        $exception->getMessage(),
                    ),
                    'timed out',
                )
                    ? 'timeout'
                    : 'connection',
                reference: $reference,
            );
        } catch (Throwable) {
            throw new PayMongoRequestException(
                $operation,
                'transport',
                reference: $reference,
            );
        }

        if ($response->successful()) {
            $body = $response->json();

            return is_array($body)
                ? $body
                : [];
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
        $baseUrl = config(
            'billing.providers.paymongo.api_base_url',
        );

        $secretKey = config(
            'billing.providers.paymongo.secret_key',
        );

        if (! is_string($baseUrl)
            || ! filter_var(
                $baseUrl,
                FILTER_VALIDATE_URL,
            )
            || ! is_string($secretKey)
            || $secretKey === '') {
            throw new PayMongoRequestException(
                'configuration',
                'configuration',
            );
        }

        return Http::baseUrl(
            rtrim($baseUrl, '/'),
        )
            ->acceptJson()
            ->asJson()
            ->withBasicAuth($secretKey, '')
            ->connectTimeout(
                self::CONNECT_TIMEOUT_SECONDS,
            )
            ->timeout(
                self::REQUEST_TIMEOUT_SECONDS,
            );
    }

    private function path(string $path): string
    {
        return '/'.ltrim($path, '/');
    }

    /** @param array<string, mixed> $payload */
    private function postWithIdempotencyKey(
        PendingRequest $request,
        string $path,
        array $payload,
        ?string $idempotencyKey,
    ): Response {
        if ($idempotencyKey !== null) {
            $request = $request->withHeader(
                'Idempotency-Key',
                $idempotencyKey,
            );
        }

        return $request->post(
            $this->path($path),
            $payload,
        );
    }

    private function getWithBoundedRetries(
        PendingRequest $request,
        string $path,
    ): Response {
        $attempt = 0;

        while (true) {
            $attempt++;

            try {
                $response = $request->get(
                    $this->path($path),
                );
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

    private function isRetryableResponse(
        Response $response,
    ): bool {
        return $response->status() === 429
            || $response->serverError();
    }

    private function retryDelayMicroseconds(
        Response $response,
    ): int {
        $retryAfter = $response->header(
            'Retry-After',
        );

        if (is_numeric($retryAfter)) {
            return min(
                (int) $retryAfter * 1_000_000,
                self::MAX_RETRY_AFTER_MILLISECONDS
                    * 1_000,
            );
        }

        return 150_000;
    }

    private function classification(
        Response $response,
    ): string {
        return match (true) {
            $response->status() === 400 => 'validation',

            $response->status() === 401 => 'authentication',

            $response->status() === 429 => 'rate_limit',

            $response->serverError() => 'provider',

            default => 'client',
        };
    }
}
