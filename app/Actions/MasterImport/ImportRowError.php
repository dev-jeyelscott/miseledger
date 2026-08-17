<?php

namespace App\Actions\MasterImport;

final class ImportRowError
{
    /**
     * @param  list<string>  $messages
     */
    public function __construct(
        public readonly int $row,
        public readonly array $messages,
    ) {}
}
