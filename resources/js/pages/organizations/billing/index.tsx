import { Head, router, useHttp } from '@inertiajs/react';
import {
    CheckCircle2,
    CreditCard,
    ExternalLink,
    RefreshCw,
    XCircle,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import OrganizationBillingCancellationController from '@/actions/App/Http/Controllers/Billing/OrganizationBillingCancellationController';
import OrganizationBillingPortalController from '@/actions/App/Http/Controllers/Billing/OrganizationBillingPortalController';
import OrganizationCheckoutController from '@/actions/App/Http/Controllers/Billing/OrganizationCheckoutController';
import OrganizationInvoicePaymentController from '@/actions/App/Http/Controllers/Billing/OrganizationInvoicePaymentController';
import OrganizationInvoiceStatusController from '@/actions/App/Http/Controllers/Billing/OrganizationInvoiceStatusController';
import OrganizationManualRenewalController from '@/actions/App/Http/Controllers/Billing/OrganizationManualRenewalController';
import OrganizationSubscriptionUpgradeController from '@/actions/App/Http/Controllers/Billing/OrganizationSubscriptionUpgradeController';
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
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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
    manualQrPhEnabled: boolean;
};

type QrPhCheckout = {
    kind?: 'renewal' | 'upgrade';
    invoice_id: number;
    invoice_status: string;
    target_plan?: string | null;
    payment_id: number;
    payment_status: 'awaiting_payment' | 'paid' | 'failed' | 'expired';
    amount: number;
    currency: string;
    qr_code_url: string | null;
    expires_at: string | null;
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

function expiryLabel(value: string | null, now: number): string | null {
    if (value === null) {
        return null;
    }

    const remainingSeconds = Math.max(
        0,
        Math.ceil((new Date(value).getTime() - now) / 1_000),
    );
    const minutes = Math.floor(remainingSeconds / 60);
    const seconds = remainingSeconds % 60;

    return `${minutes}:${seconds.toString().padStart(2, '0')}`;
}

function statusVariant(
    status: string | null,
): 'secondary' | 'outline' | 'destructive' {
    return status === 'unpaid'
        ? 'destructive'
        : status === 'past_due'
          ? 'outline'
          : 'secondary';
}

/**
 * Present the organization's backend-derived commercial state and let the
 * billing administrator start Checkout or use the server-selected management
 * path. Every restriction shown here is presentation only: the server remains
 * the enforcement boundary for both this page and the actions it triggers.
 */
export default function OrganizationBilling({
    organization,
    subscription,
    entitlements,
    availablePlans,
    manualQrPhEnabled,
}: Props) {
    const hasActiveSubscription = subscription.status !== null;
    const upgradeableTo = hasActiveSubscription
        ? availablePlans.filter((plan) => plan.eligibleUpgrade)
        : [];
    const trialEndsAt = formatDate(subscription.trialEndsAt);
    const endsAt = formatDate(subscription.endsAt);
    const nextBillingAt = formatDate(subscription.nextBillingAt);
    const overLimitKeys = Object.entries(entitlements.usage)
        .filter(([, usage]) => usage.atLimit)
        .map(([key]) => formatLabel(key));
    const [checkout, setCheckout] = useState<QrPhCheckout | null>(null);
    const [currentTime, setCurrentTime] = useState(0);
    const [paymentSuccessDialogOpen, setPaymentSuccessDialogOpen] =
        useState(false);
    const announcedPaidPaymentId = useRef<number | null>(null);
    const renewal = useHttp<
        { plan?: string; interval?: 'monthly' | 'yearly' },
        QrPhCheckout
    >({});
    const paymentRetry = useHttp<Record<string, never>, QrPhCheckout>({});
    const upgrade = useHttp<{ plan?: string }, QrPhCheckout>({});
    const paymentStatus = useHttp<
        Record<string, never>,
        Pick<QrPhCheckout, 'invoice_status' | 'payment_status' | 'expires_at'>
    >({});

    useEffect(() => {
        const timer = window.setInterval(
            () => setCurrentTime(Date.now()),
            1_000,
        );

        return () => window.clearInterval(timer);
    }, []);

    useEffect(() => {
        if (
            checkout?.payment_status !== 'paid' ||
            checkout.payment_id === announcedPaidPaymentId.current
        ) {
            return;
        }

        announcedPaidPaymentId.current = checkout.payment_id;
        setPaymentSuccessDialogOpen(true);
    }, [checkout?.payment_id, checkout?.payment_status]);

    function subscribe(planCode: string, interval: 'monthly' | 'yearly') {
        if (manualQrPhEnabled) {
            renewal.setData({ plan: planCode, interval });
            renewal.post(
                OrganizationManualRenewalController.store.url(organization.id),
                {
                    onSuccess: (data) => {
                        setCurrentTime(Date.now());
                        setCheckout(data);
                    },
                },
            );

            return;
        }

        router.post(OrganizationCheckoutController.store.url(organization.id), {
            plan: planCode,
            interval,
        });
    }

    function renewSubscription() {
        renewal.setData({});
        renewal.post(
            OrganizationManualRenewalController.store.url(organization.id),
            {
                onSuccess: (data) => {
                    setCurrentTime(Date.now());
                    setCheckout(data);
                },
            },
        );
    }

    function upgradeTo(planCode: string) {
        upgrade.setData({ plan: planCode });
        upgrade.post(
            OrganizationSubscriptionUpgradeController.store.url(
                organization.id,
            ),
            {
                onSuccess: (data) => {
                    setCurrentTime(Date.now());
                    setCheckout(data);
                },
            },
        );
    }

    function generateNewQr() {
        if (checkout === null) {
            return;
        }

        paymentRetry.post(
            OrganizationInvoicePaymentController.store.url({
                organization: organization.id,
                invoice: checkout.invoice_id,
            }),
            {
                onSuccess: (data) => {
                    setCurrentTime(Date.now());
                    setCheckout(data);
                },
            },
        );
    }

    useEffect(() => {
        const invoiceId = checkout?.invoice_id;
        const paymentStatusValue = checkout?.payment_status;

        if (invoiceId === undefined || paymentStatusValue === 'paid') {
            return;
        }

        const poll = window.setInterval(() => {
            paymentStatus.get(
                OrganizationInvoiceStatusController.show.url({
                    organization: organization.id,
                    invoice: invoiceId,
                }),
                {
                    onSuccess: (data) => {
                        setCheckout((current) =>
                            current === null
                                ? current
                                : {
                                      ...current,
                                      invoice_status: data.invoice_status,
                                      payment_status:
                                          data.payment_status as QrPhCheckout['payment_status'],
                                      expires_at: data.expires_at,
                                  },
                        );
                    },
                },
            );
        }, 3_000);

        return () => window.clearInterval(poll);
    }, [
        checkout?.invoice_id,
        checkout?.payment_status,
        organization.id,
        paymentStatus,
    ]);

    function openBillingPortal() {
        router.post(
            OrganizationBillingPortalController.store.url(organization.id),
        );
    }

    function cancelPayMongoSubscription() {
        if (
            !window.confirm(
                'Cancel renewal? Paid access remains available until the end of the current billing period.',
            )
        ) {
            return;
        }

        router.post(
            OrganizationBillingCancellationController.store.url(
                organization.id,
            ),
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
                            {overLimitKeys.join(', ').toLowerCase()} is blocked
                            until you upgrade to a plan with enough capacity.
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
                                        {subscription.planName ??
                                            formatLabel(subscription.plan)}
                                    </dd>
                                </div>

                                <div>
                                    <dt className="text-xs font-medium text-muted-foreground">
                                        Status
                                    </dt>
                                    <dd className="mt-1">
                                        <Badge
                                            variant={statusVariant(
                                                subscription.status,
                                            )}
                                        >
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

                                {subscription.interval && (
                                    <div>
                                        <dt className="text-xs font-medium text-muted-foreground">
                                            Billing interval
                                        </dt>
                                        <dd className="mt-1 font-medium">
                                            {formatLabel(subscription.interval)}
                                        </dd>
                                    </div>
                                )}

                                {nextBillingAt && (
                                    <div>
                                        <dt className="text-xs font-medium text-muted-foreground">
                                            Next billing date
                                        </dt>
                                        <dd className="mt-1 font-medium">
                                            {nextBillingAt}
                                        </dd>
                                    </div>
                                )}
                            </dl>
                        </CardContent>

                        {subscription.management !== 'none' && (
                            <CardFooter className="justify-end border-t pt-6">
                                {subscription.management === 'portal' ? (
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
                                ) : (
                                    <Button
                                        type="button"
                                        variant="destructive"
                                        onClick={cancelPayMongoSubscription}
                                    >
                                        <XCircle
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        Cancel renewal
                                    </Button>
                                )}
                            </CardFooter>
                        )}

                        {subscription.collectionMethod === 'manual' && (
                            <CardFooter className="justify-end border-t pt-6">
                                <Button
                                    type="button"
                                    onClick={renewSubscription}
                                    disabled={renewal.processing}
                                >
                                    <RefreshCw
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    {renewal.processing
                                        ? 'Creating QR Ph code…'
                                        : 'Renew subscription'}
                                </Button>
                            </CardFooter>
                        )}
                    </Card>

                    {hasActiveSubscription &&
                        manualQrPhEnabled &&
                        upgradeableTo.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Upgrade plan</CardTitle>
                                    <CardDescription>
                                        Move this organization to a higher plan.
                                        Your current billing interval stays the
                                        same.
                                    </CardDescription>
                                </CardHeader>

                                <CardContent className="grid gap-4 sm:grid-cols-2">
                                    {upgradeableTo.map((plan) => (
                                        <div
                                            key={plan.code}
                                            className="flex flex-col gap-3 rounded-lg border p-4"
                                        >
                                            <p className="font-medium">
                                                {plan.name}
                                            </p>

                                            <Button
                                                type="button"
                                                size="sm"
                                                disabled={upgrade.processing}
                                                onClick={() =>
                                                    upgradeTo(plan.code)
                                                }
                                            >
                                                {upgrade.processing
                                                    ? 'Starting upgrade…'
                                                    : `Upgrade to ${plan.name}`}
                                            </Button>
                                        </div>
                                    ))}
                                </CardContent>
                            </Card>
                        )}

                    {checkout !== null && (
                        <Card>
                            <CardHeader>
                                <CardTitle>QR Ph payment</CardTitle>
                                <CardDescription>
                                    Scan this code using a QR Ph-supported bank
                                    or wallet. Payment is confirmed only after
                                    it is synchronized with MiseLedger.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid justify-items-center gap-4 text-center">
                                <p className="text-xl font-semibold">
                                    {new Intl.NumberFormat('en-PH', {
                                        style: 'currency',
                                        currency: checkout.currency,
                                    }).format(checkout.amount / 100)}
                                </p>

                                {checkout.payment_status === 'paid' ? (
                                    <div className="grid justify-items-center gap-2 text-emerald-700 dark:text-emerald-400">
                                        <CheckCircle2
                                            className="size-12"
                                            aria-hidden="true"
                                        />
                                        <p className="font-medium">
                                            {checkout.kind === 'upgrade'
                                                ? 'Payment received. Your subscription has been upgraded.'
                                                : 'Payment received. Your subscription has been renewed.'}
                                        </p>
                                    </div>
                                ) : checkout.qr_code_url !== null &&
                                  checkout.payment_status ===
                                      'awaiting_payment' ? (
                                    <img
                                        src={checkout.qr_code_url}
                                        alt="QR Ph payment code"
                                        className="size-64 rounded-lg border bg-white p-2"
                                    />
                                ) : (
                                    <p className="text-sm text-muted-foreground">
                                        This QR Ph code is no longer usable.
                                        Generate a new code to continue.
                                    </p>
                                )}

                                {checkout.payment_status ===
                                    'awaiting_payment' && (
                                    <p className="text-sm text-muted-foreground">
                                        {expiryLabel(
                                            checkout.expires_at,
                                            currentTime,
                                        ) === '0:00'
                                            ? 'This QR Ph code has expired. Generate a new code to continue.'
                                            : `Waiting for payment confirmation… Expires in ${expiryLabel(checkout.expires_at, currentTime) ?? '30:00'}.`}
                                    </p>
                                )}
                            </CardContent>
                            {checkout.payment_status !== 'paid' && (
                                <CardFooter className="justify-end border-t pt-6">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={generateNewQr}
                                        disabled={paymentRetry.processing}
                                    >
                                        <RefreshCw
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        {paymentRetry.processing
                                            ? 'Creating QR Ph code…'
                                            : 'Generate new QR'}
                                    </Button>
                                </CardFooter>
                            )}
                        </Card>
                    )}

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

            <Dialog
                open={paymentSuccessDialogOpen}
                onOpenChange={setPaymentSuccessDialogOpen}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader className="items-center text-center sm:items-center sm:text-center">
                        <CheckCircle2
                            className="size-14 text-emerald-600 dark:text-emerald-400"
                            aria-hidden="true"
                        />
                        <DialogTitle>Payment received</DialogTitle>
                        <DialogDescription>
                            {checkout?.kind === 'upgrade'
                                ? 'Your QR Ph payment has been confirmed and your subscription has been upgraded.'
                                : 'Your QR Ph payment has been confirmed and your subscription has been renewed.'}
                        </DialogDescription>
                    </DialogHeader>
                    {checkout !== null && (
                        <p className="text-center text-2xl font-semibold">
                            {new Intl.NumberFormat('en-PH', {
                                style: 'currency',
                                currency: checkout.currency,
                            }).format(checkout.amount / 100)}
                        </p>
                    )}
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button">Done</Button>
                        </DialogClose>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
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
