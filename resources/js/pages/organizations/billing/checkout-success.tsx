import { Head, Link, router, usePoll } from '@inertiajs/react';
import { CheckCircle2, CreditCard, Loader2, RefreshCw } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { FormEvent } from 'react';
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
import settings from '@/routes/organizations/settings';
import type {
    OrganizationSubscriptionContext,
    OrganizationSummary,
} from '@/types';

type Props = {
    organization: OrganizationSummary;
    subscription: OrganizationSubscriptionContext;
    synchronized: boolean;
    payment: {
        paymentIntentId: string;
        clientKey: string;
        publicKey: string;
        apiBaseUrl: string;
    } | null;
};

const POLL_INTERVAL_MS = 4000;
/** Stop automatic polling after this many attempts (~1 minute) and fall back to manual refresh. */
const MAX_POLL_ATTEMPTS = 15;

/** Format a plan or status code as a readable label. */
function formatLabel(value: string | null): string {
    if (value === null) {
        return 'Unknown';
    }

    return value
        .split(/[_-]/)
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
}

/**
 * Show the post-Checkout outcome using only the locally synchronized
 * subscription state re-read on every visit. The Stripe redirect itself is
 * never treated as proof of activation: while webhook synchronization is
 * still pending, a processing state is shown with safe refresh and bounded
 * polling instead of fabricating access.
 */
