<?php

namespace App\Support\Mobile;

use App\Enums\OrganizationPermission;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Resolve the permission-filtered action set shown in the item action hub
 * (Spec 2 decision #34.A), reusing desktop's exact permission model rather
 * than a mobile-only ACL. Stock Details is always included: it needs no
 * permission beyond InventoryView, which every OrganizationRole grants.
 */
final class AvailableScanActions
{
    /**
     * @return list<'receive'|'count'|'waste'|'transfer'|'details'>
     */
    public static function forUser(User $user, Organization $organization): array
    {
        $gate = Gate::forUser($user);

        /** @var list<'receive'|'count'|'waste'|'transfer'|'details'> $actions */
        $actions = [];

        if ($gate->allows(OrganizationPermission::ReceivingFinalize->value, $organization)) {
            $actions[] = 'receive';
        }

        if ($gate->allows(OrganizationPermission::CountsCreate->value, $organization)) {
            $actions[] = 'count';
        }

        if ($gate->allows(OrganizationPermission::WasteRecord->value, $organization)) {
            $actions[] = 'waste';
        }

        if ($gate->allows(OrganizationPermission::TransfersCreate->value, $organization)) {
            $actions[] = 'transfer';
        }

        $actions[] = 'details';

        return $actions;
    }
}
