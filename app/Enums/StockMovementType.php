<?php

namespace App\Enums;

enum StockMovementType: string
{
    case OpeningBalance = 'OPENING_BALANCE';
    case PurchaseReceipt = 'PURCHASE_RECEIPT';
    case Waste = 'WASTE';
    case TransferOut = 'TRANSFER_OUT';
    case TransferIn = 'TRANSFER_IN';
    case CountAdjustment = 'COUNT_ADJUSTMENT';
    case ManualAdjustment = 'MANUAL_ADJUSTMENT';

    /**
     * Determine whether this movement must increase stock.
     */
    public function isInboundOnly(): bool
    {
        return in_array($this, [
            self::OpeningBalance,
            self::PurchaseReceipt,
            self::TransferIn,
        ], true);
    }

    /**
     * Determine whether this movement must decrease stock.
     */
    public function isOutboundOnly(): bool
    {
        return in_array($this, [
            self::Waste,
            self::TransferOut,
        ], true);
    }

    /**
     * Determine whether the caller must provide an explicit inbound unit cost.
     */
    public function requiresExplicitInboundCost(): bool
    {
        return in_array($this, [
            self::OpeningBalance,
            self::PurchaseReceipt,
            self::TransferIn,
        ], true);
    }

    /**
     * Determine whether this movement may represent observed negative stock.
     */
    public function allowsNegativeBalance(): bool
    {
        return $this === self::CountAdjustment;
    }
}
