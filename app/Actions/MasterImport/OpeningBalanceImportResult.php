<?php

namespace App\Actions\MasterImport;

final class OpeningBalanceImportResult
{
    /**
     * @param  list<ImportRowError>  $errors
     */
    public function __construct(
        public readonly int $created,
        public readonly int $skipped,
        public readonly array $errors,
    ) {}

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }
}
