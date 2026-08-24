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
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
    canViewCosts: boolean;
};

const selectClassName =
    'h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs outline-none transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive aria-invalid:ring-[3px] aria-invalid:ring-destructive/20 disabled:cursor-not-allowed disabled:opacity-50';

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

function formatDate(value: string): string {
    return new Date(value).toLocaleString();
}

function formatTypeLabel(type: string): string {
    return type
        .toLowerCase()
        .split('_')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
}

function movementTypeClassName(type: string): string {
    switch (type) {
        case 'PURCHASE_RECEIPT':
        case 'TRANSFER_IN':
        case 'OPENING_BALANCE':
            return 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300';
        case 'WASTE':
            return 'border-destructive/30 bg-destructive/10 text-destructive dark:border-destructive/50 dark:bg-destructive/20';
        case 'TRANSFER_OUT':
            return 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-300';
        case 'COUNT_ADJUSTMENT':
        case 'MANUAL_ADJUSTMENT':
            return 'border-violet-200 bg-violet-50 text-violet-700 dark:border-violet-900 dark:bg-violet-950/40 dark:text-violet-300';
        default:
            return 'bg-muted/50 text-foreground';
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
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Stock movement ledger
                        </h1>

                        <p className="mt-1 text-sm text-muted-foreground">
                            Immutable stock movement history in deterministic
                            append order.
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
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
                    </div>
                </div>

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
                        <div className="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card shadow-sm dark:border-sidebar-border">
                            <div className="grid gap-4 p-4 md:grid-cols-2 xl:grid-cols-4 2xl:grid-cols-[1fr_1fr_1fr_1fr_0.9fr_0.9fr_1.35fr_auto]">
                                <div className="grid gap-2">
                                    <Label htmlFor="location_id">
                                        Location
                                    </Label>

                                    <select
                                        id="location_id"
                                        name="location_id"
                                        defaultValue={
                                            filters.locationId?.toString() ?? ''
                                        }
                                        aria-invalid={
                                            errors.location_id
                                                ? true
                                                : undefined
                                        }
                                        className={selectClassName}
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
                                    </select>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="storage_location_id">
                                        Storage location
                                    </Label>

                                    <select
                                        id="storage_location_id"
                                        name="storage_location_id"
                                        defaultValue={
                                            filters.storageLocationId?.toString() ??
                                            ''
                                        }
                                        aria-invalid={
                                            errors.storage_location_id
                                                ? true
                                                : undefined
                                        }
                                        className={selectClassName}
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
                                    </select>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="inventory_item_id">
                                        Item
                                    </Label>

                                    <select
                                        id="inventory_item_id"
                                        name="inventory_item_id"
                                        defaultValue={
                                            filters.inventoryItemId?.toString() ??
                                            ''
                                        }
                                        aria-invalid={
                                            errors.inventory_item_id
                                                ? true
                                                : undefined
                                        }
                                        className={selectClassName}
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
                                    </select>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="type">Movement type</Label>

                                    <select
                                        id="type"
                                        name="type"
                                        defaultValue={filters.type ?? ''}
                                        aria-invalid={
                                            errors.type ? true : undefined
                                        }
                                        className={selectClassName}
                                    >
                                        <option value="">All types</option>

                                        {typeOptions.map((type) => (
                                            <option key={type} value={type}>
                                                {formatTypeLabel(type)}
                                            </option>
                                        ))}
                                    </select>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="from">From</Label>
                                    <Input
                                        id="from"
                                        name="from"
                                        type="date"
                                        defaultValue={filters.from ?? ''}
                                        aria-invalid={
                                            errors.from ? true : undefined
                                        }
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="to">To</Label>
                                    <Input
                                        id="to"
                                        name="to"
                                        type="date"
                                        defaultValue={filters.to ?? ''}
                                        aria-invalid={
                                            errors.to ? true : undefined
                                        }
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="reference">
                                        Source / reference
                                    </Label>

                                    <div className="relative">
                                        <Search
                                            className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                            aria-hidden="true"
                                        />

                                        <Input
                                            id="reference"
                                            name="reference"
                                            type="search"
                                            defaultValue={
                                                filters.reference ?? ''
                                            }
                                            placeholder="Source type or #ID"
                                            className="pl-9"
                                            autoComplete="off"
                                            maxLength={100}
                                            aria-invalid={
                                                errors.reference
                                                    ? true
                                                    : undefined
                                            }
                                        />
                                    </div>
                                </div>

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
                                className="flex flex-wrap items-center gap-2 border-t border-sidebar-border/70 px-4 py-3 text-sm text-muted-foreground dark:border-sidebar-border"
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
                        </div>
                    )}
                </Form>

                <section
                    className="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card shadow-sm dark:border-sidebar-border"
                    aria-labelledby="stock-movement-table-title"
                >
                    <div className="flex min-h-14 flex-wrap items-center justify-between gap-3 border-b border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border">
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
                                            className="px-6 py-14 text-center"
                                        >
                                            <div className="mx-auto flex size-10 items-center justify-center rounded-full bg-muted">
                                                <Search
                                                    className="size-5 text-muted-foreground"
                                                    aria-hidden="true"
                                                />
                                            </div>

                                            <p className="mt-3 font-medium">
                                                {hasFilters
                                                    ? 'No stock movements match these filters.'
                                                    : 'No stock movements found.'}
                                            </p>

                                            <p className="mt-1 text-sm text-muted-foreground">
                                                {hasFilters
                                                    ? 'Adjust or clear the filters to inspect other ledger entries.'
                                                    : 'Stock movements will appear here as inventory transactions are finalized.'}
                                            </p>
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
                                                className="border-b border-sidebar-border/70 align-top transition-colors last:border-b-0 hover:bg-muted/30 dark:border-sidebar-border"
                                            >
                                                <td className="px-4 py-3 whitespace-nowrap tabular-nums">
                                                    {formatDate(row.occurredAt)}
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
                                                    <Badge
                                                        variant="outline"
                                                        className={movementTypeClassName(
                                                            row.type,
                                                        )}
                                                    >
                                                        {formatTypeLabel(
                                                            row.type,
                                                        )}
                                                    </Badge>
                                                </td>

                                                <td className="px-4 py-3 text-right whitespace-nowrap tabular-nums">
                                                    <span
                                                        className={`inline-flex items-center justify-end gap-1.5 font-semibold ${
                                                            inbound
                                                                ? 'text-emerald-700 dark:text-emerald-300'
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
                                                            —
                                                        </span>
                                                    )}
                                                </td>

                                                {canViewCosts && (
                                                    <>
                                                        <td className="px-4 py-3 text-right whitespace-nowrap tabular-nums">
                                                            {row.unitCost ===
                                                            null
                                                                ? '—'
                                                                : formatCurrency(
                                                                      row.unitCost,
                                                                      currency,
                                                                  )}
                                                        </td>

                                                        <td className="px-4 py-3 text-right font-medium whitespace-nowrap tabular-nums">
                                                            {row.totalCost ===
                                                            null
                                                                ? '—'
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

                    {rows.total > 0 && (
                        <div className="flex flex-col gap-3 border-t border-sidebar-border/70 px-4 py-3 sm:flex-row sm:items-center sm:justify-between dark:border-sidebar-border">
                            <p className="text-sm text-muted-foreground">
                                Showing {rows.from ?? 0} to {rows.to ?? 0} of{' '}
                                {rows.total.toLocaleString()} stock movements.
                            </p>

                            {rows.last_page > 1 && (
                                <div className="flex items-center gap-2">
                                    {rows.prev_page_url !== null ? (
                                        <Button variant="outline" asChild>
                                            <Link
                                                href={rows.prev_page_url}
                                                preserveScroll
                                                preserveState
                                            >
                                                Previous
                                            </Link>
                                        </Button>
                                    ) : (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            disabled
                                        >
                                            Previous
                                        </Button>
                                    )}

                                    <span className="px-2 text-sm text-muted-foreground">
                                        Page {rows.current_page} of{' '}
                                        {rows.last_page}
                                    </span>

                                    {rows.next_page_url !== null ? (
                                        <Button variant="outline" asChild>
                                            <Link
                                                href={rows.next_page_url}
                                                preserveScroll
                                                preserveState
                                            >
                                                Next
                                            </Link>
                                        </Button>
                                    ) : (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            disabled
                                        >
                                            Next
                                        </Button>
                                    )}
                                </div>
                            )}
                        </div>
                    )}
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
