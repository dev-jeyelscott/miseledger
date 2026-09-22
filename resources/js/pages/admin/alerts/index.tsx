import { Form, Head, Link } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';

import PlatformAlertController from '@/actions/App/Http/Controllers/Platform/PlatformAlertController';
import { EmptyState } from '@/components/empty-state';
import { FilterToolbar } from '@/components/filter-toolbar';
import { PageHeader } from '@/components/page-header';
import { PaginationControls } from '@/components/pagination-controls';
import { StatusBadge } from '@/components/status-badge';
import type { StatusBadgeProps } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { NativeSelect } from '@/components/ui/native-select';

type AlertSeverity = 'info' | 'warning' | 'critical';
type AlertState = 'open' | 'resolved';
type Period = '24h' | '7d' | '30d';

type AlertListItem = {
    id: number;
    fingerprint: string;
    type: string;
    typeLabel: string;
    source: string;
    severity: AlertSeverity;
    state: AlertState;
    title: string;
    firstSeenAt: string;
    lastSeenAt: string;
    resolvedAt: string | null;
    occurrenceCount: number;
};

type Pagination = {
    current_page: number;
    from: number | null;
    last_page: number;
    next_page_url: string | null;
    per_page: number;
    prev_page_url: string | null;
    to: number | null;
    total: number;
};

type Filters = {
    state: AlertState | null;
    severity: AlertSeverity | null;
    type: string | null;
    period: Period | null;
    perPage: number;
};

type TypeOption = { value: string; label: string };

