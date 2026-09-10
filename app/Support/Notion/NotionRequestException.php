<?php

namespace App\Support\Notion;

use RuntimeException;
use Throwable;

final class NotionRequestException extends RuntimeException
{
    /**
     * Represent a classified Notion failure without embedding secrets or response bodies.
     */
    public function __construct(
        public readonly string $operation,
        public readonly bool $isTransient,
        public readonly ?int $status = null,
        public readonly ?string $reason = null,
        ?Throwable $previous = null,
    ) {
        $failureType = $isTransient ? 'transient' : 'permanent';
        $statusSuffix = $status === null ? '' : " (HTTP {$status})";
        $reasonSuffix = $reason === null ? '' : " [{$reason}]";

        parent::__construct(
            "Notion {$operation} {$failureType} failure{$statusSuffix}{$reasonSuffix}",
            previous: $previous,
        );
    }

    /**
     * Build a retryable Notion failure.
     */
    public static function transient(
        string $operation,
        ?int $status = null,
        ?string $reason = null,
        ?Throwable $previous = null,
    ): self {
        return new self(
            operation: $operation,
            isTransient: true,
            status: $status,
            reason: $reason,
            previous: $previous,
        );
    }

    /**
     * Build a non-retryable Notion failure.
     */
    public static function permanent(
        string $operation,
        ?int $status = null,
        ?string $reason = null,
        ?Throwable $previous = null,
    ): self {
        return new self(
            operation: $operation,
            isTransient: false,
            status: $status,
            reason: $reason,
            previous: $previous,
        );
    }
}
