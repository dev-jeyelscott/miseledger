<?php

namespace App\Exceptions;

use App\Enums\AiProviderErrorCode;
use RuntimeException;

final class AiProviderException extends RuntimeException
{
    public function __construct(
        public readonly AiProviderErrorCode $errorCode,
        string $message = 'The AI provider could not complete the request.',
    ) {
        parent::__construct($message);
    }
}