type Props = {
    alerts: AlertListItem[];
    pagination: Pagination;
    filters: Filters;
    typeOptions: TypeOption[];
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

/** Render the bounded, searchable platform owner alerts console. */
export default function PlatformAlertsIndex({
    alerts,
    pagination,
    filters,
    typeOptions,
}: Props) {
    const hasQueryState =
        filters.state !== null ||
        filters.severity !== null ||
        filters.type !== null ||
        filters.period !== null ||
        filters.perPage !== 25;

    return (
        <>
            <Head title="Platform Alerts" />

            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <PageHeader
                    title="Alerts"
                    description="Persistent, deduplicated platform conditions derived from authoritative billing, queue, database, and problem-report evidence."
                />

                <section className="overflow-hidden rounded-xl border border-border bg-card">
                    <Form
                        action={PlatformAlertController.index().url}
                        method="get"
                    >
                        {({ processing }) => (
                            <FilterToolbar className="rounded-b-none border-x-0 border-t-0 shadow-none">
                                <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-[minmax(9rem,11rem)_minmax(9rem,11rem)_minmax(11rem,14rem)_minmax(8rem,10rem)_auto]">
                                    <div>
                                        <label
                                            htmlFor="platform-alert-state"
                                            className="sr-only"
                                        >
                                            State
                                        </label>
                                        <NativeSelect
                                            id="platform-alert-state"
                                            name="state"
                                            defaultValue={filters.state ?? ''}
                                        >
                                            <option value="">All states</option>
                                            <option value="open">Open</option>
                                            <option value="resolved">
                                                Resolved
                                            </option>
                                        </NativeSelect>
                                    </div>

                                    <div>
                                        <label
                                            htmlFor="platform-alert-severity"
                                            className="sr-only"
                                        >
                                            Severity
                                        </label>
                                        <NativeSelect
                                            id="platform-alert-severity"
                                            name="severity"
                                            defaultValue={
                                                filters.severity ?? ''
                                            }
                                        >
                                            <option value="">
                                                All severities
                                            </option>
                                            <option value="critical">
                                                Critical
                                            </option>
                                            <option value="warning">
                                                Warning
                                            </option>
                                            <option value="info">Info</option>
                                        </NativeSelect>
                                    </div>

                                    <div>
                                        <label
                                            htmlFor="platform-alert-type"
                                            className="sr-only"
                                        >
                                            Source
                                        </label>
                                        <NativeSelect
                                            id="platform-alert-type"
                                            name="type"
                                            defaultValue={filters.type ?? ''}
                                        >
                                            <option value="">
                                                All sources
                                            </option>
                                            {typeOptions.map((option) => (
                                                <option
                                                    key={option.value}
                                                    value={option.value}
                                                >
                                                    {option.label}
                                                </option>
                                            ))}
                                        </NativeSelect>
                                    </div>

                                    <div>
                                        <label
                                            htmlFor="platform-alert-period"
                                            className="sr-only"
                                        >
                                            Recent period
                                        </label>
                                        <NativeSelect
                                            id="platform-alert-period"
                                            name="period"
                                            defaultValue={filters.period ?? ''}
                                        >
                                            <option value="">All time</option>
                                            <option value="24h">
                                                Last 24 hours
                                            </option>
                                            <option value="7d">
                                                Last 7 days
                                            </option>
                                            <option value="30d">
                                                Last 30 days
                                            </option>
                                        </NativeSelect>
                                    </div>

                                    <div className="flex flex-wrap items-center gap-2 md:col-span-2 xl:col-span-1 xl:justify-end">
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            {processing
                                                ? 'Applying…'
                                                : 'Apply filters'}
                                        </Button>

                                        {hasQueryState ? (
                                            <Button
                                                type="button"
                                                variant="outline"
                                                asChild
                                            >
                                                <Link
                                                    href={PlatformAlertController.index()}
                                                >
                                                    Reset
                                                </Link>
                                            </Button>
                                        ) : null}
                                    </div>
                                </div>
                            </FilterToolbar>
                        )}
                    </Form>

                    {alerts.length === 0 ? (
                        <EmptyState
                            className="px-4 py-14"
                            icon={AlertTriangle}
                            title={
                                hasQueryState
                                    ? 'No alerts match these filters'
                                    : 'No alerts recorded'
                            }
                            description={
                                hasQueryState
                                    ? 'Adjust or clear the current filters to broaden the results.'
                                    : 'No platform condition has opened an alert yet.'
                            }
                        />
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[920px] text-sm">
                                <caption className="sr-only">
                                    Platform alert history
                                </caption>
                                <thead className="border-b border-border bg-muted/30 text-left text-xs text-muted-foreground">
                                    <tr>
                                        <th scope="col" className="px-4 py-3">
                                            Alert
                                        </th>
                                        <th scope="col" className="px-4 py-3">
                                            Severity
                                        </th>
                                        <th scope="col" className="px-4 py-3">
                                            Source
                                        </th>
                                        <th scope="col" className="px-4 py-3">
                                            State
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-4 py-3 text-right"
                                        >
                                            Occurrences
                                        </th>
                                        <th scope="col" className="px-4 py-3">
                                            First seen
                                        </th>
                                        <th scope="col" className="px-4 py-3">
                                            Last seen
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-4 py-3 text-right"
                                        >
                                            <span className="sr-only">
                                                Actions
                                            </span>
                                        </th>
                                    </tr>
                                </thead>

                                <tbody className="divide-y divide-border">
                                    {alerts.map((alert) => (
                                        <tr
                                            key={alert.id}
                                            className="hover:bg-muted/30"
                                        >
                                            <td className="px-4 py-3">
                                                <Link
                                                    href={PlatformAlertController.show(
                                                        alert.id,
                                                    )}
                                                    className="rounded-sm font-medium hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                                >
                                                    {alert.title}
                                                </Link>
                                            </td>

                                            <td className="px-4 py-3">
                                                <StatusBadge
                                                    label={alert.severity}
                                                    variant={severityVariant(
                                                        alert.severity,
                                                    )}
                                                    className="capitalize"
                                                />
                                            </td>

                                            <td className="px-4 py-3 text-muted-foreground">
                                                {alert.typeLabel}
                                            </td>

                                            <td className="px-4 py-3">
                                                <StatusBadge
                                                    label={
                                                        alert.state === 'open'
                                                            ? 'Open'
                                                            : 'Resolved'
                                                    }
                                                    variant={
                                                        alert.state === 'open'
                                                            ? 'warning'
                                                            : 'success'
                                                    }
                                                />
                                            </td>

                                            <td className="px-4 py-3 text-right tabular-nums">
                                                {alert.occurrenceCount.toLocaleString()}
                                            </td>

                                            <td className="px-4 py-3 text-muted-foreground">
                                                {formatDateTime(
                                                    alert.firstSeenAt,
                                                )}
                                            </td>

                                            <td className="px-4 py-3 text-muted-foreground">
                                                {formatDateTime(
                                                    alert.lastSeenAt,
                                                )}
                                            </td>

                                            <td className="px-4 py-3 text-right">
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    asChild
                                                >
                                                    <Link
                                                        href={PlatformAlertController.show(
                                                            alert.id,
                                                        )}
                                                    >
                                                        View
                                                    </Link>
                                                </Button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    <PaginationControls
                        currentPage={pagination.current_page}
                        from={pagination.from}
                        lastPage={pagination.last_page}
                        nextPageUrl={pagination.next_page_url}
                        previousPageUrl={pagination.prev_page_url}
                        to={pagination.to}
                        total={pagination.total}
                        itemLabel="alerts"
                        preserveScroll
                        preserveState
                    />
                </section>
            </div>
        </>
    );
}
