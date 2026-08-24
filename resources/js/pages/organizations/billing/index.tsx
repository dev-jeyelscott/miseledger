import { Head, router } from '@inertiajs/react';
import { CreditCard, ExternalLink } from 'lucide-react';
import OrganizationBillingPortalController from '@/actions/App/Http/Controllers/Billing/OrganizationBillingPortalController';
import OrganizationCheckoutController from '@/actions/App/Http/Controllers/Billing/OrganizationCheckoutController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { dashboard } from '@/routes';
import type {
    OrganizationAvailablePlan,
    OrganizationEntitlementContext,
    OrganizationSubscriptionContext,
    OrganizationSummary,
} from '@/types';

type Props = {
    organization: OrganizationSummary;
    subscription: OrganizationSubscriptionContext;
    entitlements: OrganizationEntitlementContext;
    availablePlans: OrganizationAvailablePlan[];
};

/** Format a plan or status code as a readable label. */
function formatLabel(value: string | null): string {
    if (value === null) {
        return 'None';
    }

    return value
        .split(/[_-]/)
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
}

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
 * Present the organization's backend-derived commercial state and let the
 * billing administrator start Checkout or open Stripe's billing portal.
 * Every restriction shown here is presentation only: the server remains the
 * enforcement boundary for both this page and the actions it triggers.
 */
