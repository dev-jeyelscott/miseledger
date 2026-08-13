<?php

namespace App\Enums;

enum StockCountStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Finalized = 'finalized';
    case Cancelled = 'cancelled';

    /**
     * Only drafts may change their physical-count evidence.
     */
    public function isEditable(): bool
    {
        return $this === self::Draft;
    }

    /**
     * Inventory-neutral draft or submitted counts may be cancelled.
     */
    public function canCancel(): bool
    {
        return in_array(
            $this,
            [self::Draft, self::Submitted],
            true,
        );
    }
}
