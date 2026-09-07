<?php

namespace App\Support\Ai\Providers;

final readonly class AiProviderTurn
{
    public function __construct(
        public string $id,
        public string $output,
        public ?string $model = null,
        public ?int $inputTokens = null,
        public ?int $outputTokens = null,
    ) {}
}
