import { Head, Link, router } from '@inertiajs/react';
import { AlertTriangle, Building2, RefreshCw, Users } from 'lucide-react';
import { useState } from 'react';

import PlatformAlertController from '@/actions/App/Http/Controllers/Platform/PlatformAlertController';
import { DashboardMetricCard } from '@/components/dashboard/dashboard-metric-card';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import type { StatusBadgeProps } from '@/components/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type PlatformMetrics = {
    organizations: {
        total: number;
        active: number;
        inactive: number;
    };
    users: {
        total: number;
        verified: number;
        unverified: number;
    };
    openProblemReports: number;
};

type OpenProblemReport = {
    reference: string;
    title: string | null;
    status: string;
    organizationName: string | null;
    timestamp: string | null;
};

type FailedBillingPayment = {
    organizationName: string;
    provider: string;
    currency: string;
    amountMinor: string;
    failureTimestamp: string | null;
    providerErrorCode: string | null;
};

type AlertSeverity = 'info' | 'warning' | 'critical';

type TopAlert = {
    id: number;
    title: string;
    severity: AlertSeverity;
    typeLabel: string;
    lastSeenAt: string;
};

type AlertSummary = {
    counts: { critical: number; warning: number; info: number };
    topAlerts: TopAlert[];
};

type PlatformDashboardProps = {
    metrics: PlatformMetrics;
    openProblemReports: OpenProblemReport[];
    failedBillingPayments: FailedBillingPayment[];
    alertSummary: AlertSummary;
};

function severityVariant(severity: AlertSeverity): StatusBadgeProps['variant'] {
    return severity === 'critical'
        ? 'danger'
        : severity === 'warning'
          ? 'warning'
          : 'info';
}

/** Format an exact integer minor-unit string without converting it to a JavaScript number. */
function formatMinorUnits(value: string): string {
    return value.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

/** Format an absolute server timestamp in the platform operator's local timezone. */
function formatDateTime(value: string): string {
    return new Intl.DateTimeFormat(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        timeZoneName: 'short',
    }).format(new Date(value));
}

