<?php

namespace App\Support\Billing\Providers;

use RuntimeException;

/** A sanitized PayMongo integration failure safe to report and log. */
final class PayMongoRequestException extends RuntimeException
{
    public function __construct(
        public readonly string $operation,
        public readonly string $classification,
        public readonly ?int $httpStatus = null,
        public readonly ?string $reference = null,
    ) {
        parent::__construct("PayMongo {$classification} failure during {$operation}.");
    }

    /** @return array{provider: string, operation: string, classification: string, http_status: int|null, reference: string|null} */
    public function context(): array
    {
        return [
            'provider' => 'paymongo',
            'operation' => $this->operation,
            'classification' => $this->classification,
            'http_status' => $this->httpStatus,
            'reference' => $this->reference,
        ];
    }
}
