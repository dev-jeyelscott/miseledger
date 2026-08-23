import type {
    OrganizationContext,
    OrganizationSubscriptionContext,
} from '@/types/organization';

export type SubscriptionNoticeContent = {
    variant: 'default' | 'destructive';
    title: string;
    description: string;
};

export type SubscriptionNoticeCallToAction =
    | { type: 'billing_link'; organizationId: number }
    | { type: 'contact_owner' };

function formatDate(value: string | null): string | null {
    if (value === null) {
        return null;
    }

    return new Date(value).toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
}

/**
 * Choose the single highest-priority notice for the active organization's
 * safe, server-derived subscription state. Order matters: an `unpaid`
 * organization is read-only and takes priority over any other framing.
 * Severity for the past-due warning is taken from the server-authoritative
 * `billingWarning` flag rather than re-derived from `status`/`endsAt`, so it
 * cannot be silently suppressed by an unrelated scheduled-cancellation date.
 */
export function resolveNotice(
    subscription: OrganizationSubscriptionContext,
): SubscriptionNoticeContent | null {
    const trialEndsAt = formatDate(subscription.trialEndsAt);
    const endsAt = formatDate(subscription.endsAt);

    if (subscription.status === 'unpaid') {
        return {
            variant: 'destructive',
            title: 'Subscription unpaid',
            description:
                'This organization is read-only because its subscription is unpaid. Mutations are unavailable until billing is resolved.',
        };
    }

    if (subscription.accessMode === 'read_only') {
        return {
            variant: 'destructive',
            title: 'Read-only organization',
            description:
                'This organization is commercially read-only. Mutations are unavailable until an active subscription is restored.',
        };
    }

    if (subscription.status === 'past_due' && subscription.billingWarning) {
        return {
            variant: 'destructive',
            title: 'Payment past due',
            description:
                'This organization has a payment problem. Write access is retained for now, but please resolve billing soon to avoid losing it.',
        };
    }

    if (endsAt !== null) {
        return {
            variant: 'default',
            title: 'Subscription ending',
            description: `This organization's subscription is scheduled to end on ${endsAt}. Write access is retained until then.`,
        };
    }

    if (subscription.onTrial && trialEndsAt !== null) {
        return {
            variant: 'default',
            title: 'Trial ending soon',
            description: `This organization's trial ends on ${trialEndsAt}. Subscribe to keep write access afterward.`,
        };
    }

    return null;
}

/**
 * Determine the notice's call to action for the active organization only.
 * Billing-authorized members are pointed at that organization's own Billing
 * page; every other role receives a non-sensitive owner-contact message.
 */
export function resolveCallToAction(
    organizationContext: Pick<OrganizationContext, 'active' | 'memberships'>,
): SubscriptionNoticeCallToAction {
    const activeOrganization = organizationContext.active;

    const activeMembership = organizationContext.memberships.find(
        (membership) => membership.organization.id === activeOrganization?.id,
    );

    if (
        activeOrganization !== null &&
        (activeMembership?.permissions.includes('billing.manage') ?? false)
    ) {
        return { type: 'billing_link', organizationId: activeOrganization.id };
    }

    return { type: 'contact_owner' };
}