/** Render the bounded, read-only platform owner overview. */
export default function PlatformDashboard({
    metrics,
    openProblemReports,
    failedBillingPayments,
    alertSummary,
}: PlatformDashboardProps) {
    const [refreshing, setRefreshing] = useState(false);

    /** Refresh only platform overview props without introducing background polling. */
    function refreshOverview(): void {
        if (refreshing) {
            return;
        }

        router.reload({
            only: [
                'metrics',
                'openProblemReports',
                'failedBillingPayments',
                'alertSummary',
            ],
            onStart: () => setRefreshing(true),
            onFinish: () => setRefreshing(false),
        });
    }

    const hasOpenAlerts =
        alertSummary.counts.critical > 0 ||
        alertSummary.counts.warning > 0 ||
        alertSummary.counts.info > 0;

    return (
        <>
            <Head title="Platform Console" />

            <div className="flex flex-1 flex-col gap-4 p-4 sm:p-6">
                <PageHeader
                    title="Platform Console"
                    description="Read-only platform overview from locally persisted MiseLedger data."
                    actions={
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            disabled={refreshing}
                            onClick={refreshOverview}
                        >
                            <RefreshCw
                                className={cn(
                                    'size-3.5',
                                    refreshing &&
                                        'animate-spin motion-reduce:animate-none',
                                )}
                                aria-hidden="true"
                            />
                            {refreshing ? 'Refreshing…' : 'Refresh'}
                        </Button>
                    }
                />

                <section
                    aria-labelledby="platform-attention-heading"
                    className="overflow-hidden rounded-xl border border-border bg-card"
                >
                    <div className="flex items-center justify-between gap-3 border-b border-border px-4 py-3">
                        <div>
                            <h2
                                id="platform-attention-heading"
                                className="text-sm font-semibold"
                            >
                                Requires attention
                            </h2>
                            <p className="mt-1 text-xs leading-5 text-muted-foreground">
                                Open platform alerts, most severe first.
                            </p>
                        </div>

                        <Button size="sm" variant="outline" asChild>
                            <Link href={PlatformAlertController.index()}>
                                View all alerts
                            </Link>
                        </Button>
                    </div>

                    <div className="flex flex-wrap gap-2 border-b border-border px-4 py-3">
                        <StatusBadge
                            label={`${alertSummary.counts.critical} critical`}
                            variant="danger"
                        />
                        <StatusBadge
                            label={`${alertSummary.counts.warning} warning`}
                            variant="warning"
                        />
                        <StatusBadge
                            label={`${alertSummary.counts.info} info`}
                            variant="info"
                        />
                    </div>

                    {!hasOpenAlerts ? (
                        <div className="px-4 py-8 text-center">
                            <p className="font-medium">
                                No open platform alerts
                            </p>
                            <p className="mt-1 text-sm text-muted-foreground">
                                No monitored condition currently requires
                                attention.
                            </p>
                        </div>
                    ) : (
                        <ul className="divide-y divide-border">
                            {alertSummary.topAlerts.map((alert) => (
                                <li
                                    key={alert.id}
                                    className="grid gap-3 px-4 py-4 sm:grid-cols-[auto_minmax(0,1fr)_auto] sm:items-center"
                                >
                                    <StatusBadge
                                        label={alert.severity}
                                        variant={severityVariant(
                                            alert.severity,
                                        )}
                                        className="capitalize"
                                    />

                                    <div className="min-w-0">
                                        <Link
                                            href={PlatformAlertController.show(
                                                alert.id,
                                            )}
                                            className="rounded-sm text-sm font-medium break-words hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                        >
                                            {alert.title}
                                        </Link>
                                        <p className="mt-0.5 text-xs text-muted-foreground">
                                            {alert.typeLabel}
                                        </p>
                                    </div>

                                    <time
                                        dateTime={alert.lastSeenAt}
                                        className="text-xs text-muted-foreground sm:text-right"
                                    >
                                        {formatDateTime(alert.lastSeenAt)}
                                    </time>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                <section aria-labelledby="platform-metrics-heading">
                    <h2 id="platform-metrics-heading" className="sr-only">
                        Platform metrics
                    </h2>

                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <DashboardMetricCard
                            title="Organizations"
                            value={metrics.organizations.total}
                            description="All locally persisted organizations."
                            icon={Building2}
                            tone="blue"
                        />

                        <DashboardMetricCard
                            title="Active organizations"
                            value={metrics.organizations.active}
                            description="Administrative active flag is true."
                            icon={Building2}
                            tone="emerald"
                        />

                        <DashboardMetricCard
                            title="Inactive organizations"
                            value={metrics.organizations.inactive}
                            description="Administrative active flag is false."
                            icon={Building2}
                            tone="amber"
                        />

                        <DashboardMetricCard
                            title="Users"
                            value={metrics.users.total}
                            description="All locally persisted user identities."
                            icon={Users}
                            tone="violet"
                        />

                        <DashboardMetricCard
                            title="Verified users"
                            value={metrics.users.verified}
                            description="Email verification timestamp is present."
                            icon={Users}
                            tone="teal"
                        />

                        <DashboardMetricCard
                            title="Unverified users"
                            value={metrics.users.unverified}
                            description="Email verification timestamp is absent."
                            icon={Users}
                            tone="amber"
                        />

                        <DashboardMetricCard
                            title="Open problem reports"
                            value={metrics.openProblemReports}
                            description="Submitted, in review, or in progress only."
                            icon={AlertTriangle}
                            tone="amber"
                        />
                    </div>
                </section>

                <div className="grid gap-4 xl:grid-cols-2">
                    <section
                        aria-labelledby="open-problem-reports-heading"
                        className="overflow-hidden rounded-xl border border-border bg-card"
                    >
                        <div className="border-b border-border px-4 py-3">
                            <h2
                                id="open-problem-reports-heading"
                                className="text-sm font-semibold"
                            >
                                Open problem reports
                            </h2>
                            <p className="mt-1 text-xs leading-5 text-muted-foreground">
                                Five newest submitted, in review, or in progress
                                reports.
                            </p>
                        </div>

                        {openProblemReports.length === 0 ? (
                            <div className="px-4 py-8 text-center">
                                <p className="font-medium">
                                    No open problem reports
                                </p>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    No locally persisted report currently has an
                                    open status.
                                </p>
                            </div>
                        ) : (
                            <ul className="divide-y divide-border">
                                {openProblemReports.map((report) => (
                                    <li
                                        key={report.reference}
                                        className="grid gap-3 px-4 py-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-start"
                                    >
                                        <div className="min-w-0">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <span className="font-mono text-xs text-muted-foreground">
                                                    {report.reference}
                                                </span>

                                                <Badge variant="outline">
                                                    {report.status}
                                                </Badge>
                                            </div>

                                            <p className="mt-2 text-sm font-medium break-words">
                                                {report.title ??
                                                    'Untitled report'}
                                            </p>

                                            <p className="mt-1 text-xs break-words text-muted-foreground">
                                                {report.organizationName ??
                                                    'No organization snapshot'}
                                            </p>
                                        </div>

                                        {report.timestamp !== null ? (
                                            <time
                                                dateTime={report.timestamp}
                                                className="text-xs text-muted-foreground sm:text-right"
                                            >
                                                {formatDateTime(
                                                    report.timestamp,
                                                )}
                                            </time>
                                        ) : (
                                            <span className="text-xs text-muted-foreground sm:text-right">
                                                Timestamp unavailable
                                            </span>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>

                    <section
                        aria-labelledby="failed-payments-heading"
                        className="overflow-hidden rounded-xl border border-border bg-card"
                    >
                        <div className="border-b border-border px-4 py-3">
                            <h2
                                id="failed-payments-heading"
                                className="text-sm font-semibold"
                            >
                                Failed payment attempts
                            </h2>
                            <p className="mt-1 text-xs leading-5 text-muted-foreground">
                                Five newest locally persisted payments whose
                                status is failed.
                            </p>
                        </div>

                        {failedBillingPayments.length === 0 ? (
                            <div className="px-4 py-8 text-center">
                                <p className="font-medium">
                                    No failed payment attempts
                                </p>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    No locally persisted payment currently has
                                    failed status.
                                </p>
                            </div>
                        ) : (
                            <ul className="divide-y divide-border">
                                {failedBillingPayments.map((payment, index) => (
                                    <li
                                        key={`${payment.organizationName}-${payment.provider}-${payment.failureTimestamp ?? 'unknown'}-${index}`}
                                        className="grid gap-3 px-4 py-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-start"
                                    >
                                        <div className="min-w-0">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <p className="text-sm font-medium break-words">
                                                    {payment.organizationName}
                                                </p>

                                                <Badge variant="secondary">
                                                    {payment.provider}
                                                </Badge>
                                            </div>

                                            <p className="mt-2 text-sm tabular-nums">
                                                {payment.currency}{' '}
                                                {formatMinorUnits(
                                                    payment.amountMinor,
                                                )}{' '}
                                                minor units
                                            </p>

                                            <p className="mt-1 font-mono text-xs break-all text-muted-foreground">
                                                Provider error:{' '}
                                                {payment.providerErrorCode ??
                                                    'Not provided'}
                                            </p>
                                        </div>

                                        {payment.failureTimestamp !== null ? (
                                            <time
                                                dateTime={
                                                    payment.failureTimestamp
                                                }
                                                className="text-xs text-muted-foreground sm:text-right"
                                            >
                                                {formatDateTime(
                                                    payment.failureTimestamp,
                                                )}
                                            </time>
                                        ) : (
                                            <span className="text-xs text-muted-foreground sm:text-right">
                                                Failure time unavailable
                                            </span>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>
                </div>
            </div>
        </>
    );
}
