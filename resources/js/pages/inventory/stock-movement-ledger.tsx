import { Form, Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowDownToLine,
    ArrowLeftRight,
    ArrowUpFromLine,
    CircleMinus,
    CirclePlus,
    Download,
    Filter,
    Package,
    RotateCcw,
    Search,
    Trash2,
} from 'lucide-react';

import InventoryItemController from '@/actions/App/Http/Controllers/Inventory/InventoryItemController';
import StockMovementLedgerReportController from '@/actions/App/Http/Controllers/Inventory/StockMovementLedgerReportController';
import { DashboardMetricCard } from '@/components/dashboard/dashboard-metric-card';
import { EmptyState } from '@/components/empty-state';
import { FilterToolbar } from '@/components/filter-toolbar';
import { PageHeader } from '@/components/page-header';
import { PaginationControls } from '@/components/pagination-controls';
import { StatusBadge } from '@/components/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { dashboard } from '@/routes';
import type { OrganizationContext } from '@/types';

type StockMovementRow = {
    id: number;
    occurredAt: string;
    locationId: number;
    locationName: string;
    storageLocationId: number;
    storageLocationName: string;
    itemId: number;
    itemName: string;
    itemSku: string;
    type: string;
    quantity: string;
    baseUnitSymbol: string;
    unitCost: string | null;
    totalCost: string | null;
    referenceType: string;
    referenceId: number;
    actorName: string | null;
};

type PaginatedStockMovementRows = {
    current_page: number;
    data: StockMovementRow[];
    from: number | null;
    last_page: number;
    next_page_url: string | null;
    prev_page_url: string | null;
    to: number | null;
    total: number;
};

type StockMovementSummary = {
    totalCount: number;
    inboundCount: number;
    outboundCount: number;
    wasteCount: number;
};

type Option = {
    id: number;
    name: string;
};

type Props = {
    rows: PaginatedStockMovementRows;
    summary: StockMovementSummary;
    locationOptions: Option[];
    storageLocationOptions: Option[];
    itemOptions: Option[];
    typeOptions: string[];
    filters: {
        locationId: number | null;
        storageLocationId: number | null;
        inventoryItemId: number | null;
        type: string | null;
        from: string | null;
        to: string | null;
        reference: string | null;
    };
    currency: string;
    timezone: string;
    canViewCosts: boolean;
};

/** Format persisted decimal strings without converting ledger quantities to floats. */
function formatDecimal(value: string): string {
    const [rawInteger, rawDecimal = ''] = value.trim().split('.');
    const negative = rawInteger.startsWith('-');
    const integerDigits = negative ? rawInteger.slice(1) : rawInteger;
    const groupedInteger = integerDigits.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    const decimal = rawDecimal.replace(/0+$/, '');

    return `${negative ? '-' : ''}${groupedInteger}${
        decimal === '' ? '' : `.${decimal}`
    }`;
}

/** Keep the persisted movement sign explicit in the operational quantity display. */
function formatSignedDecimal(value: string): string {
    const formatted = formatDecimal(value);

    return value.trim().startsWith('-') ? formatted : `+${formatted}`;
}

/** Format one currency amount without floating-point conversion. */
function formatCurrency(value: string, currency: string): string {
    return `${currency} ${formatDecimal(value)}`;
}

/** Format ledger timestamps in the active organization's configured timezone. */
function formatOrganizationDate(value: string, timezone: string): string {
    return new Intl.DateTimeFormat('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        timeZone: timezone,
        timeZoneName: 'short',
    }).format(new Date(value));
}

function formatTypeLabel(type: string): string {
    return type
        .toLowerCase()
        .split('_')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
}

/** Map persisted ledger movement types to the shared semantic badge vocabulary. */
function movementTypeVariant(
    type: string,
): 'neutral' | 'success' | 'warning' | 'info' | 'danger' {
    switch (type) {
        case 'PURCHASE_RECEIPT':
        case 'TRANSFER_IN':
        case 'OPENING_BALANCE':
            return 'success';
        case 'WASTE':
            return 'danger';
        case 'TRANSFER_OUT':
            return 'warning';
        case 'COUNT_ADJUSTMENT':
        case 'MANUAL_ADJUSTMENT':
            return 'info';
        default:
            return 'neutral';
    }
}