export default function OrganizationCheckoutSuccess({
    organization,
    subscription,
    synchronized: isSynchronized,
    payment,
}: Props) {
    const [pollAttempts, setPollAttempts] = useState(0);
    const pollExhausted = pollAttempts >= MAX_POLL_ATTEMPTS;

    const poll = usePoll(
        POLL_INTERVAL_MS,
        {
            only: ['subscription', 'synchronized'],
            onFinish: () => setPollAttempts((attempts) => attempts + 1),
        },
        { autoStart: !isSynchronized },
    );

    useEffect(() => {
        if (isSynchronized || pollExhausted) {
            poll.stop();
        }
    }, [isSynchronized, pollExhausted, poll]);

    async function submitPayMongoCardPayment(
        event: FormEvent<HTMLFormElement>,
    ) {
        event.preventDefault();

        if (payment === null) {
            return;
        }

        const formData = new FormData(event.currentTarget);
        const authorization = `Basic ${btoa(`${payment.publicKey}:`)}`;

        const paymentMethodResponse = await fetch(
            `${payment.apiBaseUrl}/payment_methods`,
            {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    Authorization: authorization,
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    data: {
                        attributes: {
                            type: 'card',
                            details: {
                                card_number: formData.get('cardNumber'),
                                exp_month: Number(formData.get('expiryMonth')),
                                exp_year: Number(formData.get('expiryYear')),
                                cvc: formData.get('cvc'),
                            },
                        },
                    },
                }),
            },
        );

        const paymentMethod = await paymentMethodResponse.json();
        const paymentMethodId = paymentMethod?.data?.id;

        if (!paymentMethodResponse.ok || typeof paymentMethodId !== 'string') {
            throw new Error('Unable to create the payment method.');
        }

        const paymentIntentResponse = await fetch(
            `${payment.apiBaseUrl}/payment_intents/${payment.paymentIntentId}/attach`,
            {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    Authorization: authorization,
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    data: {
                        attributes: {
                            payment_method: paymentMethodId,
                            client_key: payment.clientKey,
                            return_url: window.location.href,
                        },
                    },
                }),
            },
        );

        const paymentIntent = await paymentIntentResponse.json();
        const redirectUrl =
            paymentIntent?.data?.attributes?.next_action?.redirect?.url;

        if (!paymentIntentResponse.ok) {
            throw new Error('Unable to start the payment.');
        }

        if (typeof redirectUrl === 'string') {
            window.location.assign(redirectUrl);

            return;
        }

        router.reload({ only: ['subscription', 'synchronized'] });
    }

    return (
        <>
            <Head title="Checkout complete" />

            <div className="flex flex-1 items-center justify-center p-4 md:p-6">
                <Card className="w-full max-w-md">
                    <CardHeader>
                        <div className="flex items-start gap-3">
                            <div className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-muted">
                                {isSynchronized ? (
                                    <CheckCircle2
                                        className="size-5 text-emerald-600"
                                        aria-hidden="true"
                                    />
                                ) : (
                                    <Loader2
                                        className="size-5 animate-spin text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                )}
                            </div>

                            <div className="grid gap-1">
                                <CardTitle>
                                    {isSynchronized
                                        ? 'Subscription confirmed'
                                        : 'Confirming your subscription'}
                                </CardTitle>
                                <CardDescription>
                                    {isSynchronized
                                        ? `Subscription payment was confirmed for ${organization.name}.`
                                        : `We're still waiting for an authoritative subscription update for ${organization.name}.`}
                                </CardDescription>
                            </div>
                        </div>
                    </CardHeader>

                    <CardContent className="space-y-4">
                        {isSynchronized ? (
                            <dl className="grid grid-cols-2 gap-4 text-sm">
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
                            </dl>
                        ) : (
                            <div className="grid gap-4">
                                {payment !== null && (
                                    <form
                                        className="grid gap-3"
                                        onSubmit={(event) => {
                                            void submitPayMongoCardPayment(
                                                event,
                                            );
                                        }}
                                    >
                                        <p className="text-sm text-muted-foreground">
                                            Enter your card details to complete
                                            the first payment. Card details are
                                            sent directly to PayMongo.
                                        </p>
                                        <label className="grid gap-2 text-sm font-medium">
                                            Card number
                                            <input
                                                className="h-9 rounded-md border bg-background px-3"
                                                inputMode="numeric"
                                                name="cardNumber"
                                                required
                                            />
                                        </label>
                                        <div className="grid grid-cols-2 gap-3">
                                            <label className="grid gap-2 text-sm font-medium">
                                                Expiry month
                                                <input
                                                    className="h-9 rounded-md border bg-background px-3"
                                                    inputMode="numeric"
                                                    max="12"
                                                    min="1"
                                                    name="expiryMonth"
                                                    required
                                                />
                                            </label>
                                            <label className="grid gap-2 text-sm font-medium">
                                                Expiry year
                                                <input
                                                    className="h-9 rounded-md border bg-background px-3"
                                                    inputMode="numeric"
                                                    name="expiryYear"
                                                    required
                                                />
                                            </label>
                                        </div>
                                        <label className="grid gap-2 text-sm font-medium">
                                            Security code
                                            <input
                                                className="h-9 rounded-md border bg-background px-3"
                                                inputMode="numeric"
                                                name="cvc"
                                                required
                                                type="password"
                                            />
                                        </label>
                                        <Button type="submit">
                                            <CreditCard aria-hidden="true" />
                                            Continue to payment
                                        </Button>
                                    </form>
                                )}
                                <p
                                    className="text-sm text-muted-foreground"
                                    aria-live="polite"
                                >
                                    {pollExhausted
                                        ? "We're still waiting for the provider to synchronize billing. Automatic checking has stopped; use the refresh button below when you're ready to check again."
                                        : 'This page checks server-owned billing state automatically. A completed browser payment does not activate access until lifecycle synchronization confirms it.'}
                                </p>
                            </div>
                        )}
                    </CardContent>

                    <CardFooter className="flex-wrap justify-end gap-2 border-t pt-6">
                        {!isSynchronized && (
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() =>
                                    router.reload({
                                        only: ['subscription', 'synchronized'],
                                    })
                                }
                            >
                                <RefreshCw
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Refresh status
                            </Button>
                        )}

                        <Button asChild>
                            <Link href={settings.edit.url(organization.id)}>
                                Go to organization settings
                            </Link>
                        </Button>
                    </CardFooter>
                </Card>
            </div>
        </>
    );
}
