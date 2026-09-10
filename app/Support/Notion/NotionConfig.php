<?php

namespace App\Support\Notion;

final class NotionConfig
{
    /**
     * Hold the server-side Notion synchronization settings resolved from Laravel config.
     */
    public function __construct(
        public readonly bool $enabled,
        public readonly ?string $apiKey,
        public readonly string $apiVersion,
        public readonly ?string $dataSourceId,
    ) {}

    /**
     * Build a typed configuration object from the canonical services.notion array.
     *
     * @param  array<string, mixed>  $config
     */
    public static function fromConfig(array $config): self
    {
        $apiVersion = $config['api_version'] ?? '2022-06-28';

        return new self(
            enabled: (bool) ($config['enabled'] ?? false),
            apiKey: is_string($config['api_key'] ?? null) ? $config['api_key'] : null,
            apiVersion: is_string($apiVersion) ? $apiVersion : '',
            dataSourceId: is_string($config['data_source_id'] ?? null)
                ? $config['data_source_id']
                : null,
        );
    }

    /**
     * Determine whether an enabled integration has every required server-side setting.
     */
    public function isValid(): bool
    {
        if (! $this->enabled) {
            return true;
        }

        return is_string($this->apiKey)
            && $this->apiKey !== ''
            && $this->apiVersion !== ''
            && is_string($this->dataSourceId)
            && $this->dataSourceId !== '';
    }
}
