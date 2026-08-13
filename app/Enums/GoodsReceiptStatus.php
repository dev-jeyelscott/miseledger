<?php

namespace App\Enums;

enum GoodsReceiptStatus: string
{
    case Draft = 'draft';
    case Finalized = 'finalized';
    case Cancelled = 'cancelled';

    /**
     * Only draft receipts may change.
     */
    public function isEditable(): bool
    {
        return $this === self::Draft;
    }
}
