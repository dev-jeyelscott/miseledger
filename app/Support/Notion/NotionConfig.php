<?php

namespace App\Support\Notion;

final class NotionConfig
{
    public function __construct(
        public readonly bool $enabled,
        public readonly ?string $apiKey,
        public readonly string $apiVersion,
        public readonly ?string $dataSourceId,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromConfig(array $config): self
    {
        return new self(
            enabled: $config['enabled'] ?? false,
            apiKey: $config['api_key'] ?? null,
            apiVersion: $config['api_version'] ?? '2022-06-28',
            dataSourceId: $config['data_source_id'] ?? null,
        );
    }

    public function isValid(): bool
    {
        if (! $this->enabled) {
            return true;
        }

        return is_string($this->apiKey)
            && $this->apiKey !== ''
            && is_string($this->dataSourceId)
            && $this->dataSourceId !== '';
    }
}
