<?php

namespace App\Actions\Billing;

use App\Enums\BillingLifecycleEvent;
use App\Enums\OrganizationPermission;
use App\Models\Organization;
use App\Notifications\BillingLifecycleNotification;
use Illuminate\Support\Facades\Notification;

class NotifyOrganizationBillingLifecycle
{
    /**
     * Notify billing-authorized members of the organization identified by its
     * Stripe customer ID.
     */
    public function handle(string $stripeCustomerId, BillingLifecycleEvent $event): void
    {
        $organization = Organization::query()
            ->where('stripe_id', $stripeCustomerId)
            ->first();

        if ($organization === null) {
            return;
        }

        $recipients = $organization->memberships()
            ->with('user')
            ->get()
            ->filter(
                fn ($membership): bool => $membership->role->allows(OrganizationPermission::BillingManage),
            )
            ->pluck('user');

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send(
            $recipients,
            (new BillingLifecycleNotification($organization, $event))->afterCommit(),
        );
    }
}