/** Keep the CSV export scoped to the exact report filters currently applied. */
function buildExportUrl(filters: Props['filters']): string {
    const params = new URLSearchParams();

    if (filters.locationId !== null) {
        params.set('location_id', filters.locationId.toString());
    }

    if (filters.storageLocationId !== null) {
        params.set('storage_location_id', filters.storageLocationId.toString());
    }

    if (filters.inventoryItemId !== null) {
        params.set('inventory_item_id', filters.inventoryItemId.toString());
    }

    if (filters.type !== null) {
        params.set('type', filters.type);
    }

    if (filters.from !== null) {
        params.set('from', filters.from);
    }

    if (filters.to !== null) {
        params.set('to', filters.to);
    }

    if (filters.reference !== null) {
        params.set('reference', filters.reference);
    }

    const baseUrl = StockMovementLedgerReportController.export().url;
    const query = params.toString();

    return query === '' ? baseUrl : `${baseUrl}?${query}`;
}

/** Render one ledger movement as a mobile evidence card. */
function StockMovementCard({
    row,
    currency,
    timezone,
    canViewCosts,
}: {
    row: StockMovementRow;
    currency: string;
    timezone: string;
    canViewCosts: boolean;
}) {
    const inbound = !row.quantity.trim().startsWith('-');

    return (
        <article className="rounded-xl border border-border bg-card p-4 shadow-sm">
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <h3 className="font-medium">{row.itemName}</h3>
                    <p className="mt-0.5 font-mono text-xs text-muted-foreground">
                        {row.itemSku}
                    </p>
                </div>

                <StatusBadge
                    label={formatTypeLabel(row.type)}
                    variant={movementTypeVariant(row.type)}
                />
            </div>

            <div className="mt-3 flex items-center gap-1.5 font-semibold tabular-nums">
                {inbound ? (
                    <CirclePlus
                        className="size-4 text-success-foreground"
                        aria-hidden="true"
                    />
                ) : (
                    <CircleMinus
                        className="size-4 text-destructive"
                        aria-hidden="true"
                    />
                )}
                <span
                    className={
                        inbound ? 'text-success-foreground' : 'text-destructive'
                    }
                >
                    {formatSignedDecimal(row.quantity)} {row.baseUnitSymbol}
                </span>
            </div>

            <dl className="mt-4 grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                <div>
                    <dt className="text-xs text-muted-foreground">Occurred</dt>
                    <dd className="mt-0.5 tabular-nums">
                        {formatOrganizationDate(row.occurredAt, timezone)}
                    </dd>
                </div>

                <div>
                    <dt className="text-xs text-muted-foreground">
                        Location / Storage
                    </dt>
                    <dd className="mt-0.5">
                        {row.locationName}
                        <span className="block text-xs text-muted-foreground">
                            {row.storageLocationName}
                        </span>
                    </dd>
                </div>

                <div>
                    <dt className="text-xs text-muted-foreground">
                        Source / Reference
                    </dt>
                    <dd className="mt-0.5 font-mono text-xs">
                        {row.referenceType} #{row.referenceId}
                    </dd>
                </div>

                <div>
                    <dt className="text-xs text-muted-foreground">Actor</dt>
                    <dd className="mt-0.5">
                        {row.actorName ?? 'Not recorded'}
                    </dd>
                </div>

                {canViewCosts && (
                    <>
                        <div>
                            <dt className="text-xs text-muted-foreground">
                                Unit cost
                            </dt>
                            <dd className="mt-0.5 tabular-nums">
                                {row.unitCost === null
                                    ? 'Not recorded'
                                    : formatCurrency(row.unitCost, currency)}
                            </dd>
                        </div>

                        <div>
                            <dt className="text-xs text-muted-foreground">
                                Total cost
                            </dt>
                            <dd className="mt-0.5 tabular-nums">
                                {row.totalCost === null
                                    ? 'Not recorded'
                                    : formatCurrency(row.totalCost, currency)}
                            </dd>
                        </div>
                    </>
                )}
            </dl>
        </article>
    );
}

