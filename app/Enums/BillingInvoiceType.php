<?php

namespace App\Enums;

enum BillingInvoiceType: string
{
    case Renewal = 'renewal';
    case Upgrade = 'upgrade';
}
