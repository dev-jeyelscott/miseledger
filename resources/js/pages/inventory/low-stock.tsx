import { Form, Head, Link } from '@inertiajs/react';
import {
    CircleMinus,
    Filter,
    MapPin,
    Package,
    Pencil,
    RotateCcw,
    Search,
    TriangleAlert,
} from 'lucide-react';

import InventoryItemController from '@/actions/App/Http/Controllers/Inventory/InventoryItemController';
import LowStockReportController from '@/actions/App/Http/Controllers/Inventory/LowStockReportController';
import { DashboardMetricCard } from '@/components/dashboard/dashboard-metric-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';

type LowStockStatus = 'out_of_stock' | 'negative';

type LowStockRow = {
    id: number;
    locationId: number;
    locationName: string;
    storageLocationId: number;
    storageLocationName: string;
    itemId: number;
    itemName: string;
    itemSku: string;
    categoryName: string | null;
    quantityOnHand: string;
    baseUnitSymbol: string;
    status: LowStockStatus;
};

type LowStockSummary = {
    affectedBalanceCount: number;
    outOfStockCount: number;
    negativeCount: number;
    affectedLocationCount: number;
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

type Option = {
    id: number;
    name: string;
};

type Props = {
    rows: LowStockRow[];
    pagination: Pagination;
    summary: LowStockSummary;
    locationOptions: Option[];
    storageLocationOptions: Option[];
    categoryOptions: Option[];
    filters: {
        locationId: number | null;
        storageLocationId: number | null;
        inventoryCategoryId: number | null;
        inventoryItemId: number | null;
        itemSearch: string | null;
        status: LowStockStatus | null;
    };
    canManage: boolean;
};

const statusLabels: Record<LowStockStatus, string> = {
    out_of_stock: 'Out of stock',
    negative: 'Negative',
};

const selectClassName =
    'h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs outline-none transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive aria-invalid:ring-[3px] aria-invalid:ring-destructive/20 disabled:cursor-not-allowed disabled:opacity-50';

/** Format persisted decimal strings without converting inventory quantities to floats. */
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

/** Render the server-authoritative operational view of zero and negative balances. */
export default function LowStockReport({
    rows,
    pagination,
    summary,
    locationOptions,
    storageLocationOptions,
    categoryOptions,
    filters,
    canManage,
}: Props) {
    const itemFilterValue =
        filters.itemSearch ?? filters.inventoryItemId?.toString() ?? '';

    const activeFilterCount = [
        filters.locationId,
        filters.storageLocationId,
        filters.inventoryCategoryId,
        filters.status,
        itemFilterValue === '' ? null : itemFilterValue,
    ].filter((value) => value !== null).length;

    const hasFilters = activeFilterCount > 0;

    return (
        <>
            <Head title="Low stock" />

            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Low stock
                        </h1>

                        <p className="mt-1 text-sm text-muted-foreground">
                            Zero and negative inventory balances across
                            locations.
                        </p>
                    </div>

                    <Button variant="outline" asChild>
                        <Link href={InventoryItemController.index()}>
                            <Package className="size-4" aria-hidden="true" />
                            Inventory items
                        </Link>
                    </Button>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <DashboardMetricCard
                        title="Affected balances"
                        value={summary.affectedBalanceCount.toLocaleString()}
                        description="Zero or negative balances in the current filters"
                        icon={Package}
                        tone="blue"
                    />

                    <DashboardMetricCard
                        title="Out of stock"
                        value={summary.outOfStockCount.toLocaleString()}
                        description="Balances currently at exactly zero"
                        icon={CircleMinus}
                        tone="amber"
                    />

                    <DashboardMetricCard
                        title="Negative stock"
                        value={summary.negativeCount.toLocaleString()}
                        description="Balances requiring investigation below zero"
                        icon={TriangleAlert}
                        tone="amber"
                    />

                    <DashboardMetricCard
                        title="Locations affected"
                        value={summary.affectedLocationCount.toLocaleString()}
                        description="Restaurant locations represented in these balances"
                        icon={MapPin}
                        tone="teal"
                    />
                </div>

                <Form
                    action={LowStockReportController.index().url}
                    method="get"
                >
                    {({ errors, processing }) => (
                        <div className="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card shadow-sm dark:border-sidebar-border">
                            <div className="grid gap-4 p-4 md:grid-cols-2 xl:grid-cols-[1fr_1fr_1fr_0.9fr_1.35fr_auto]">
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
                                    <Label htmlFor="inventory_category_id">
                                        Category
                                    </Label>

                                    <select
                                        id="inventory_category_id"
                                        name="inventory_category_id"
                                        defaultValue={
                                            filters.inventoryCategoryId?.toString() ??
                                            ''
                                        }
                                        aria-invalid={
                                            errors.inventory_category_id
                                                ? true
                                                : undefined
                                        }
                                        className={selectClassName}
                                    >
                                        <option value="">All categories</option>

                                        {categoryOptions.map((category) => (
                                            <option
                                                key={category.id}
                                                value={category.id}
                                            >
                                                {category.name}
                                            </option>
                                        ))}
                                    </select>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="status">Status</Label>

                                    <select
                                        id="status"
                                        name="status"
                                        defaultValue={filters.status ?? ''}
                                        aria-invalid={
                                            errors.status ? true : undefined
                                        }
                                        className={selectClassName}
                                    >
                                        <option value="">All statuses</option>
                                        <option value="out_of_stock">
                                            Out of stock
                                        </option>
                                        <option value="negative">
                                            Negative stock
                                        </option>
                                    </select>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="item">Item</Label>

                                    <div className="relative">
                                        <Search
                                            className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                            aria-hidden="true"
                                        />

                                        <Input
                                            id="item"
                                            type="search"
                                            name="item"
                                            defaultValue={itemFilterValue}
                                            placeholder="Search by name, SKU, or item ID"
                                            className="pl-9"
                                            autoComplete="off"
                                            aria-invalid={
                                                errors.item ? true : undefined
                                            }
                                        />
                                    </div>
                                </div>

                                <div className="flex items-end gap-2 md:col-span-2 xl:col-span-1">
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        className="min-w-24 flex-1 xl:flex-none"
                                    >
                                        <Filter
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        {processing ? 'Applying…' : 'Apply'}
                                    </Button>

                                    <Button
                                        variant="outline"
                                        className="flex-1 xl:flex-none"
                                        asChild
                                    >
                                        <Link
                                            href={LowStockReportController.index()}
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
                                        className="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive md:col-span-2 xl:col-span-6"
                                    >
                                        One or more report filters are invalid.
                                        Review the filter values or clear them
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
                                    Showing {pagination.total.toLocaleString()}{' '}
                                    {pagination.total === 1
                                        ? 'affected balance'
                                        : 'affected balances'}
                                </span>

                                <Badge variant="outline">
                                    Active filters:{' '}
                                    {activeFilterCount === 0
                                        ? 'None'
                                        : activeFilterCount}
                                </Badge>
                            </div>
                        </div>
                    )}
                </Form>

                <section
                    className="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card shadow-sm dark:border-sidebar-border"
                    aria-labelledby="low-stock-table-title"
                >
                    <div className="flex min-h-14 items-center justify-between gap-3 border-b border-sidebar-border/70 px-4 dark:border-sidebar-border">
                        <div>
                            <h2
                                id="low-stock-table-title"
                                className="text-sm font-semibold"
                            >
                                Low-stock balances
                            </h2>

                            <p className="mt-0.5 text-xs text-muted-foreground">
                                Quantities are shown in each item's base unit of
                                measure.
                            </p>
                        </div>

                        <Badge variant="outline">
                            {pagination.total.toLocaleString()}{' '}
                            {pagination.total === 1 ? 'balance' : 'balances'}
                        </Badge>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[1080px] text-sm">
                            <caption className="sr-only">
                                Inventory balances at zero or below zero,
                                grouped by item, location, and storage location.
                            </caption>

                            <thead className="border-b bg-muted/40 text-left text-xs text-muted-foreground">
                                <tr>
                                    <th scope="col" className="px-4 py-3">
                                        Item
                                    </th>

                                    <th scope="col" className="px-4 py-3">
                                        SKU
                                    </th>

                                    <th scope="col" className="px-4 py-3">
                                        Category
                                    </th>

                                    <th scope="col" className="px-4 py-3">
                                        Location
                                    </th>

                                    <th scope="col" className="px-4 py-3">
                                        Storage
                                    </th>

                                    <th
                                        scope="col"
                                        className="px-4 py-3 text-right"
                                    >
                                        On hand
                                    </th>

                                    <th scope="col" className="px-4 py-3">
                                        Status
                                    </th>

                                    {canManage && (
                                        <th
                                            scope="col"
                                            className="w-16 px-4 py-3 text-right"
                                        >
                                            <span className="sr-only">
                                                Actions
                                            </span>
                                        </th>
                                    )}
                                </tr>
                            </thead>

                            <tbody>
                                {rows.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={canManage ? 8 : 7}
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
                                                    ? 'No low-stock balances match these filters.'
                                                    : 'No low-stock balances found.'}
                                            </p>

                                            <p className="mt-1 text-sm text-muted-foreground">
                                                {hasFilters
                                                    ? 'Adjust or clear the filters to see other affected balances.'
                                                    : 'There are currently no zero or negative inventory balances requiring attention.'}
                                            </p>
                                        </td>
                                    </tr>
                                ) : (
                                    rows.map((row) => (
                                        <tr
                                            key={row.id}
                                            className="border-b border-sidebar-border/70 transition-colors last:border-b-0 hover:bg-muted/30 dark:border-sidebar-border"
                                        >
                                            <td className="px-4 py-3">
                                                <div className="flex items-center gap-3">
                                                    <div
                                                        className="flex size-8 shrink-0 items-center justify-center rounded-md bg-destructive/10 text-destructive"
                                                        aria-hidden="true"
                                                    >
                                                        <Package className="size-4" />
                                                    </div>

                                                    <div className="min-w-0">
                                                        {canManage ? (
                                                            <Link
                                                                href={InventoryItemController.edit(
                                                                    row.itemId,
                                                                )}
                                                                className="block truncate font-medium underline-offset-4 hover:underline focus-visible:rounded-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                                            >
                                                                {row.itemName}
                                                            </Link>
                                                        ) : (
                                                            <div className="truncate font-medium">
                                                                {row.itemName}
                                                            </div>
                                                        )}
                                                    </div>
                                                </div>
                                            </td>

                                            <td className="px-4 py-3 font-mono text-xs text-muted-foreground">
                                                {row.itemSku}
                                            </td>

                                            <td className="px-4 py-3">
                                                {row.categoryName ?? (
                                                    <span className="text-muted-foreground">
                                                        —
                                                    </span>
                                                )}
                                            </td>

                                            <td className="px-4 py-3">
                                                {row.locationName}
                                            </td>

                                            <td className="px-4 py-3">
                                                {row.storageLocationName}
                                            </td>

                                            <td className="px-4 py-3 text-right font-semibold whitespace-nowrap text-destructive tabular-nums">
                                                {formatDecimal(
                                                    row.quantityOnHand,
                                                )}{' '}
                                                <span className="font-normal">
                                                    {row.baseUnitSymbol}
                                                </span>
                                            </td>

                                            <td className="px-4 py-3">
                                                <Badge
                                                    variant={
                                                        row.status ===
                                                        'negative'
                                                            ? 'destructive'
                                                            : 'outline'
                                                    }
                                                    className={
                                                        row.status ===
                                                        'out_of_stock'
                                                            ? 'border-destructive/30 bg-destructive/10 text-destructive dark:border-destructive/50 dark:bg-destructive/20'
                                                            : undefined
                                                    }
                                                >
                                                    {row.status ===
                                                    'negative' ? (
                                                        <TriangleAlert aria-hidden="true" />
                                                    ) : (
                                                        <CircleMinus aria-hidden="true" />
                                                    )}

                                                    {statusLabels[row.status]}
                                                </Badge>
                                            </td>

                                            {canManage && (
                                                <td className="px-4 py-2 text-right">
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        asChild
                                                    >
                                                        <Link
                                                            href={InventoryItemController.edit(
                                                                row.itemId,
                                                            )}
                                                            aria-label={`Edit ${row.itemName}`}
                                                        >
                                                            <Pencil
                                                                className="size-4"
                                                                aria-hidden="true"
                                                            />
                                                        </Link>
                                                    </Button>
                                                </td>
                                            )}
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    {pagination.total > 0 && (
                        <div className="flex flex-col gap-3 border-t border-sidebar-border/70 px-4 py-3 sm:flex-row sm:items-center sm:justify-between dark:border-sidebar-border">
                            <p className="text-sm text-muted-foreground">
                                Showing {pagination.from ?? 0} to{' '}
                                {pagination.to ?? 0} of{' '}
                                {pagination.total.toLocaleString()} balances
                            </p>

                            {pagination.last_page > 1 && (
                                <div className="flex items-center gap-2">
                                    {pagination.prev_page_url !== null ? (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            asChild
                                        >
                                            <Link
                                                href={pagination.prev_page_url}
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
                                            size="sm"
                                            disabled
                                        >
                                            Previous
                                        </Button>
                                    )}

                                    <span className="px-1 text-sm text-muted-foreground">
                                        Page {pagination.current_page} of{' '}
                                        {pagination.last_page}
                                    </span>

                                    {pagination.next_page_url !== null ? (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            asChild
                                        >
                                            <Link
                                                href={pagination.next_page_url}
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
                                            size="sm"
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

LowStockReport.layout = {
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
            title: 'Low stock',
            href: LowStockReportController.index(),
        },
    ],
};
