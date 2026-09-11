import { Head, Link } from '@inertiajs/react';
import {
    AlertTriangle,
    CreditCard,
    FlaskConical,
    ReceiptText,
} from 'lucide-react';

import PlatformBillingController from '@/actions/App/Http/Controllers/Platform/PlatformBillingController';
import { DashboardMetricCard } from '@/components/dashboard/dashboard-metric-card';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';

type Metrics = {
    subscriptionProjections: {
        live: number;
        test: number;
    };
    failedPaymentAttempts: {
        live: number;
        test: number;
    };
};

type PaymentSignal = {
    currency: string;
    livemode: boolean;
    capturedCount: number;
    capturedAmountMinor: string;
    failedCount: number;
};

type PlanMixRow = {
    planCode: string | null;
    planLabel: string;
    provider: string;
    providerLabel: string;
    livemode: boolean;
    subscriptionCount: number;
};

type Props = {
    metrics: Metrics;
    paymentSignals: PaymentSignal[];
    planMix: PlanMixRow[];
};

/** Group an exact integer string for readable display without numeric coercion. */
function groupIntegerDigits(value: string): string {
    return value.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

/** Format supported two-decimal currencies from exact minor-unit strings without floats. */
function formatMinorAmount(amountMinor: string, currency: string): string {
    if (currency !== 'PHP' && currency !== 'USD') {
        return `${currency} ${groupIntegerDigits(amountMinor)} minor units`;
    }

    const negative = amountMinor.startsWith('-');
    const unsigned = negative ? amountMinor.slice(1) : amountMinor;
    const padded = unsigned.padStart(3, '0');
    const major = padded.slice(0, -2);
    const fraction = padded.slice(-2);
    const prefix = negative ? '-' : '';

    return `${currency} ${prefix}${groupIntegerDigits(major)}.${fraction}`;
}

/** Render the read-only billing and captured-payment overview. */
export default function PlatformBillingIndex({
    metrics,
    paymentSignals,
    planMix,
}: Props) {
    return (
        <>
            <Head title="Billing & Revenue" />

            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <PageHeader
                    title="Billing & Revenue"
                    description="Read-only local billing projections. Captured amounts include only paid payment rows with a confirmed paid timestamp, and do not represent MRR, ARR, refunds, fees, taxes, profit, or settlement proceeds."
                    actions={
                        <>
                            <Button variant="outline" asChild>
                                <Link
                                    href={PlatformBillingController.subscriptions()}
                                >
                                    Subscriptions
                                </Link>
                            </Button>
                            <Button variant="outline" asChild>
                                <Link
                                    href={PlatformBillingController.payments()}
                                >
                                    Payments
                                </Link>
                            </Button>
                        </>
                    }
                />

                <section aria-labelledby="billing-overview-metrics-heading">
                    <h2
                        id="billing-overview-metrics-heading"
                        className="sr-only"
                    >
                        Billing overview metrics
                    </h2>

                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <DashboardMetricCard
                            title="Live subscriptions"
                            value={metrics.subscriptionProjections.live}
                            description="Persisted live-mode subscription projections."
                            icon={CreditCard}
                            tone="emerald"
                            href={PlatformBillingController.subscriptions({
                                query: { mode: 'live' },
                            })}
                        />

                        <DashboardMetricCard
                            title="Test subscriptions"
                            value={metrics.subscriptionProjections.test}
                            description="Persisted test-mode subscription projections."
                            icon={FlaskConical}
                            tone="violet"
                            href={PlatformBillingController.subscriptions({
                                query: { mode: 'test' },
                            })}
                        />

                        <DashboardMetricCard
                            title="Live failed attempts"
                            value={metrics.failedPaymentAttempts.live}
                            description="Historical live-mode failed payment attempts."
                            icon={AlertTriangle}
                            tone="amber"
                            href={PlatformBillingController.payments({
                                query: {
                                    status: 'failed',
                                    mode: 'live',
                                },
                            })}
                        />

                        <DashboardMetricCard
                            title="Test failed attempts"
                            value={metrics.failedPaymentAttempts.test}
                            description="Historical test-mode failed payment attempts."
                            icon={AlertTriangle}
                            tone="amber"
                            href={PlatformBillingController.payments({
                                query: {
                                    status: 'failed',
                                    mode: 'test',
                                },
                            })}
                        />
                    </div>
                </section>

                <section
                    aria-labelledby="captured-payments-heading"
                    className="overflow-hidden rounded-xl border border-border bg-card"
                >
                    <div className="border-b border-border px-4 py-3">
                        <div className="flex items-center gap-2">
                            <ReceiptText
                                className="size-4 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <h2
                                id="captured-payments-heading"
                                className="text-sm font-semibold"
                            >
                                Captured payment signals
                            </h2>
                        </div>
                        <p className="mt-1 text-xs leading-5 text-muted-foreground">
                            Currency and live/test mode stay separate. Captured
                            amount requires status paid and a non-null paid_at.
                        </p>
                    </div>

                    {paymentSignals.length === 0 ? (
                        <div className="px-4 py-10 text-center">
                            <p className="font-medium">
                                No payment attempts found
                            </p>
                            <p className="mt-1 text-sm text-muted-foreground">
                                No locally synchronized billing payment rows are
                                available yet.
                            </p>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[680px] text-sm">
                                <caption className="sr-only">
                                    Captured payments and failed attempts by
                                    currency and billing mode
                                </caption>
                                <thead className="border-b border-border bg-muted/30 text-left text-xs text-muted-foreground">
                                    <tr>
                                        <th scope="col" className="px-4 py-3">
                                            Mode
                                        </th>
                                        <th scope="col" className="px-4 py-3">
                                            Currency
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-4 py-3 text-right"
                                        >
                                            Confirmed captures
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-4 py-3 text-right"
                                        >
                                            Captured amount
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-4 py-3 text-right"
                                        >
                                            Failed attempts
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {paymentSignals.map((signal) => (
                                        <tr
                                            key={`${signal.currency}-${signal.livemode ? 'live' : 'test'}`}
                                            className="hover:bg-muted/30"
                                        >
                                            <td className="px-4 py-3">
                                                <StatusBadge
                                                    label={
                                                        signal.livemode
                                                            ? 'Live'
                                                            : 'Test'
                                                    }
                                                    variant={
                                                        signal.livemode
                                                            ? 'success'
                                                            : 'neutral'
                                                    }
                                                />
                                            </td>
                                            <td className="px-4 py-3 font-medium">
                                                {signal.currency}
                                            </td>
                                            <td className="px-4 py-3 text-right tabular-nums">
                                                {signal.capturedCount.toLocaleString()}
                                            </td>
                                            <td className="px-4 py-3 text-right font-medium tabular-nums">
                                                {formatMinorAmount(
                                                    signal.capturedAmountMinor,
                                                    signal.currency,
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-right tabular-nums">
                                                {signal.failedCount.toLocaleString()}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>

                <section
                    aria-labelledby="plan-mix-heading"
                    className="overflow-hidden rounded-xl border border-border bg-card"
                >
                    <div className="border-b border-border px-4 py-3">
                        <h2
                            id="plan-mix-heading"
                            className="text-sm font-semibold"
                        >
                            Subscription plan mix
                        </h2>
                        <p className="mt-1 text-xs leading-5 text-muted-foreground">
                            Plan identity uses persisted internal plan_code
                            only. Provider ownership and live/test mode remain
                            separate dimensions.
                        </p>
                    </div>

                    {planMix.length === 0 ? (
                        <div className="px-4 py-10 text-center">
                            <p className="font-medium">
                                No subscription projections found
                            </p>
                            <p className="mt-1 text-sm text-muted-foreground">
                                No locally persisted billing subscription rows
                                are available yet.
                            </p>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[680px] text-sm">
                                <caption className="sr-only">
                                    Subscription projections grouped by internal
                                    plan code, provider, and billing mode
                                </caption>
                                <thead className="border-b border-border bg-muted/30 text-left text-xs text-muted-foreground">
                                    <tr>
                                        <th scope="col" className="px-4 py-3">
                                            Plan
                                        </th>
                                        <th scope="col" className="px-4 py-3">
                                            Provider
                                        </th>
                                        <th scope="col" className="px-4 py-3">
                                            Mode
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-4 py-3 text-right"
                                        >
                                            Subscriptions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {planMix.map((row) => (
                                        <tr
                                            key={`${row.planCode ?? 'unmapped'}-${row.provider}-${row.livemode ? 'live' : 'test'}`}
                                            className="hover:bg-muted/30"
                                        >
                                            <td className="px-4 py-3">
                                                <p className="font-medium">
                                                    {row.planLabel}
                                                </p>
                                                <p className="mt-0.5 font-mono text-xs text-muted-foreground">
                                                    {row.planCode ??
                                                        'No plan code'}
                                                </p>
                                            </td>
                                            <td className="px-4 py-3">
                                                {row.providerLabel}
                                            </td>
                                            <td className="px-4 py-3">
                                                <StatusBadge
                                                    label={
                                                        row.livemode
                                                            ? 'Live'
                                                            : 'Test'
                                                    }
                                                    variant={
                                                        row.livemode
                                                            ? 'success'
                                                            : 'neutral'
                                                    }
                                                />
                                            </td>
                                            <td className="px-4 py-3 text-right tabular-nums">
                                                {row.subscriptionCount.toLocaleString()}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>
            </div>
        </>
    );
}
