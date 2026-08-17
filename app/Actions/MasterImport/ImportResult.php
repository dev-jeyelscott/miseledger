<?php

namespace App\Actions\MasterImport;

final class ImportResult
{
    /**
     * @param  list<ImportRowError>  $errors
     */
    public function __construct(
        public readonly int $created,
        public readonly int $updated,
        public readonly array $errors,
    ) {}

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }
}
