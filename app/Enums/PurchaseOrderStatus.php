<?php

namespace App\Enums;

enum PurchaseOrderStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
    case PartiallyReceived = 'partially_received';
    case Received = 'received';
    case Cancelled = 'cancelled';

    /**
     * Determine whether normal PO content remains editable.
     */
    public function isEditable(): bool
    {
        return $this === self::Draft;
    }

    /**
     * Determine whether goods may still be received against this PO.
     */
    public function canReceive(): bool
    {
        return in_array(
            $this,
            [
                self::Approved,
                self::PartiallyReceived,
            ],
            true,
        );
    }
}
