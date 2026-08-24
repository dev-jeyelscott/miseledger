<?php

namespace App\Actions\Billing;

use App\Enums\BillingLifecycleEvent;
use App\Enums\OrganizationPermission;
use App\Models\Organization;
use App\Notifications\BillingLifecycleNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

class NotifyOrganizationBillingLifecycle
{
    /**
     * Notify billing-authorized members of the organization identified by its
     * Stripe customer ID. Notification dispatch is intentionally best-effort:
     * Cashier remains the authority for lifecycle synchronization.
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

        try {
            Notification::send(
                $recipients,
                (new BillingLifecycleNotification($organization, $event))->afterCommit(),
            );
        } catch (Throwable) {
            Log::channel((string) config('billing.logger'))
                ->warning('Billing lifecycle notification dispatch failed.', [
                    'organization_id' => $organization->getKey(),
                    'event' => $event->value,
                ]);
        }
    }
}