/** Render the append-only stock ledger as a dense operational report. */
export default function StockMovementLedgerReport({
    rows,
    summary,
    locationOptions,
    storageLocationOptions,
    itemOptions,
    typeOptions,
    filters,
    currency,
    timezone,
    canViewCosts,
}: Props) {
    const activeFilterLabels = [
        filters.locationId === null
            ? null
            : `Location: ${
                  locationOptions.find(
                      (option) => option.id === filters.locationId,
                  )?.name ?? filters.locationId
              }`,
        filters.storageLocationId === null
            ? null
            : `Storage: ${
                  storageLocationOptions.find(
                      (option) => option.id === filters.storageLocationId,
                  )?.name ?? filters.storageLocationId
              }`,
        filters.inventoryItemId === null
            ? null
            : `Item: ${
                  itemOptions.find(
                      (option) => option.id === filters.inventoryItemId,
                  )?.name ?? filters.inventoryItemId
              }`,
        filters.type === null ? null : `Type: ${formatTypeLabel(filters.type)}`,
        filters.from === null ? null : `From: ${filters.from}`,
        filters.to === null ? null : `To: ${filters.to}`,
        filters.reference === null
            ? null
            : `Source / ref: ${filters.reference}`,
    ].filter((label): label is string => label !== null);

    const hasFilters = activeFilterLabels.length > 0;
    const exportUrl = buildExportUrl(filters);

    const { organizationContext } = usePage<{
        organizationContext: OrganizationContext;
    }>().props;
    const canExportReports =
        organizationContext.entitlements?.grants['reports.export'] ?? false;

    return (
        <>
            <Head title="Stock movement ledger" />

            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <PageHeader
                    title="Stock movement ledger"
                    description="Immutable stock movement history in deterministic append order."
                    actions={
                        <>
                            <Button variant="outline" asChild>
                                <Link href={InventoryItemController.index()}>
                                    <Package
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Inventory items
                                </Link>
                            </Button>

                            {canExportReports && (
                                <Button variant="outline" asChild>
                                    <a href={exportUrl}>
                                        <Download
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        Export CSV
                                    </a>
                                </Button>
                            )}
                        </>
                    }
                />

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <DashboardMetricCard
                        title="Total movements"
                        value={summary.totalCount.toLocaleString()}
                        description="Ledger entries matching the current filters"
                        icon={ArrowLeftRight}
                        tone="blue"
                    />

                    <DashboardMetricCard
                        title="Stock increases"
                        value={summary.inboundCount.toLocaleString()}
                        description="Movements with a positive persisted quantity"
                        icon={ArrowDownToLine}
                        tone="emerald"
                    />

                    <DashboardMetricCard
                        title="Stock decreases"
                        value={summary.outboundCount.toLocaleString()}
                        description="Movements with a negative persisted quantity"
                        icon={ArrowUpFromLine}
                        tone="amber"
                    />

                    <DashboardMetricCard
                        title="Waste movements"
                        value={summary.wasteCount.toLocaleString()}
                        description="Waste entries represented in this filtered ledger"
                        icon={Trash2}
                        tone="amber"
                    />
                </div>

                <Form
                    action={StockMovementLedgerReportController.index().url}
                    method="get"
                >
                    {({ errors, processing }) => (
                        <FilterToolbar className="overflow-hidden p-0 shadow-sm">
                            <div className="grid gap-4 p-4 md:grid-cols-2 xl:grid-cols-4 2xl:grid-cols-[1fr_1fr_1fr_1fr_0.9fr_0.9fr_1.35fr_auto]">
                                <Field
                                    id="location_id"
                                    label="Location"
                                    error={errors.location_id}
                                >
                                    <NativeSelect
                                        name="location_id"
                                        defaultValue={
                                            filters.locationId?.toString() ?? ''
                                        }
                                    >
                                        <option value="">All locations</option>

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
                                    id="storage_location_id"
                                    label="Storage location"
                                    error={errors.storage_location_id}
                                >
                                    <NativeSelect
                                        name="storage_location_id"
                                        defaultValue={
                                            filters.storageLocationId?.toString() ??
                                            ''
                                        }
                                    >
                                        <option value="">
                                            All storage locations
                                        </option>

                                        {storageLocationOptions.map(
                                            (storageLocation) => (
                                                <option
                                                    key={storageLocation.id}
                                                    value={storageLocation.id}
                                                >
                                                    {storageLocation.name}
                                                </option>
                                            ),
                                        )}
                                    </NativeSelect>
                                </Field>

                                <Field
                                    id="inventory_item_id"
                                    label="Item"
                                    error={errors.inventory_item_id}
                                >
                                    <NativeSelect
                                        name="inventory_item_id"
                                        defaultValue={
                                            filters.inventoryItemId?.toString() ??
                                            ''
                                        }
                                    >
                                        <option value="">All items</option>

                                        {itemOptions.map((item) => (
                                            <option
                                                key={item.id}
                                                value={item.id}
                                            >
                                                {item.name}
                                            </option>
                                        ))}
                                    </NativeSelect>
                                </Field>

                                <Field
                                    id="type"
                                    label="Movement type"
                                    error={errors.type}
                                >
                                    <NativeSelect
                                        name="type"
                                        defaultValue={filters.type ?? ''}
                                    >
                                        <option value="">All types</option>

                                        {typeOptions.map((type) => (
                                            <option key={type} value={type}>
                                                {formatTypeLabel(type)}
                                            </option>
                                        ))}
                                    </NativeSelect>
                                </Field>

                                <Field
                                    id="from"
                                    label="From"
                                    error={errors.from}
                                >
                                    <Input
                                        name="from"
                                        type="date"
                                        defaultValue={filters.from ?? ''}
                                    />
                                </Field>

                                <Field id="to" label="To" error={errors.to}>
                                    <Input
                                        name="to"
                                        type="date"
                                        defaultValue={filters.to ?? ''}
                                    />
                                </Field>

                                <Field
                                    id="reference"
                                    label="Source / reference"
                                    error={errors.reference}
                                >
                                    <div className="relative">
                                        <Search
                                            className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                            aria-hidden="true"
                                        />

                                        <Input
                                            name="reference"
                                            type="search"
                                            defaultValue={
                                                filters.reference ?? ''
                                            }
                                            placeholder="Source type or #ID"
                                            className="pl-9"
                                            autoComplete="off"
                                            maxLength={100}
                                        />
                                    </div>
                                </Field>

                                <div className="flex items-end gap-2 md:col-span-2 xl:col-span-1">
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        className="min-w-24 flex-1 2xl:flex-none"
                                    >
                                        <Filter
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        {processing ? 'Applying…' : 'Apply'}
                                    </Button>

                                    <Button
                                        variant="outline"
                                        className="flex-1 2xl:flex-none"
                                        asChild
                                    >
                                        <Link
                                            href={StockMovementLedgerReportController.index()}
                                        >
                                            <RotateCcw
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                            Clear
                                        </Link>
                                    </Button>
                                </div>

                                {Object.keys(errors).length > 0 && (
                                    <div
                                        role="alert"
                                        className="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive md:col-span-2 xl:col-span-4 2xl:col-span-8"
                                    >
                                        One or more report filters are invalid.
                                        Review the values or clear the filters
                                        and try again.
                                    </div>
                                )}
                            </div>

                            <div
                                className="flex flex-wrap items-center gap-2 border-t border-border px-4 py-3 text-sm text-muted-foreground"
                                aria-live="polite"
                            >
                                <Filter className="size-4" aria-hidden="true" />

                                <span>
                                    Showing{' '}
                                    {summary.totalCount.toLocaleString()}{' '}
                                    {summary.totalCount === 1
                                        ? 'movement'
                                        : 'movements'}
                                </span>

                                {activeFilterLabels.length === 0 ? (
                                    <Badge variant="outline">
                                        Active filters: None
                                    </Badge>
                                ) : (
                                    activeFilterLabels.map((label) => (
                                        <Badge key={label} variant="outline">
                                            {label}
                                        </Badge>
                                    ))
                                )}

                                {hasFilters && (
                                    <Link
                                        href={StockMovementLedgerReportController.index()}
                                        className="font-medium text-foreground underline-offset-4 hover:underline focus-visible:rounded-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                    >
                                        Clear all
                                    </Link>
                                )}
                            </div>
                        </FilterToolbar>
                    )}
                </Form>

                <section
                    className="grid gap-3 md:hidden"
                    aria-labelledby="stock-movement-cards-title"
                >
                    <h2 id="stock-movement-cards-title" className="sr-only">
                        Movement history
                    </h2>

                    {rows.data.length === 0 ? (
                        <div className="rounded-xl border border-border bg-card p-6 shadow-sm">
                            <EmptyState
                                icon={Search}
                                title={
                                    hasFilters
                                        ? 'No stock movements match these filters.'
                                        : 'No stock movements found.'
                                }
                                description={
                                    hasFilters
                                        ? 'Adjust or clear the filters to inspect other ledger entries.'
                                        : 'Stock movements will appear here as inventory transactions are finalized.'
                                }
                            />
                        </div>
                    ) : (
                        rows.data.map((row) => (
                            <StockMovementCard
                                key={row.id}
                                row={row}
                                currency={currency}
                                timezone={timezone}
                                canViewCosts={canViewCosts}
                            />
                        ))
                    )}
                </section>

                <section
                    className="hidden overflow-hidden rounded-xl border border-border bg-card shadow-sm md:block"
                    aria-labelledby="stock-movement-table-title"
                >
                    <div className="flex min-h-14 flex-wrap items-center justify-between gap-3 border-b border-border px-4 py-3">
                        <div>
                            <h2
                                id="stock-movement-table-title"
                                className="text-sm font-semibold"
                            >
                                Movement history
                            </h2>

                            <p className="mt-0.5 text-xs text-muted-foreground">
                                Quantities are the persisted signed base-unit
                                values in append order.
                            </p>
                        </div>

                        <div className="flex items-center gap-2">
                            <Badge variant="outline">
                                {rows.total.toLocaleString()}{' '}
                                {rows.total === 1 ? 'movement' : 'movements'}
                            </Badge>
                            <Badge variant="outline">Append order</Badge>
                        </div>
                    </div>

                    <div className="overflow-x-auto">
                        <table
                            className={`w-full text-sm ${
                                canViewCosts
                                    ? 'min-w-[1320px]'
                                    : 'min-w-[1040px]'
                            }`}
                        >
                            <caption className="sr-only">
                                Immutable stock movements with source, actor,
                                signed base-unit quantity, and permission-gated
                                costs.
                            </caption>

                            <thead className="border-b bg-muted/40 text-left text-xs text-muted-foreground">
                                <tr>
                                    <th scope="col" className="px-4 py-3">
                                        Occurred
                                    </th>
                                    <th scope="col" className="px-4 py-3">
                                        Item
                                    </th>
                                    <th scope="col" className="px-4 py-3">
                                        Location / Storage
                                    </th>
                                    <th scope="col" className="px-4 py-3">
                                        Type
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-4 py-3 text-right"
                                    >
                                        Quantity
                                    </th>
                                    <th scope="col" className="px-4 py-3">
                                        Source / Reference
                                    </th>
                                    <th scope="col" className="px-4 py-3">
                                        Actor
                                    </th>

                                    {canViewCosts && (
                                        <>
                                            <th
                                                scope="col"
                                                className="px-4 py-3 text-right"
                                            >
                                                Unit cost
                                            </th>
                                            <th
                                                scope="col"
                                                className="px-4 py-3 text-right"
                                            >
                                                Total cost
                                            </th>
                                        </>
                                    )}
                                </tr>
                            </thead>

                            <tbody>
                                {rows.data.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={canViewCosts ? 9 : 7}
                                            className="px-6 py-14"
                                        >
                                            <EmptyState
                                                icon={Search}
                                                title={
                                                    hasFilters
                                                        ? 'No stock movements match these filters.'
                                                        : 'No stock movements found.'
                                                }
                                                description={
                                                    hasFilters
                                                        ? 'Adjust or clear the filters to inspect other ledger entries.'
                                                        : 'Stock movements will appear here as inventory transactions are finalized.'
                                                }
                                            />
                                        </td>
                                    </tr>
                                ) : (
                                    rows.data.map((row) => {
                                        const inbound = !row.quantity
                                            .trim()
                                            .startsWith('-');

                                        return (
                                            <tr
                                                key={row.id}
                                                className="border-b border-border align-top transition-colors last:border-b-0 hover:bg-muted/30"
                                            >
                                                <td className="px-4 py-3 whitespace-nowrap tabular-nums">
                                                    {formatOrganizationDate(
                                                        row.occurredAt,
                                                        timezone,
                                                    )}
                                                </td>

                                                <td className="px-4 py-3">
                                                    <div className="font-medium">
                                                        {row.itemName}
                                                    </div>
                                                    <div className="mt-0.5 font-mono text-xs text-muted-foreground">
                                                        {row.itemSku}
                                                    </div>
                                                </td>

                                                <td className="px-4 py-3">
                                                    <div>
                                                        {row.locationName}
                                                    </div>
                                                    <div className="mt-0.5 text-xs text-muted-foreground">
                                                        {
                                                            row.storageLocationName
                                                        }
                                                    </div>
                                                </td>

                                                <td className="px-4 py-3">
                                                    <StatusBadge
                                                        label={formatTypeLabel(
                                                            row.type,
                                                        )}
                                                        variant={movementTypeVariant(
                                                            row.type,
                                                        )}
                                                    />
                                                </td>

                                                <td className="px-4 py-3 text-right whitespace-nowrap tabular-nums">
                                                    <span
                                                        className={`inline-flex items-center justify-end gap-1.5 font-semibold ${
                                                            inbound
                                                                ? 'text-success-foreground'
                                                                : 'text-destructive'
                                                        }`}
                                                    >
                                                        {inbound ? (
                                                            <CirclePlus
                                                                className="size-4"
                                                                aria-hidden="true"
                                                            />
                                                        ) : (
                                                            <CircleMinus
                                                                className="size-4"
                                                                aria-hidden="true"
                                                            />
                                                        )}
                                                        <span>
                                                            {formatSignedDecimal(
                                                                row.quantity,
                                                            )}{' '}
                                                            {row.baseUnitSymbol}
                                                        </span>
                                                    </span>
                                                </td>

                                                <td className="px-4 py-3">
                                                    <div className="font-mono text-xs">
                                                        {row.referenceType}
                                                    </div>
                                                    <div className="mt-0.5 text-xs text-muted-foreground">
                                                        #{row.referenceId}
                                                    </div>
                                                </td>

                                                <td className="px-4 py-3">
                                                    {row.actorName ?? (
                                                        <span className="text-muted-foreground">
                                                            Not recorded
                                                        </span>
                                                    )}
                                                </td>

                                                {canViewCosts && (
                                                    <>
                                                        <td className="px-4 py-3 text-right whitespace-nowrap tabular-nums">
                                                            {row.unitCost ===
                                                            null
                                                                ? 'Not recorded'
                                                                : formatCurrency(
                                                                      row.unitCost,
                                                                      currency,
                                                                  )}
                                                        </td>

                                                        <td className="px-4 py-3 text-right font-medium whitespace-nowrap tabular-nums">
                                                            {row.totalCost ===
                                                            null
                                                                ? 'Not recorded'
                                                                : formatCurrency(
                                                                      row.totalCost,
                                                                      currency,
                                                                  )}
                                                        </td>
                                                    </>
                                                )}
                                            </tr>
                                        );
                                    })
                                )}
                            </tbody>
                        </table>
                    </div>

                    <PaginationControls
                        currentPage={rows.current_page}
                        lastPage={rows.last_page}
                        from={rows.from}
                        to={rows.to}
                        total={rows.total}
                        previousPageUrl={rows.prev_page_url}
                        nextPageUrl={rows.next_page_url}
                        itemLabel="stock movements"
                        preserveScroll
                        preserveState
                    />
                </section>
            </div>
        </>
    );
}

StockMovementLedgerReport.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
        {
            title: 'Inventory',
            href: InventoryItemController.index(),
        },
        {
            title: 'Stock movement ledger',
            href: StockMovementLedgerReportController.index(),
        },
    ],
};
