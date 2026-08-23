<?php

namespace App\Enums;

/**
 * Deterministic commercial access mode for an organization, derived by
 * `App\Support\Billing\OrganizationSubscriptionAccessResolver`. This is
 * distinct from `Organization.active`, which remains an independent
 * administrative enable/disable flag.
 */
enum OrganizationAccessMode: string
{
    case Writable = 'writable';
    case ReadOnly = 'read_only';
}
