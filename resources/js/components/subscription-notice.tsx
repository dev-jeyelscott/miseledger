import { Link, usePage } from '@inertiajs/react';
import { AlertTriangle, CreditCard, Lock } from 'lucide-react';
import OrganizationBillingController from '@/actions/App/Http/Controllers/Billing/OrganizationBillingController';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { buttonVariants } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { OrganizationContext } from '@/types';

type PageProps = {
    organizationContext: OrganizationContext;
};

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

type NoticeContent = {
    variant: 'default' | 'destructive';
    title: string;
    description: string;
};

/**
 * Choose the single highest-priority notice for the active organization's
 * safe, server-derived subscription state. Order matters: an `unpaid`
 * organization is read-only and takes priority over any other framing.
 * Severity for the past-due warning is taken from the server-authoritative
 * `billingWarning` flag rather than re-derived from `status`/`endsAt`, so it
 * cannot be silently suppressed by an unrelated scheduled-cancellation date.
 */
function resolveNotice(
    subscription: NonNullable<OrganizationContext['subscription']>,
): NoticeContent | null {
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
 * Render a persistent, active-organization-scoped subscription notice
 * driven solely by the safe P2 subscription context shared to every
 * authenticated page. Never reads or infers authorization: the server
 * remains the sole enforcement boundary for any restriction described here.
 */
export function SubscriptionNotice() {
    const { organizationContext } = usePage<PageProps>().props;

    const activeOrganization = organizationContext.active;
    const subscription = organizationContext.subscription;

    if (activeOrganization === null || subscription === null) {
        return null;
    }

    const notice = resolveNotice(subscription);

    if (notice === null) {
        return null;
    }

    const activeMembership = organizationContext.memberships.find(
        (membership) => membership.organization.id === activeOrganization.id,
    );

    const canManageBilling =
        activeMembership?.permissions.includes('billing.manage') ?? false;

    const Icon = notice.variant === 'destructive' ? AlertTriangle : Lock;

    return (
        <Alert
            key={activeOrganization.id}
            variant={notice.variant}
            className="mx-4 mt-4 md:mx-6"
        >
            <Icon aria-hidden="true" />
            <AlertTitle>{notice.title}</AlertTitle>
            <AlertDescription>
                <p>{notice.description}</p>

                {canManageBilling ? (
                    <Link
                        href={OrganizationBillingController.show(
                            activeOrganization.id,
                        )}
                        className={cn(
                            buttonVariants({
                                variant: 'outline',
                                size: 'sm',
                            }),
                            'mt-2',
                        )}
                    >
                        <CreditCard aria-hidden="true" />
                        Go to Billing
                    </Link>
                ) : (
                    <p className="mt-2 text-xs">
                        Contact your organization owner to update billing.
                    </p>
                )}
            </AlertDescription>
        </Alert>
    );
}