export default function OrganizationBilling({
    organization,
    subscription,
    entitlements,
    availablePlans,
}: Props) {
    const hasActiveSubscription = subscription.status !== null;
    const trialEndsAt = formatDate(subscription.trialEndsAt);
    const endsAt = formatDate(subscription.endsAt);
    const overLimitKeys = Object.entries(entitlements.usage)
        .filter(([, usage]) => usage.atLimit)
        .map(([key]) => formatLabel(key));

    function subscribe(planCode: string, interval: 'monthly' | 'yearly') {
        router.post(OrganizationCheckoutController.store.url(organization.id), {
            plan: planCode,
            interval,
        });
    }

    function openBillingPortal() {
        router.post(
            OrganizationBillingPortalController.store.url(organization.id),
        );
    }

    return (
        <>
            <Head title="Billing" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="mx-auto w-full max-w-5xl">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Billing
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Manage the subscription for {organization.name}.
                    </p>
                </div>

                <div className="mx-auto grid w-full max-w-5xl gap-6">
                    {subscription.billingWarning && (
                        <div
                            role="alert"
                            className="rounded-lg border border-amber-500/50 bg-amber-500/10 px-4 py-3 text-sm text-amber-700 dark:text-amber-400"
                        >
                            There is a problem with this organization's payment.
                            Please review billing to avoid losing write access.
                        </div>
                    )}

                    {subscription.accessMode === 'read_only' && (
                        <div
                            role="alert"
                            className="rounded-lg border border-destructive/50 bg-destructive/10 px-4 py-3 text-sm text-destructive"
                        >
                            This organization is commercially read-only.
                            Subscribe below to restore write access.
                        </div>
                    )}

                    {overLimitKeys.length > 0 && (
                        <div
                            role="alert"
                            className="rounded-lg border border-amber-500/50 bg-amber-500/10 px-4 py-3 text-sm text-amber-700 dark:text-amber-400"
                        >
                            This organization is at or over its current plan
                            limit for {overLimitKeys.join(', ')}. All existing
                            data remains available, but creating new{' '}
                            {overLimitKeys.join(', ').toLowerCase()} is
                            blocked until you upgrade to a plan with enough
                            capacity.
                        </div>
                    )}

                    <Card>
                        <CardHeader>
                            <div className="flex items-start gap-3">
                                <div className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-muted">
                                    <CreditCard
                                        className="size-5"
                                        aria-hidden="true"
                                    />
                                </div>

                                <div className="grid gap-1">
                                    <CardTitle>Subscription</CardTitle>
                                    <CardDescription>
                                        Current plan and status for this
                                        organization.
                                    </CardDescription>
                                </div>
                            </div>
                        </CardHeader>

                        <CardContent className="space-y-4">
                            <dl className="grid grid-cols-2 gap-4 text-sm sm:grid-cols-3">
                                <div>
                                    <dt className="text-xs font-medium text-muted-foreground">
                                        Plan
                                    </dt>
                                    <dd className="mt-1 font-medium">
                                        {formatLabel(subscription.plan)}
                                    </dd>
                                </div>

                                <div>
                                    <dt className="text-xs font-medium text-muted-foreground">
                                        Status
                                    </dt>
                                    <dd className="mt-1">
                                        <Badge variant="secondary">
                                            {formatLabel(subscription.status)}
                                        </Badge>
                                    </dd>
                                </div>

                                <div>
                                    <dt className="text-xs font-medium text-muted-foreground">
                                        Access
                                    </dt>
                                    <dd className="mt-1">
                                        <Badge
                                            variant={
                                                subscription.accessMode ===
                                                'writable'
                                                    ? 'outline'
                                                    : 'destructive'
                                            }
                                        >
                                            {formatLabel(
                                                subscription.accessMode,
                                            )}
                                        </Badge>
                                    </dd>
                                </div>

                                {subscription.onTrial && trialEndsAt && (
                                    <div>
                                        <dt className="text-xs font-medium text-muted-foreground">
                                            Trial ends
                                        </dt>
                                        <dd className="mt-1 font-medium">
                                            {trialEndsAt}
                                        </dd>
                                    </div>
                                )}

                                {endsAt && (
                                    <div>
                                        <dt className="text-xs font-medium text-muted-foreground">
                                            {subscription.accessMode ===
                                            'writable'
                                                ? 'Renews or cancels on'
                                                : 'Ended on'}
                                        </dt>
                                        <dd className="mt-1 font-medium">
                                            {endsAt}
                                        </dd>
                                    </div>
                                )}
                            </dl>
                        </CardContent>

                        {hasActiveSubscription && (
                            <CardFooter className="justify-end border-t pt-6">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={openBillingPortal}
                                >
                                    <ExternalLink
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Manage billing
                                </Button>
                            </CardFooter>
                        )}
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Plan entitlements</CardTitle>
                            <CardDescription>
                                Features and usage limits granted by the current
                                plan.
                            </CardDescription>
                        </CardHeader>

                        <CardContent className="grid gap-6 sm:grid-cols-2">
                            <div>
                                <p className="text-xs font-medium text-muted-foreground">
                                    Included features
                                </p>

                                {entitlements.features.length > 0 ? (
                                    <ul className="mt-2 space-y-1">
                                        {entitlements.features.map(
                                            (feature) => (
                                                <li
                                                    key={feature}
                                                    className="text-sm"
                                                >
                                                    {formatLabel(feature)}
                                                </li>
                                            ),
                                        )}
                                    </ul>
                                ) : (
                                    <p className="mt-2 text-sm text-muted-foreground">
                                        No features are granted.
                                    </p>
                                )}
                            </div>

                            <div>
                                <p className="text-xs font-medium text-muted-foreground">
                                    Usage limits
                                </p>

                                {Object.keys(entitlements.limits).length > 0 ? (
                                    <dl className="mt-2 space-y-2">
                                        {Object.entries(
                                            entitlements.limits,
                                        ).map(([key, limit]) => {
                                            const usage =
                                                entitlements.usage[key];

                                            return (
                                                <div
                                                    key={key}
                                                    className="flex justify-between gap-4 text-sm"
                                                >
                                                    <dt>{formatLabel(key)}</dt>
                                                    <dd className="text-right font-medium">
                                                        {limit === null ? (
                                                            'Unlimited'
                                                        ) : (
                                                            <>
                                                                {usage?.current ??
                                                                    0}{' '}
                                                                of {limit} used
                                                                {usage?.atLimit && (
                                                                    <span className="ml-2 inline-flex items-center rounded-full bg-destructive/10 px-2 py-0.5 text-xs font-medium text-destructive">
                                                                        Limit
                                                                        reached
                                                                    </span>
                                                                )}
                                                            </>
                                                        )}
                                                    </dd>
                                                </div>
                                            );
                                        })}
                                    </dl>
                                ) : (
                                    <p className="mt-2 text-sm text-muted-foreground">
                                        No limits are configured.
                                    </p>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    {!hasActiveSubscription && (
                        <Card>
                            <CardHeader>
                                <CardTitle>Subscribe</CardTitle>
                                <CardDescription>
                                    Choose a plan to restore or start billing
                                    for this organization.
                                </CardDescription>
                            </CardHeader>

                            <CardContent className="grid gap-4 sm:grid-cols-2">
                                {availablePlans.map((plan) => (
                                    <div
                                        key={plan.code}
                                        className="flex flex-col gap-3 rounded-lg border p-4"
                                    >
                                        <p className="font-medium">
                                            {plan.name}
                                        </p>

                                        <div className="flex flex-wrap gap-2">
                                            {plan.monthly && (
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    onClick={() =>
                                                        subscribe(
                                                            plan.code,
                                                            'monthly',
                                                        )
                                                    }
                                                >
                                                    Subscribe monthly
                                                </Button>
                                            )}

                                            {plan.yearly && (
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() =>
                                                        subscribe(
                                                            plan.code,
                                                            'yearly',
                                                        )
                                                    }
                                                >
                                                    Subscribe yearly
                                                </Button>
                                            )}
                                        </div>
                                    </div>
                                ))}

                                {availablePlans.length === 0 && (
                                    <p className="text-sm text-muted-foreground">
                                        No plans are currently available.
                                    </p>
                                )}
                            </CardContent>
                        </Card>
                    )}
                </div>
            </div>
        </>
    );
}

OrganizationBilling.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
