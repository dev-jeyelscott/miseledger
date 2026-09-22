import { Head, Link } from '@inertiajs/react';
import { ExternalLink } from 'lucide-react';

import PlatformAlertController from '@/actions/App/Http/Controllers/Platform/PlatformAlertController';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import type { StatusBadgeProps } from '@/components/status-badge';
import { Button } from '@/components/ui/button';

type AlertSeverity = 'info' | 'warning' | 'critical';
type AlertState = 'open' | 'resolved';

type AlertDetail = {
    id: number;
    fingerprint: string;
    type: string;
    typeLabel: string;
    source: string;
    severity: AlertSeverity;
    state: AlertState;
    title: string;
    summary: string;
    context: Record<string, unknown>;
    firstSeenAt: string;
    lastSeenAt: string;
    resolvedAt: string | null;
    occurrenceCount: number;
    targetUrl: string;
};

type HistoryEntry = {
    id: number;
    state: AlertState;
    severity: AlertSeverity;
    firstSeenAt: string;
    lastSeenAt: string;
    resolvedAt: string | null;
    occurrenceCount: number;
};

type Props = {
    alert: AlertDetail;
    history: HistoryEntry[];
};

/** Format a server timestamp in the platform operator's local timezone. */
function formatDateTime(value: string | null): string {
    if (value === null) {
        return 'Not resolved';
    }

    return new Intl.DateTimeFormat(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        timeZoneName: 'short',
    }).format(new Date(value));
}

function severityVariant(severity: AlertSeverity): StatusBadgeProps['variant'] {
    return severity === 'critical'
        ? 'danger'
        : severity === 'warning'
          ? 'warning'
          : 'info';
}

/** Render one platform alert's safe evidence, current state, and lifecycle history. */
export default function PlatformAlertShow({ alert, history }: Props) {
    return (
        <>
            <Head title={`Alert: ${alert.title}`} />

            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <PageHeader
                    title={alert.title}
                    description={`${alert.typeLabel} · ${alert.source}`}
                    actions={
                        <Button variant="outline" asChild>
                            <Link href={PlatformAlertController.index()}>
                                Back to alerts
                            </Link>
                        </Button>
                    }
                />

                <section className="grid gap-4 lg:grid-cols-3">
                    <div className="rounded-xl border border-border bg-card p-4 lg:col-span-2">
                        <div className="flex flex-wrap items-center gap-2">
                            <StatusBadge
                                label={alert.severity}
                                variant={severityVariant(alert.severity)}
                                className="capitalize"
                            />
                            <StatusBadge
                                label={
                                    alert.state === 'open' ? 'Open' : 'Resolved'
                                }
                                variant={
                                    alert.state === 'open'
                                        ? 'warning'
                                        : 'success'
                                }
                            />
                        </div>

                        <p className="mt-4 text-sm leading-6 text-foreground">
                            {alert.summary}
                        </p>

                        <dl className="mt-6 grid gap-4 sm:grid-cols-2">
                            <div>
                                <dt className="text-xs text-muted-foreground">
                                    First seen
                                </dt>
                                <dd className="mt-0.5 text-sm">
                                    {formatDateTime(alert.firstSeenAt)}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs text-muted-foreground">
                                    Last seen
                                </dt>
                                <dd className="mt-0.5 text-sm">
                                    {formatDateTime(alert.lastSeenAt)}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs text-muted-foreground">
                                    Resolved
                                </dt>
                                <dd className="mt-0.5 text-sm">
                                    {formatDateTime(alert.resolvedAt)}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs text-muted-foreground">
                                    Occurrences
                                </dt>
                                <dd className="mt-0.5 text-sm tabular-nums">
                                    {alert.occurrenceCount.toLocaleString()}
                                </dd>
                            </div>
                        </dl>

                        <div className="mt-6">
                            <Button asChild>
                                <Link href={alert.targetUrl}>
                                    View related console page
                                    <ExternalLink
                                        className="size-3.5"
                                        aria-hidden="true"
                                    />
                                </Link>
                            </Button>
                        </div>
                    </div>

                    <div className="rounded-xl border border-border bg-card p-4">
                        <h2 className="text-sm font-semibold">Safe evidence</h2>
                        <p className="mt-1 text-xs text-muted-foreground">
                            Minimized context only. No secrets, raw payloads, or
                            stack traces are ever recorded here.
                        </p>
                        <pre className="mt-3 max-h-96 overflow-auto rounded-md bg-muted p-3 text-xs">
                            {JSON.stringify(alert.context, null, 2)}
                        </pre>
                    </div>
                </section>

                <section
                    aria-labelledby="alert-history-heading"
                    className="overflow-hidden rounded-xl border border-border bg-card"
                >
                    <div className="border-b border-border px-4 py-3">
                        <h2
                            id="alert-history-heading"
                            className="text-sm font-semibold"
                        >
                            Prior occurrences of this condition
                        </h2>
                        <p className="mt-1 text-xs leading-5 text-muted-foreground">
                            Every prior open/resolved lifecycle for this
                            fingerprint. History is never deleted, even after
                            recovery.
                        </p>
                    </div>

                    {history.length === 0 ? (
                        <div className="px-4 py-8 text-center">
                            <p className="font-medium">No prior occurrences</p>
                            <p className="mt-1 text-sm text-muted-foreground">
                                This is the only recorded lifecycle for this
                                condition so far.
                            </p>
                        </div>
                    ) : (
                        <ul className="divide-y divide-border">
                            {history.map((entry) => (
                                <li
                                    key={entry.id}
                                    className="grid gap-2 px-4 py-4 sm:grid-cols-[auto_minmax(0,1fr)_auto] sm:items-center"
                                >
                                    <div className="flex flex-wrap items-center gap-2">
                                        <StatusBadge
                                            label={entry.severity}
                                            variant={severityVariant(
                                                entry.severity,
                                            )}
                                            className="capitalize"
                                        />
                                        <StatusBadge
                                            label={
                                                entry.state === 'open'
                                                    ? 'Open'
                                                    : 'Resolved'
                                            }
                                            variant={
                                                entry.state === 'open'
                                                    ? 'warning'
                                                    : 'success'
                                            }
                                        />
                                    </div>

                                    <p className="text-xs text-muted-foreground">
                                        First seen{' '}
                                        {formatDateTime(entry.firstSeenAt)},
                                        last seen{' '}
                                        {formatDateTime(entry.lastSeenAt)}
                                        {entry.resolvedAt !== null
                                            ? `, resolved ${formatDateTime(entry.resolvedAt)}`
                                            : ''}
                                    </p>

                                    <span className="text-xs text-muted-foreground tabular-nums sm:text-right">
                                        {entry.occurrenceCount.toLocaleString()}{' '}
                                        occurrence(s)
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </>
    );
}
