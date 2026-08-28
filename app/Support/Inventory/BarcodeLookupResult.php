<?php

namespace App\Support\Inventory;

use App\Models\Barcode;

final readonly class BarcodeLookupResult
{
    private function __construct(
        public bool $found,
        public ?Barcode $barcode,
    ) {}

    /**
     * Report a successful exact-match resolution.
     */
    public static function found(Barcode $barcode): self
    {
        return new self(true, $barcode);
    }

    /**
     * Report that no active barcode matched within the organization.
     */
    public static function notFound(): self
    {
        return new self(false, null);
    }
}
