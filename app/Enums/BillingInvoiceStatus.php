<?php

namespace App\Enums;

enum BillingInvoiceStatus: string
{
    case Pending = 'pending';
    case PaymentPending = 'payment_pending';
    case Paid = 'paid';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function isPayable(): bool
    {
        return $this === self::Pending || $this === self::PaymentPending;
    }
}
