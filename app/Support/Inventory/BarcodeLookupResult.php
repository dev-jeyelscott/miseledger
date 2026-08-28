<?php

namespace App\Support\Inventory;

use App\Models\InventoryItemBarcode;

final readonly class BarcodeLookupResult
{
    private function __construct(
        public bool $found,
        public ?InventoryItemBarcode $barcode,
    ) {}

    /**
     * Report a successful exact-match resolution.
     */
    public static function found(
        InventoryItemBarcode $barcode,
    ): self {
        return new self(true, $barcode);
    }

    /**
     * Report that no active organization barcode matched.
     */
    public static function notFound(): self
    {
        return new self(false, null);
    }
}
