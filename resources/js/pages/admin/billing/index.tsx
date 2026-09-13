import { Head, Link } from '@inertiajs/react';
import { AlertTriangle, CreditCard, ReceiptText } from 'lucide-react';

import PlatformBillingController from '@/actions/App/Http/Controllers/Platform/PlatformBillingController';
import { DashboardMetricCard } from '@/components/dashboard/dashboard-metric-card';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { formatBillingMinorAmount } from '@/lib/billing-money';

type BillingMode = 'live' | 'test';

type Period = {
    from: string;
    toExclusive: string;
    fromDate: string;
    toDate: string;
    timezone: 'UTC';
};

type Props = {
    scope: {
        mode: BillingMode;
        period: Period;
    };
    metrics: {
        subscriptionProjections: number;
        capturedPayments: number;
        failedPaymentAttempts: number;
    };
    paymentSignals: {
        currency: string;
        capturedCount: number;
        capturedAmountMinor: string;
    }[];
    planMix: {
        planCode: string;
        planLabel: string;
        subscriptionCount: number;
    }[];
};

function formatUtcDate(value: string): string {
    return new Intl.DateTimeFormat(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        timeZone: 'UTC',
    }).format(new Date(`${value}T00:00:00Z`));
}

/** Render the current-month read-only billing intelligence overview. */
export default function PlatformBillingIndex({
    scope,
    metrics,
    paymentSignals,
    planMix,
}: Props) {
    const modeLabel = scope.mode === 'live' ? 'Live' : 'Test';

    return (
        <>
            <Head title="Billing & Revenue" />

            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <PageHeader
                    title="Billing & Revenue"
                    description="Read-only local billing intelligence. Captured amounts require a paid BillingPayment row with a confirmed paid timestamp. No MRR, ARR, refunds, fees, taxes, profit, settlement, or provider API calls are inferred here."
                    actions={
                        <>
                            <Button variant="outline" asChild>
                                <Link
                                    href={PlatformBillingController.subscriptions(
                                        {
                                            query: { mode: scope.mode },
                                        },
                                    )}
                                >
                                    Subscriptions
                                </Link>
                            </Button>
                            <Button variant="outline" asChild>
                                <Link
                                    href={PlatformBillingController.payments({
                                        query: { mode: scope.mode },
                                    })}
                                >
                                    Payments
                                </Link>
                            </Button>
                        </>
                    }
                />

                <section
                    aria-labelledby="billing-scope-heading"
                    className="rounded-xl border border-border bg-card p-4"
                >
                    <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <h2
                                id="billing-scope-heading"
                                className="text-sm font-semibold"
                            >
                                Reporting scope
                            </h2>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {modeLabel} mode, current calendar month in UTC:{' '}
                                {formatUtcDate(scope.period.fromDate)} through{' '}
                                {formatUtcDate(scope.period.toDate)}. The next
                                month boundary is exclusive.
                            </p>
                        </div>

                        <div
                            className="flex flex-wrap gap-2"
                            role="group"
                            aria-label="Billing mode"
                        >
                            {(['live', 'test'] as const).map((mode) => (
                                <Button
                                    key={mode}
                                    variant={
                                        scope.mode === mode
                                            ? 'default'
                                            : 'outline'
                                    }
                                    asChild
                                >
                                    <Link
                                        href={PlatformBillingController.index({
                                            query: { mode },
                                        })}
                                        aria-current={
                                            scope.mode === mode
                                                ? 'page'
                                                : undefined
                                        }
                                    >
                                        {mode === 'live' ? 'Live' : 'Test'}
                                    </Link>
                                </Button>
                            ))}
                        </div>
                    </div>
                </section>

                <section aria-labelledby="billing-metrics-heading">
                    <h2 id="billing-metrics-heading" className="sr-only">
                        Billing metrics
                    </h2>

                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        <DashboardMetricCard
                            title={`${modeLabel} subscriptions`}
                            value={metrics.subscriptionProjections}
                            description="Persisted subscription projections in the selected mode."
                            icon={CreditCard}
                            tone="blue"
                            href={PlatformBillingController.subscriptions({
                                query: { mode: scope.mode },
                            })}
                        />
                        <DashboardMetricCard
                            title="Confirmed captures"
                            value={metrics.capturedPayments}
                            description="Paid rows with paid_at inside the selected UTC month."
                            icon={ReceiptText}
                            tone="emerald"
                            href={PlatformBillingController.payments({
                                query: {
                                    status: 'paid',
                                    mode: scope.mode,
                                },
                            })}
                        />
                        <DashboardMetricCard
                            title="Failed attempts"
                            value={metrics.failedPaymentAttempts}
                            description="Failed rows with failed_at inside the selected UTC month."
                            icon={AlertTriangle}
                            tone="amber"
                            href={PlatformBillingController.payments({
                                query: {
                                    status: 'failed',
                                    mode: scope.mode,
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
                        <div className="flex flex-wrap items-center gap-2">
                            <h2
                                id="captured-payments-heading"
                                className="text-sm font-semibold"
                            >
                                Confirmed captures by currency
                            </h2>
                            <StatusBadge
                                label={modeLabel}
                                variant={
                                    scope.mode === 'live'
                                        ? 'success'
                                        : 'neutral'
                                }
                            />
                        </div>
                        <p className="mt-1 text-xs text-muted-foreground">
                            No FX conversion or cross-currency monetary total is
                            calculated.
                        </p>
                    </div>

                    {paymentSignals.length === 0 ? (
                        <div className="px-4 py-10 text-center">
                            <p className="font-medium">
                                No confirmed captures in this period
                            </p>
                            <p className="mt-1 text-sm text-muted-foreground">
                                No qualifying paid payment is inside the
                                selected UTC month and mode.
                            </p>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[560px] text-sm">
                                <caption className="sr-only">
                                    Confirmed captures grouped by currency
                                </caption>
                                <thead className="border-b border-border bg-muted/30 text-left text-xs text-muted-foreground">
                                    <tr>
                                        <th scope="col" className="px-4 py-3">
                                            Currency
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-4 py-3 text-right"
                                        >
                                            Captures
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-4 py-3 text-right"
                                        >
                                            Captured amount
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {paymentSignals.map((signal) => (
                                        <tr
                                            key={signal.currency}
                                            className="hover:bg-muted/30"
                                        >
                                            <td className="px-4 py-3 font-medium">
                                                {signal.currency}
                                            </td>
                                            <td className="px-4 py-3 text-right tabular-nums">
                                                {signal.capturedCount.toLocaleString()}
                                            </td>
                                            <td className="px-4 py-3 text-right font-medium tabular-nums">
                                                {formatBillingMinorAmount(
                                                    signal.capturedAmountMinor,
                                                    signal.currency,
                                                )}
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
                        <p className="mt-1 text-xs text-muted-foreground">
                            Grouped only by stable internal plan_code inside the
                            selected billing mode.
                        </p>
                    </div>

                    {planMix.length === 0 ? (
                        <div className="px-4 py-10 text-center">
                            <p className="font-medium">
                                No valid plan projections found
                            </p>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[520px] text-sm">
                                <caption className="sr-only">
                                    Subscription projections by internal plan
                                    code
                                </caption>
                                <thead className="border-b border-border bg-muted/30 text-left text-xs text-muted-foreground">
                                    <tr>
                                        <th scope="col" className="px-4 py-3">
                                            Plan
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
                                            key={row.planCode}
                                            className="hover:bg-muted/30"
                                        >
                                            <td className="px-4 py-3">
                                                <p className="font-medium">
                                                    {row.planLabel}
                                                </p>
                                                <p className="mt-0.5 font-mono text-xs text-muted-foreground">
                                                    {row.planCode}
                                                </p>
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
