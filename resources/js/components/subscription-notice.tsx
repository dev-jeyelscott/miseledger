import { Link, usePage } from '@inertiajs/react';
import { AlertTriangle, CreditCard, Lock, X } from 'lucide-react';
import { useState } from 'react';
import OrganizationBillingController from '@/actions/App/Http/Controllers/Billing/OrganizationBillingController';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button, buttonVariants } from '@/components/ui/button';
import { resolveCallToAction, resolveNotice } from '@/lib/subscription-notice';
import { cn } from '@/lib/utils';
import type { OrganizationContext } from '@/types';

const dismissalDuration = 24 * 60 * 60 * 1000;

function dismissalStorageKey(organizationId: number): string {
    return `miseledger:subscription-notice:dismissed:${organizationId}`;
}

function readDismissedUntil(organizationId: number): number | null {
    if (typeof window === 'undefined') {
        return null;
    }

    try {
        const dismissedUntil = Number(
            window.localStorage.getItem(dismissalStorageKey(organizationId)),
        );

        if (dismissedUntil > Date.now()) {
            return dismissedUntil;
        }

        window.localStorage.removeItem(dismissalStorageKey(organizationId));
    } catch {
        return null;
    }

    return null;
}

type PageProps = {
    organizationContext: OrganizationContext;
};

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
    const activeOrganizationId = activeOrganization?.id ?? null;
    const [locallyDismissedOrganizationId, setLocallyDismissedOrganizationId] =
        useState<number | null>(null);

    if (activeOrganization === null || subscription === null) {
        return null;
    }

    const organization = activeOrganization;

    const notice = resolveNotice(subscription);

    if (notice === null) {
        return null;
    }

    const callToAction = resolveCallToAction(organizationContext);

    const storedDismissedUntil =
        activeOrganizationId === null
            ? null
            : readDismissedUntil(activeOrganizationId);
    const isDismissed =
        locallyDismissedOrganizationId === organization.id ||
        storedDismissedUntil !== null;

    if (notice.dismissible && isDismissed) {
        return null;
    }

    const isTrialNotice = notice.dismissible;
    const Icon =
        notice.variant === 'destructive' || isTrialNotice
            ? AlertTriangle
            : Lock;

    function dismissNotice(): void {
        const dismissedUntil = Date.now() + dismissalDuration;

        try {
            window.localStorage.setItem(
                dismissalStorageKey(organization.id),
                String(dismissedUntil),
            );
        } catch {
            // The alert still dismisses for this render if storage is unavailable.
        }

        setLocallyDismissedOrganizationId(organization.id);
    }

    return (
        <Alert
            key={organization.id}
            variant={notice.variant}
            className={cn(
                'mx-4 mt-4 w-auto min-w-0 md:mx-6',
                isTrialNotice &&
                    'border-warning-border bg-warning-subtle text-warning-foreground [&>svg]:text-warning-foreground',
            )}
        >
            <Icon aria-hidden="true" />
            <div className="col-start-2 flex items-start justify-between gap-4">
                <AlertTitle className="min-w-0">{notice.title}</AlertTitle>
                {isTrialNotice && (
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="-mt-1 -mr-1 shrink-0 text-warning-foreground hover:bg-warning-border/50 hover:text-warning-foreground focus-visible:ring-warning-foreground/50"
                        aria-label="Dismiss trial ending soon alert"
                        onClick={dismissNotice}
                    >
                        <X aria-hidden="true" />
                    </Button>
                )}
            </div>
            <AlertDescription
                className={
                    isTrialNotice ? 'text-warning-foreground/80' : undefined
                }
            >
                <p>{notice.description}</p>

                {callToAction.type === 'billing_link' ? (
                    <Link
                        href={OrganizationBillingController.show(
                            callToAction.organizationId,
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
