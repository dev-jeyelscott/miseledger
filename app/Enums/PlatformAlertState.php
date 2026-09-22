<?php

namespace App\Enums;

enum PlatformAlertState: string
{
    case Open = 'open';
    case Resolved = 'resolved';
}
