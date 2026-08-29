import { Form, Head, Link, router } from '@inertiajs/react';
import {
    CheckCircle2,
    ClipboardList,
    Clock3,
    Eye,
    Info,
    Pencil,
    Plus,
    RotateCcw,
    TriangleAlert,
    X,
} from 'lucide-react';
import { useEffect, useState } from 'react';

import StockCountController from '@/actions/App/Http/Controllers/Inventory/StockCountController';
import { DashboardMetricCard } from '@/components/dashboard/dashboard-metric-card';
import { EmptyState } from '@/components/empty-state';
import { FilterToolbar } from '@/components/filter-toolbar';
import { PageHeader } from '@/components/page-header';
import { PaginationControls } from '@/components/pagination-controls';
import { StatusBadge } from '@/components/status-badge';
import type { StatusBadgeProps } from '@/components/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';

type StockCountStatus = 'draft' | 'submitted' | 'finalized' | 'cancelled';
type StockCountView =
    | 'all'
    | 'open'
    | 'draft'
    | 'submitted'
    | 'finalized'
    | 'cancelled'
    | 'variance';
type StockCountSort =
    'latest' | 'number' | 'status' | 'counted_at' | 'finalized_at';
type SortDirection = 'asc' | 'desc';

type StockCountRow = {
    id: number;
    number: string;
    status: StockCountStatus;
    locationName: string;
    storageLocationName: string;
    countedByName: string | null;
    countedAt: string | null;
    finalizedAt: string | null;
    varianceItemCount: number | null;
};

type StockCountSummary = {
    totalCount: number;
    openCount: number;
    finalizedTodayCount: number;
    varianceAlertCount: number;
};

type Pagination = {
    currentPage: number;
    from: number | null;
    lastPage: number;
    nextPageUrl: string | null;
    perPage: number;
    previousPageUrl: string | null;
    to: number | null;
    total: number;
};

type LocationOption = {
    id: number;
    name: string;
};

type StorageLocationOption = {
    id: number;
    locationId: number;
    name: string;
};

type Filters = {
    search: string | null;
    view: StockCountView;
    locationId: number | null;
    storageLocationId: number | null;
    from: string | null;
    to: string | null;
    sort: StockCountSort;
    direction: SortDirection;
    perPage: number;
};

type Props = {
    rows: StockCountRow[];
    pagination: Pagination;
    summary: StockCountSummary;
    locationOptions: LocationOption[];
    storageLocationOptions: StorageLocationOption[];
    filters: Filters;
    timezone: string;
    canCreate: boolean;
    canViewReport: boolean;
};

const quickViews: Array<{ label: string; value: StockCountView }> = [
    { label: 'All', value: 'all' },
    { label: 'Open', value: 'open' },
    { label: 'Finalized', value: 'finalized' },
    { label: 'Variance', value: 'variance' },
];

const viewLabels: Record<StockCountView, string> = {
    all: 'All statuses',
    open: 'Open',
    draft: 'Draft',
    submitted: 'Submitted',
    finalized: 'Finalized',
    cancelled: 'Cancelled',
    variance: 'Has variance',
};

/** Format workflow timestamps consistently in the active organization's timezone. */
function formatOrganizationDate(value: string, timezone: string): string {
    return new Intl.DateTimeFormat('en-US', {
        month: 'numeric',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        timeZone: timezone,
    }).format(new Date(value));
}

/** Resolve the canonical semantic badge treatment for a stock-count lifecycle. */
function stockCountStatusVariant(
    status: StockCountStatus,
): StatusBadgeProps['variant'] {
    if (status === 'finalized') {
        return 'success';
    }

    if (status === 'submitted') {
        return 'info';
    }

    if (status === 'cancelled') {
        return 'danger';
    }

    return 'neutral';
}

/** Produce the visible lifecycle label independently from its visual treatment. */
function stockCountStatusLabel(status: StockCountStatus): string {
    return status.charAt(0).toUpperCase() + status.slice(1);
}

/** Expose the current server sort state to assistive technology. */
function ariaSortFor(
    sort: StockCountSort,
    activeSort: StockCountSort,
    direction: SortDirection,
): 'ascending' | 'descending' | 'none' {
    if (sort !== activeSort) {
        return 'none';
    }

    return direction === 'asc' ? 'ascending' : 'descending';
}

