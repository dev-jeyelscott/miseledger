<?php

namespace App\Enums;

enum BillingPaymentStatus: string
{
    case Pending = 'pending';
    case AwaitingPayment = 'awaiting_payment';
    case Paid = 'paid';
    case Failed = 'failed';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function isReusable(): bool
    {
        return $this === self::AwaitingPayment;
    }
}
