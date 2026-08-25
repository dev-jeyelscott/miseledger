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
    public function handle(string $stripeCustomerId, BillingLifecycleEvent $event, string $stripeEventId): void
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

        // Sent synchronously (no ShouldQueue) so this call is delivery, not enqueueing:
        // the caller can atomically tie a completion marker to an actual send attempt.
        // The notification carries a deterministic idempotency key derived from the
        // Stripe event, so a redelivered attempt after a post-send, pre-marker crash is
        // safe to dedupe at the mail transport rather than relying solely on the marker.
        Notification::sendNow(
            $recipients,
            new BillingLifecycleNotification($organization, $event, $stripeEventId),
        );
    }
}
