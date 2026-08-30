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

function statusVariant(status: LowStockStatus): StatusBadgeProps['variant'] {
    return status === 'negative' ? 'danger' : 'warning';
}

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
                <PageHeader
                    title="Low stock"
                    description="Zero and negative inventory balances across locations."
                    actions={
                        <Button variant="outline" asChild>
                            <Link href={InventoryItemController.index()}>
                                <Package
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Inventory items
                            </Link>
                        </Button>
                    }
                />

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
                        <FilterToolbar>
                            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-[1fr_1fr_1fr_0.9fr_1.35fr_auto]">
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
                                    id="inventory_category_id"
                                    label="Category"
                                    error={errors.inventory_category_id}
                                >
                                    <NativeSelect
                                        name="inventory_category_id"
                                        defaultValue={
                                            filters.inventoryCategoryId?.toString() ??
                                            ''
                                        }
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
                                    </NativeSelect>
                                </Field>

                                <Field
                                    id="status"
                                    label="Status"
                                    error={errors.status}
                                >
                                    <NativeSelect
                                        name="status"
                                        defaultValue={filters.status ?? ''}
                                    >
                                        <option value="">All statuses</option>
                                        <option value="out_of_stock">
                                            Out of stock
                                        </option>
                                        <option value="negative">
                                            Negative stock
                                        </option>
                                    </NativeSelect>
                                </Field>

                                <Field
                                    id="item"
                                    label="Item"
                                    error={errors.item}
                                >
                                    <div className="relative">
                                        <Search
                                            className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                            aria-hidden="true"
                                        />

                                        <Input
                                            type="search"
                                            name="item"
                                            defaultValue={itemFilterValue}
                                            placeholder="Search by name, SKU, or item ID"
                                            className="pl-9"
                                            autoComplete="off"
                                        />
                                    </div>
                                </Field>

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
                                className="mt-4 flex flex-wrap items-center gap-2 border-t border-border pt-4 text-sm text-muted-foreground"
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
                        </FilterToolbar>
                    )}
                </Form>

                <section
                    className="overflow-hidden rounded-xl border border-border bg-card text-card-foreground"
                    aria-labelledby="low-stock-table-title"
                >
                    <div className="flex min-h-14 items-center justify-between gap-3 border-b border-border px-4">
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

                    {rows.length === 0 ? (
                        <EmptyState
                            className="px-6 py-14"
                            icon={Search}
                            title={
                                hasFilters
                                    ? 'No low-stock balances match these filters.'
                                    : 'No low-stock balances found.'
                            }
                            description={
                                hasFilters
                                    ? 'Adjust or clear the filters to see other affected balances.'
                                    : 'There are currently no zero or negative inventory balances requiring attention.'
                            }
                        />
                    ) : (
                        <>
                            <div
                                className="divide-y divide-border md:hidden"
                                data-testid="mobile-low-stock"
                            >
                                {rows.map((row) => (
                                    <article
                                        key={row.id}
                                        className="space-y-3 p-4"
                                        aria-labelledby={`low-stock-${row.id}`}
                                    >
                                        <div className="flex items-start justify-between gap-3">
                                            <div className="min-w-0">
                                                {canManage ? (
                                                    <Link
                                                        id={`low-stock-${row.id}`}
                                                        href={InventoryItemController.edit(
                                                            row.itemId,
                                                        )}
                                                        className="block truncate font-medium underline-offset-4 hover:underline focus-visible:rounded-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                                    >
                                                        {row.itemName}
                                                    </Link>
                                                ) : (
                                                    <p
                                                        id={`low-stock-${row.id}`}
                                                        className="truncate font-medium"
                                                    >
                                                        {row.itemName}
                                                    </p>
                                                )}
                                                <p className="mt-0.5 truncate text-xs text-muted-foreground">
                                                    {row.itemSku}
                                                    {row.categoryName
                                                        ? ` · ${row.categoryName}`
                                                        : ''}
                                                </p>
                                            </div>
                                            <StatusBadge
                                                label={statusLabels[row.status]}
                                                variant={statusVariant(
                                                    row.status,
                                                )}
                                            />
                                        </div>

                                        <dl className="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                                            <div>
                                                <dt className="text-xs text-muted-foreground">
                                                    Location
                                                </dt>
                                                <dd className="mt-1">
                                                    {row.locationName} /{' '}
                                                    {row.storageLocationName}
                                                </dd>
                                            </div>
                                            <div>
                                                <dt className="text-xs text-muted-foreground">
                                                    On hand
                                                </dt>
                                                <dd className="mt-1 font-semibold text-destructive tabular-nums">
                                                    {formatDecimal(
                                                        row.quantityOnHand,
                                                    )}{' '}
                                                    {row.baseUnitSymbol}
                                                </dd>
                                            </div>
                                        </dl>

                                        {canManage && (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                className="w-full"
                                                asChild
                                            >
                                                <Link
                                                    href={InventoryItemController.edit(
                                                        row.itemId,
                                                    )}
                                                >
                                                    <Pencil
                                                        className="size-4"
                                                        aria-hidden="true"
                                                    />
                                                    Edit item
                                                </Link>
                                            </Button>
                                        )}
                                    </article>
                                ))}
                            </div>

                            <div className="hidden overflow-x-auto md:block">
                                <table className="w-full min-w-[1080px] text-sm">
                                    <caption className="sr-only">
                                        Inventory balances at zero or below
                                        zero, grouped by item, location, and
                                        storage location.
                                    </caption>

                                    <thead className="border-b bg-muted/40 text-left text-xs text-muted-foreground">
                                        <tr>
                                            <th
                                                scope="col"
                                                className="px-4 py-3"
                                            >
                                                Item
                                            </th>

                                            <th
                                                scope="col"
                                                className="px-4 py-3"
                                            >
                                                SKU
                                            </th>

                                            <th
                                                scope="col"
                                                className="px-4 py-3"
                                            >
                                                Category
                                            </th>

                                            <th
                                                scope="col"
                                                className="px-4 py-3"
                                            >
                                                Location
                                            </th>

                                            <th
                                                scope="col"
                                                className="px-4 py-3"
                                            >
                                                Storage
                                            </th>

                                            <th
                                                scope="col"
                                                className="px-4 py-3 text-right"
                                            >
                                                On hand
                                            </th>

                                            <th
                                                scope="col"
                                                className="px-4 py-3"
                                            >
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
                                        {rows.map((row) => (
                                            <tr
                                                key={row.id}
                                                className="border-b border-border transition-colors last:border-b-0 hover:bg-muted/30"
                                            >
                                                <td className="px-4 py-3">
                                                    <div className="flex items-center gap-3">
                                                        <div className="min-w-0">
                                                            {canManage ? (
                                                                <Link
                                                                    href={InventoryItemController.edit(
                                                                        row.itemId,
                                                                    )}
                                                                    className="block truncate font-medium underline-offset-4 hover:underline focus-visible:rounded-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                                                >
                                                                    {
                                                                        row.itemName
                                                                    }
                                                                </Link>
                                                            ) : (
                                                                <div className="truncate font-medium">
                                                                    {
                                                                        row.itemName
                                                                    }
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
                                                    <StatusBadge
                                                        label={
                                                            statusLabels[
                                                                row.status
                                                            ]
                                                        }
                                                        variant={statusVariant(
                                                            row.status,
                                                        )}
                                                    />
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
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </>
                    )}

                    <PaginationControls
                        currentPage={pagination.current_page}
                        from={pagination.from}
                        to={pagination.to}
                        total={pagination.total}
                        lastPage={pagination.last_page}
                        previousPageUrl={pagination.prev_page_url}
                        nextPageUrl={pagination.next_page_url}
                        itemLabel="balances"
                        preserveScroll
                        preserveState
                    />
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