/** Render one server-authoritative Stock Counts operational index. */
export default function StockCountIndex({
    rows,
    pagination,
    summary,
    locationOptions,
    storageLocationOptions,
    filters,
    timezone,
    canCreate,
    canViewReport,
}: Props) {
    const [isNavigating, setIsNavigating] = useState(false);

    const hasFilters =
        filters.search !== null ||
        filters.view !== 'all' ||
        filters.locationId !== null ||
        filters.storageLocationId !== null ||
        filters.from !== null ||
        filters.to !== null;

    const emptyStateTitle = hasFilters
        ? 'No stock counts match these filters.'
        : 'No stock counts yet.';
    const emptyStateDescription = hasFilters
        ? 'Adjust or reset the filters to see other stock-count history.'
        : canCreate
          ? 'Create a stock count when you are ready to reconcile physical inventory.'
          : 'Stock counts will appear here when they are available.';

    const selectedLocation = locationOptions.find(
        (location) => location.id === filters.locationId,
    );
    const selectedStorageLocation = storageLocationOptions.find(
        (storage) => storage.id === filters.storageLocationId,
    );

    const activeFilters = [
        filters.search !== null
            ? { label: `Search: ${filters.search}`, key: 'search' }
            : null,
        filters.view !== 'all'
            ? { label: `Status: ${viewLabels[filters.view]}`, key: 'view' }
            : null,
        selectedLocation !== undefined
            ? {
                  label: `Location: ${selectedLocation.name}`,
                  key: 'location_id',
              }
            : null,
        selectedStorageLocation !== undefined
            ? {
                  label: `Storage: ${selectedStorageLocation.name}`,
                  key: 'storage_location_id',
              }
            : null,
        filters.from !== null
            ? { label: `From: ${filters.from}`, key: 'from' }
            : null,
        filters.to !== null ? { label: `To: ${filters.to}`, key: 'to' } : null,
    ].filter(
        (value): value is { label: string; key: string } => value !== null,
    );

    /** Preserve every current server-backed query parameter while changing requested values. */
    const hrefFor = (
        changes: Record<string, string | number | null>,
    ): string => {
        const params = new URLSearchParams();

        if (filters.search !== null) {
            params.set('search', filters.search);
        }

        params.set('view', filters.view);

        if (filters.locationId !== null) {
            params.set('location_id', filters.locationId.toString());
        }

        if (filters.storageLocationId !== null) {
            params.set(
                'storage_location_id',
                filters.storageLocationId.toString(),
            );
        }

        if (filters.from !== null) {
            params.set('from', filters.from);
        }

        if (filters.to !== null) {
            params.set('to', filters.to);
        }

        params.set('sort', filters.sort);
        params.set('direction', filters.direction);
        params.set('per_page', filters.perPage.toString());

        Object.entries(changes).forEach(([key, value]) => {
            if (value === null || value === '') {
                params.delete(key);

                return;
            }

            params.set(key, value.toString());
        });

        return `${StockCountController.index().url}?${params.toString()}`;
    };

    /** Toggle only the requested server-side sort while preserving all other query state. */
    const sortHref = (sort: StockCountSort): string => {
        const direction =
            filters.sort === sort && filters.direction === 'asc'
                ? 'desc'
                : 'asc';

        return hrefFor({
            sort,
            direction,
            page: null,
        });
    };

    useEffect(() => {
        const removeStartListener = router.on('start', () => {
            setIsNavigating(true);
        });
        const removeFinishListener = router.on('finish', () => {
            setIsNavigating(false);
        });

        return () => {
            removeStartListener();
            removeFinishListener();
        };
    }, []);

    return (
        <>
            <Head title="Stock counts" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Stock counts"
                    description="Reconcile physical stock with the inventory ledger."
                    actions={
                        <>
                            {canViewReport && (
                                <Button variant="outline" asChild>
                                    <Link
                                        href={StockCountController.variance()}
                                    >
                                        <TriangleAlert
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        Variance report
                                    </Link>
                                </Button>
                            )}

                            {canCreate && (
                                <Button asChild>
                                    <Link href={StockCountController.create()}>
                                        <Plus
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        New stock count
                                    </Link>
                                </Button>
                            )}
                        </>
                    }
                />

                <div
                    aria-live="polite"
                    aria-atomic="true"
                    className="min-h-5 text-sm text-muted-foreground"
                >
                    {isNavigating ? 'Updating stock count results…' : null}
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <DashboardMetricCard
                        title="Total counts"
                        value={summary.totalCount.toLocaleString()}
                        description="All stock-count workflows"
                        icon={ClipboardList}
                        tone="blue"
                    />

                    <DashboardMetricCard
                        title="Open counts"
                        value={summary.openCount.toLocaleString()}
                        description="Draft or submitted counts"
                        icon={Clock3}
                        tone="amber"
                    />

                    <DashboardMetricCard
                        title="Finalized today"
                        value={summary.finalizedTodayCount.toLocaleString()}
                        description={`Today in ${timezone}`}
                        icon={CheckCircle2}
                        tone="emerald"
                    />

                    <DashboardMetricCard
                        title="Variance alerts"
                        value={summary.varianceAlertCount.toLocaleString()}
                        description="Finalized counts with persisted variance"
                        icon={TriangleAlert}
                        tone="amber"
                    />
                </div>

                <section
                    aria-label="Stock count discovery controls"
                    className="overflow-hidden rounded-xl border border-border bg-card"
                >
                    <Form
                        action={StockCountController.index().url}
                        method="get"
                    >
                        {({ errors, processing }) => (
                            <FilterToolbar className="rounded-none border-x-0 border-t-0">
                                <input
                                    type="hidden"
                                    name="sort"
                                    value={filters.sort}
                                />
                                <input
                                    type="hidden"
                                    name="direction"
                                    value={filters.direction}
                                />
                                <input
                                    type="hidden"
                                    name="per_page"
                                    value={filters.perPage}
                                />

                                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-[1.45fr_0.9fr_0.95fr_0.95fr_0.8fr_0.8fr_auto]">
                                    <Field
                                        id="stock-count-search"
                                        label="Search"
                                        error={errors.search}
                                    >
                                        <Input
                                            type="search"
                                            name="search"
                                            defaultValue={filters.search ?? ''}
                                            placeholder="Number, location, or storage"
                                            autoComplete="off"
                                        />
                                    </Field>

                                    <Field
                                        id="stock-count-view"
                                        label="Status"
                                        error={errors.view}
                                    >
                                        <NativeSelect
                                            name="view"
                                            defaultValue={filters.view}
                                            className="motion-reduce:transition-none"
                                        >
                                            <option value="all">
                                                All statuses
                                            </option>
                                            <option value="open">
                                                Open (draft + submitted)
                                            </option>
                                            <option value="draft">Draft</option>
                                            <option value="submitted">
                                                Submitted
                                            </option>
                                            <option value="finalized">
                                                Finalized
                                            </option>
                                            <option value="cancelled">
                                                Cancelled
                                            </option>
                                            <option value="variance">
                                                Has variance
                                            </option>
                                        </NativeSelect>
                                    </Field>

                                    <Field
                                        id="stock-count-location"
                                        label="Location"
                                        error={errors.location_id}
                                    >
                                        <NativeSelect
                                            name="location_id"
                                            defaultValue={
                                                filters.locationId?.toString() ??
                                                ''
                                            }
                                            className="motion-reduce:transition-none"
                                        >
                                            <option value="">
                                                All locations
                                            </option>
                                            {locationOptions.map((location) => (
                                                <option
                                                    key={location.id}
                                                    value={location.id}
                                                >
                                                    {location.name}
                                                </option>
                                            ))}
                                        </NativeSelect>
                                    </Field>

                                    <Field
                                        id="stock-count-storage-location"
                                        label="Storage"
                                        error={errors.storage_location_id}
                                    >
                                        <NativeSelect
                                            name="storage_location_id"
                                            defaultValue={
                                                filters.storageLocationId?.toString() ??
                                                ''
                                            }
                                            className="motion-reduce:transition-none"
                                        >
                                            <option value="">
                                                All storage
                                            </option>
                                            {storageLocationOptions.map(
                                                (storage) => (
                                                    <option
                                                        key={storage.id}
                                                        value={storage.id}
                                                    >
                                                        {storage.name}
                                                    </option>
                                                ),
                                            )}
                                        </NativeSelect>
                                    </Field>

                                    <Field
                                        id="stock-count-from"
                                        label="Counted from"
                                        error={errors.from}
                                    >
                                        <Input
                                            type="date"
                                            name="from"
                                            defaultValue={filters.from ?? ''}
                                        />
                                    </Field>

                                    <Field
                                        id="stock-count-to"
                                        label="Counted to"
                                        error={errors.to}
                                    >
                                        <Input
                                            type="date"
                                            name="to"
                                            defaultValue={filters.to ?? ''}
                                        />
                                    </Field>

                                    <div className="flex items-end gap-2 md:col-span-2 xl:col-span-1">
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                            className="min-w-28 flex-1 xl:flex-none"
                                        >
                                            {processing
                                                ? 'Applying…'
                                                : 'Apply filters'}
                                        </Button>

                                        <Button
                                            type="button"
                                            variant="outline"
                                            className="flex-1 xl:flex-none"
                                            asChild
                                        >
                                            <Link
                                                href={StockCountController.index()}
                                            >
                                                <RotateCcw
                                                    className="size-4"
                                                    aria-hidden="true"
                                                />
                                                Reset
                                            </Link>
                                        </Button>
                                    </div>
                                </div>

                                {activeFilters.length > 0 && (
                                    <div
                                        className="mt-4 flex flex-wrap items-center gap-2 border-t border-border pt-4"
                                        aria-label="Active filters"
                                    >
                                        <span className="text-xs font-medium text-muted-foreground">
                                            Active filters
                                        </span>
                                        {activeFilters.map((filter) => (
                                            <Button
                                                key={filter.key}
                                                variant="outline"
                                                size="sm"
                                                className="h-7 gap-1 px-2 text-xs"
                                                asChild
                                            >
                                                <Link
                                                    href={hrefFor({
                                                        [filter.key]: null,
                                                        page: null,
                                                    })}
                                                    preserveScroll
                                                    preserveState
                                                >
                                                    {filter.label}
                                                    <X
                                                        className="size-3"
                                                        aria-hidden="true"
                                                    />
                                                    <span className="sr-only">
                                                        Remove {filter.label}{' '}
                                                        filter
                                                    </span>
                                                </Link>
                                            </Button>
                                        ))}
                                    </div>
                                )}

                                {Object.keys(errors).length > 0 && (
                                    <div
                                        role="alert"
                                        className="mt-4 rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive"
                                    >
                                        One or more stock-count filters are
                                        invalid. Review the values or reset the
                                        filters and try again.
                                    </div>
                                )}
                            </FilterToolbar>
                        )}
                    </Form>

                    <nav
                        aria-label="Stock count views"
                        className="flex gap-1 overflow-x-auto border-t border-border px-4 pt-2"
                    >
                        {quickViews.map((view) => {
                            const active = filters.view === view.value;

                            return (
                                <Link
                                    key={view.value}
                                    href={hrefFor({
                                        view: view.value,
                                        page: null,
                                    })}
                                    preserveScroll
                                    preserveState
                                    aria-current={active ? 'page' : undefined}
                                    className={cn(
                                        'border-b-2 px-3 py-2 text-sm font-medium whitespace-nowrap transition-colors focus-visible:rounded-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none motion-reduce:transition-none',
                                        active
                                            ? 'border-primary text-foreground'
                                            : 'border-transparent text-muted-foreground hover:text-foreground',
                                    )}
                                >
                                    {view.label}
                                </Link>
                            );
                        })}
                    </nav>
                </section>

                <section
                    className="overflow-hidden rounded-xl border border-border bg-card"
                    aria-labelledby="stock-counts-history-title"
                    aria-busy={isNavigating}
                >
                    <div className="flex min-h-14 flex-wrap items-center justify-between gap-3 border-b border-border px-4 py-3">
                        <div>
                            <h2
                                id="stock-counts-history-title"
                                className="text-sm font-semibold"
                            >
                                Stock count history
                            </h2>
                            <p className="mt-0.5 text-xs text-muted-foreground">
                                Physical-count workflow evidence for the active
                                organization.
                            </p>
                        </div>

                        <Badge variant="outline">
                            {pagination.total.toLocaleString()}{' '}
                            {pagination.total === 1 ? 'count' : 'counts'}
                        </Badge>
                    </div>

                    {rows.length === 0 ? (
                        <div className="px-4 py-12 md:hidden">
                            <EmptyState
                                icon={ClipboardList}
                                title={emptyStateTitle}
                                description={emptyStateDescription}
                            />
                        </div>
                    ) : (
                        <div className="divide-y divide-border md:hidden">
                            {rows.map((row) => {
                                const canEditDraft =
                                    canCreate && row.status === 'draft';

                                return (
                                    <article
                                        key={row.id}
                                        className="space-y-4 p-4"
                                        aria-labelledby={`stock-count-${row.id}-number`}
                                    >
                                        <div className="flex items-start justify-between gap-3">
                                            <div className="min-w-0">
                                                <Link
                                                    id={`stock-count-${row.id}-number`}
                                                    href={StockCountController.edit(
                                                        row.id,
                                                    )}
                                                    className="font-medium text-foreground underline-offset-4 hover:underline focus-visible:rounded-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                                >
                                                    {row.number}
                                                </Link>
                                                <p className="mt-1 text-sm text-muted-foreground">
                                                    {row.locationName} ·{' '}
                                                    {row.storageLocationName}
                                                </p>
                                            </div>

                                            <StatusBadge
                                                label={stockCountStatusLabel(
                                                    row.status,
                                                )}
                                                variant={stockCountStatusVariant(
                                                    row.status,
                                                )}
                                            />
                                        </div>

                                        <dl className="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                                            <div>
                                                <dt className="text-xs text-muted-foreground">
                                                    Counted by
                                                </dt>
                                                <dd className="mt-1">
                                                    {row.countedByName ?? '—'}
                                                </dd>
                                            </div>

                                            <div>
                                                <dt className="text-xs text-muted-foreground">
                                                    Variance
                                                </dt>
                                                <dd
                                                    className={cn(
                                                        'mt-1 tabular-nums',
                                                        row.varianceItemCount !==
                                                            null &&
                                                            row.varianceItemCount >
                                                                0 &&
                                                            'font-medium text-destructive',
                                                        row.varianceItemCount ===
                                                            0 &&
                                                            'text-success-foreground',
                                                    )}
                                                >
                                                    {row.varianceItemCount ===
                                                    null
                                                        ? '—'
                                                        : `${row.varianceItemCount.toLocaleString()} ${
                                                              row.varianceItemCount ===
                                                              1
                                                                  ? 'item'
                                                                  : 'items'
                                                          }`}
                                                </dd>
                                            </div>

                                            <div>
                                                <dt className="text-xs text-muted-foreground">
                                                    Counted
                                                </dt>
                                                <dd className="mt-1 tabular-nums">
                                                    {row.countedAt === null ? (
                                                        '—'
                                                    ) : (
                                                        <time
                                                            dateTime={
                                                                row.countedAt
                                                            }
                                                        >
                                                            {formatOrganizationDate(
                                                                row.countedAt,
                                                                timezone,
                                                            )}
                                                        </time>
                                                    )}
                                                </dd>
                                            </div>

                                            <div>
                                                <dt className="text-xs text-muted-foreground">
                                                    Finalized
                                                </dt>
                                                <dd className="mt-1 tabular-nums">
                                                    {row.finalizedAt ===
                                                    null ? (
                                                        '—'
                                                    ) : (
                                                        <time
                                                            dateTime={
                                                                row.finalizedAt
                                                            }
                                                        >
                                                            {formatOrganizationDate(
                                                                row.finalizedAt,
                                                                timezone,
                                                            )}
                                                        </time>
                                                    )}
                                                </dd>
                                            </div>
                                        </dl>

                                        <div className="flex justify-end border-t border-border pt-3">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                asChild
                                            >
                                                <Link
                                                    href={StockCountController.edit(
                                                        row.id,
                                                    )}
                                                >
                                                    {canEditDraft ? (
                                                        <Pencil
                                                            className="size-3.5"
                                                            aria-hidden="true"
                                                        />
                                                    ) : (
                                                        <Eye
                                                            className="size-3.5"
                                                            aria-hidden="true"
                                                        />
                                                    )}
                                                    {canEditDraft
                                                        ? 'Edit count'
                                                        : 'View count'}
                                                </Link>
                                            </Button>
                                        </div>
                                    </article>
                                );
                            })}
                        </div>
                    )}

                    <div className="hidden overflow-x-auto md:block">
                        <table className="w-full min-w-[1260px] text-sm">
                            <caption className="sr-only">
                                Stock counts showing location, storage, status,
                                actors, timestamps, and persisted variance
                                evidence.
                            </caption>
                            <thead className="bg-muted/40 text-left text-xs text-muted-foreground">
                                <tr className="border-b border-border">
                                    <th
                                        scope="col"
                                        aria-sort={ariaSortFor(
                                            'number',
                                            filters.sort,
                                            filters.direction,
                                        )}
                                        className="px-4 py-3"
                                    >
                                        <Link
                                            href={sortHref('number')}
                                            preserveScroll
                                            preserveState
                                            className="inline-flex items-center gap-1.5 font-medium text-foreground underline-offset-4 hover:underline focus-visible:rounded-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                        >
                                            Number
                                        </Link>
                                    </th>
                                    <th scope="col" className="px-4 py-3">
                                        Location
                                    </th>
                                    <th scope="col" className="px-4 py-3">
                                        Storage
                                    </th>
                                    <th
                                        scope="col"
                                        aria-sort={ariaSortFor(
                                            'status',
                                            filters.sort,
                                            filters.direction,
                                        )}
                                        className="px-4 py-3"
                                    >
                                        <Link
                                            href={sortHref('status')}
                                            preserveScroll
                                            preserveState
                                            className="inline-flex items-center gap-1.5 font-medium text-foreground underline-offset-4 hover:underline focus-visible:rounded-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                        >
                                            Status
                                        </Link>
                                    </th>
                                    <th scope="col" className="px-4 py-3">
                                        Counted by
                                    </th>
                                    <th
                                        scope="col"
                                        aria-sort={ariaSortFor(
                                            'counted_at',
                                            filters.sort,
                                            filters.direction,
                                        )}
                                        className="px-4 py-3"
                                    >
                                        <Link
                                            href={sortHref('counted_at')}
                                            preserveScroll
                                            preserveState
                                            className="inline-flex items-center gap-1.5 font-medium text-foreground underline-offset-4 hover:underline focus-visible:rounded-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                        >
                                            Counted
                                        </Link>
                                    </th>
                                    <th
                                        scope="col"
                                        aria-sort={ariaSortFor(
                                            'finalized_at',
                                            filters.sort,
                                            filters.direction,
                                        )}
                                        className="px-4 py-3"
                                    >
                                        <Link
                                            href={sortHref('finalized_at')}
                                            preserveScroll
                                            preserveState
                                            className="inline-flex items-center gap-1.5 font-medium text-foreground underline-offset-4 hover:underline focus-visible:rounded-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                        >
                                            Finalized
                                        </Link>
                                    </th>
                                    <th scope="col" className="px-4 py-3">
                                        Variance
                                    </th>
                                    <th
                                        scope="col"
                                        className="w-16 px-4 py-3 text-right"
                                    >
                                        Actions
                                    </th>
                                </tr>
                            </thead>

                            <tbody>
                                {rows.length === 0 ? (
                                    <tr>
                                        <td colSpan={9} className="px-6 py-14">
                                            <EmptyState
                                                icon={ClipboardList}
                                                title={emptyStateTitle}
                                                description={
                                                    emptyStateDescription
                                                }
                                            />
                                        </td>
                                    </tr>
                                ) : (
                                    rows.map((row) => {
                                        const canEditDraft =
                                            canCreate && row.status === 'draft';

                                        return (
                                            <tr
                                                key={row.id}
                                                className="border-b border-border transition-colors last:border-b-0 hover:bg-muted/30 motion-reduce:transition-none"
                                            >
                                                <td className="px-4 py-3 font-medium">
                                                    <Link
                                                        href={StockCountController.edit(
                                                            row.id,
                                                        )}
                                                        className="text-foreground underline-offset-4 hover:underline focus-visible:rounded-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                                    >
                                                        {row.number}
                                                    </Link>
                                                </td>
                                                <td className="px-4 py-3">
                                                    {row.locationName}
                                                </td>
                                                <td className="px-4 py-3">
                                                    {row.storageLocationName}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <StatusBadge
                                                        label={stockCountStatusLabel(
                                                            row.status,
                                                        )}
                                                        variant={stockCountStatusVariant(
                                                            row.status,
                                                        )}
                                                    />
                                                </td>
                                                <td className="px-4 py-3">
                                                    {row.countedByName ?? (
                                                        <span className="text-muted-foreground">
                                                            —
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="px-4 py-3 whitespace-nowrap tabular-nums">
                                                    {row.countedAt === null ? (
                                                        <span className="text-muted-foreground">
                                                            —
                                                        </span>
                                                    ) : (
                                                        <time
                                                            dateTime={
                                                                row.countedAt
                                                            }
                                                        >
                                                            {formatOrganizationDate(
                                                                row.countedAt,
                                                                timezone,
                                                            )}
                                                        </time>
                                                    )}
                                                </td>
                                                <td className="px-4 py-3 whitespace-nowrap tabular-nums">
                                                    {row.finalizedAt ===
                                                    null ? (
                                                        <span className="text-muted-foreground">
                                                            —
                                                        </span>
                                                    ) : (
                                                        <time
                                                            dateTime={
                                                                row.finalizedAt
                                                            }
                                                        >
                                                            {formatOrganizationDate(
                                                                row.finalizedAt,
                                                                timezone,
                                                            )}
                                                        </time>
                                                    )}
                                                </td>
                                                <td className="px-4 py-3 whitespace-nowrap tabular-nums">
                                                    {row.varianceItemCount ===
                                                    null ? (
                                                        <span className="text-muted-foreground">
                                                            —
                                                        </span>
                                                    ) : row.varianceItemCount ===
                                                      0 ? (
                                                        <span className="text-success-foreground">
                                                            0 items
                                                        </span>
                                                    ) : (
                                                        <span className="font-medium text-destructive">
                                                            {row.varianceItemCount.toLocaleString()}{' '}
                                                            {row.varianceItemCount ===
                                                            1
                                                                ? 'item'
                                                                : 'items'}
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="px-4 py-2 text-right">
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        asChild
                                                    >
                                                        <Link
                                                            href={StockCountController.edit(
                                                                row.id,
                                                            )}
                                                            aria-label={`${canEditDraft ? 'Edit' : 'View'} ${row.number}`}
                                                        >
                                                            {canEditDraft ? (
                                                                <Pencil
                                                                    className="size-4"
                                                                    aria-hidden="true"
                                                                />
                                                            ) : (
                                                                <Eye
                                                                    className="size-4"
                                                                    aria-hidden="true"
                                                                />
                                                            )}
                                                        </Link>
                                                    </Button>
                                                </td>
                                            </tr>
                                        );
                                    })
                                )}
                            </tbody>
                        </table>
                    </div>

                    <div className="flex items-start gap-2 border-t border-info-border bg-info-subtle px-4 py-3 text-sm text-info-foreground">
                        <Info
                            className="mt-0.5 size-4 shrink-0"
                            aria-hidden="true"
                        />
                        <p>
                            Finalized counts are locked for audit integrity and
                            cannot be edited. Record later corrections through a
                            new stock count or the appropriate inventory
                            adjustment workflow.
                        </p>
                    </div>

                    {pagination.total > 0 && (
                        <>
                            <div className="flex justify-end border-t border-border px-4 py-3">
                                <label className="flex items-center gap-2 text-sm text-muted-foreground">
                                    <span>Rows per page</span>
                                    <NativeSelect
                                        aria-label="Rows per page"
                                        value={pagination.perPage}
                                        className="h-8 w-auto min-w-20 motion-reduce:transition-none"
                                        onChange={(event) => {
                                            router.visit(
                                                hrefFor({
                                                    per_page:
                                                        event.currentTarget
                                                            .value,
                                                    page: null,
                                                }),
                                                {
                                                    preserveScroll: true,
                                                    preserveState: true,
                                                },
                                            );
                                        }}
                                    >
                                        <option value="10">10</option>
                                        <option value="25">25</option>
                                        <option value="50">50</option>
                                    </NativeSelect>
                                </label>
                            </div>

                            {pagination.lastPage > 1 ? (
                                <PaginationControls
                                    currentPage={pagination.currentPage}
                                    from={pagination.from}
                                    lastPage={pagination.lastPage}
                                    nextPageUrl={pagination.nextPageUrl}
                                    previousPageUrl={pagination.previousPageUrl}
                                    to={pagination.to}
                                    total={pagination.total}
                                    itemLabel="counts"
                                    preserveScroll
                                    preserveState
                                />
                            ) : (
                                <div className="border-t border-border px-4 py-3">
                                    <p className="text-sm text-muted-foreground">
                                        Showing {pagination.from ?? 0} to{' '}
                                        {pagination.to ?? 0} of{' '}
                                        {pagination.total.toLocaleString()}{' '}
                                        {pagination.total === 1
                                            ? 'count'
                                            : 'counts'}
                                    </p>
                                </div>
                            )}
                        </>
                    )}
                </section>
            </div>
        </>
    );
}

StockCountIndex.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
        {
            title: 'Stock counts',
            href: StockCountController.index(),
        },
    ],
};
