<?php

namespace App\Enums;

enum BillingPaymentMethod: string
{
    case Card = 'card';
    case Maya = 'maya';
    case QrPh = 'qrph';
}
