<?php

namespace App\Enums;

enum StockTransferStatus: string
{
    case Draft = 'draft';
    case Shipped = 'shipped';
    case Received = 'received';
    case Cancelled = 'cancelled';

    /**
     * Determine whether transfer configuration can still be edited.
     */
    public function isEditable(): bool
    {
        return $this === self::Draft;
    }
}
