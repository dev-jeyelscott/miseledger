<?php

namespace App\Enums;

enum BillingCollectionMethod: string
{
    case Automatic = 'automatic';
    case Manual = 'manual';
}
