<?php

namespace App\Enums;

enum PlatformAdminAuditAction: string
{
    case Grant = 'grant';
    case Revoke = 'revoke';
}
