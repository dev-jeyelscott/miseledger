import { Form, Head, Link } from '@inertiajs/react';
import {
    Boxes,
    Filter,
    MapPin,
    Package,
    RotateCcw,
    Search,
} from 'lucide-react';

import InventoryItemController from '@/actions/App/Http/Controllers/Inventory/InventoryItemController';
import StockOnHandReportController from '@/actions/App/Http/Controllers/Inventory/StockOnHandReportController';
import { DashboardMetricCard } from '@/components/dashboard/dashboard-metric-card';
import { EmptyState } from '@/components/empty-state';
import { FilterToolbar } from '@/components/filter-toolbar';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { dashboard } from '@/routes';

type StockOnHandRow = {
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
    averageUnitCost: string | null;
    inventoryValue: string | null;
};

type StockOnHandSummary = {
    itemsWithStockCount: number;
    storageLocationCount: number;
    totalValue: string | null;
};

type Option = {
    id: number;
    name: string;
};

type Props = {
    rows: StockOnHandRow[];
    summary: StockOnHandSummary;
    locationOptions: Option[];
    storageLocationOptions: Option[];
    categoryOptions: Option[];
    filters: {
        locationId: number | null;
        storageLocationId: number | null;
        inventoryCategoryId: number | null;
        inventoryItemId: number | null;
        itemSearch: string | null;
    };
    currency: string;
    canViewCosts: boolean;
};

/** Format persisted decimal strings for compact operational display. */
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

/** Format one currency amount without converting it to floating point. */
function formatCurrency(value: string, currency: string): string {
    return `${currency} ${formatDecimal(value)}`;
}

export default function StockOnHandReport({
    rows,
    summary,
    locationOptions,
    storageLocationOptions,
    categoryOptions,
    filters,
    currency,
    canViewCosts,
}: Props) {
    const itemSearchDefault =
        filters.itemSearch ?? filters.inventoryItemId?.toString() ?? '';

    return (
        <>
            <Head title="Stock on hand" />

            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <PageHeader
                    title="Stock on hand"
                    description="Current balance quantities and values by location."
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

                <div
                    className={
                        canViewCosts
                            ? 'grid gap-4 md:grid-cols-2 xl:grid-cols-3'
                            : 'grid gap-4 md:grid-cols-2'
                    }
                >
                    {canViewCosts && summary.totalValue !== null && (
                        <DashboardMetricCard
                            title="Total stock value"
                            value={formatCurrency(summary.totalValue, currency)}
                            description="Across the current filtered stock balances"
                            icon={Package}
                            tone="blue"
                        />
                    )}

                    <DashboardMetricCard
                        title="Active items"
                        value={summary.itemsWithStockCount}
                        description="Items with stock on hand"
                        icon={Boxes}
                        tone="emerald"
                    />

                    <DashboardMetricCard
                        title="Locations"
                        value={summary.storageLocationCount}
                        description="Storage locations holding stock"
                        icon={MapPin}
                        tone="violet"
                    />
                </div>

                <Form
                    action={StockOnHandReportController.index().url}
                    method="get"
                >
                    {({ processing }) => (
                        <FilterToolbar>
                            <div className="grid gap-4 lg:grid-cols-2 xl:grid-cols-[1fr_1fr_1fr_1.3fr_auto]">
                                <Field id="location_id" label="Location">
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

                                <Field id="item" label="Item">
                                    <div className="relative">
                                        <Search
                                            className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                            aria-hidden="true"
                                        />

                                        <Input
                                            type="search"
                                            name="item"
                                            defaultValue={itemSearchDefault}
                                            placeholder="Search by item ID, SKU, or name"
                                            className="pl-9"
                                            autoComplete="off"
                                        />
                                    </div>
                                </Field>

                                <div className="flex items-end gap-2 lg:col-span-2 xl:col-span-1">
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
                                            href={StockOnHandReportController.index()}
                                        >
                                            <RotateCcw
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                            Clear
                                        </Link>
                                    </Button>
                                </div>
                            </div>
                        </FilterToolbar>
                    )}
                </Form>

                <section
                    className="overflow-hidden rounded-xl border border-border bg-card text-card-foreground"
                    aria-labelledby="stock-on-hand-table-title"
                >
                    <div className="flex min-h-12 items-center justify-between gap-3 border-b border-border px-4">
                        <div>
                            <h2
                                id="stock-on-hand-table-title"
                                className="text-sm font-semibold"
                            >
                                Current stock balances
                            </h2>

                            <p className="mt-0.5 text-xs text-muted-foreground">
                                {rows.length}{' '}
                                {rows.length === 1
                                    ? 'balance matches'
                                    : 'balances match'}{' '}
                                the selected filters
                            </p>
                        </div>
                    </div>

                    {rows.length === 0 ? (
                        <EmptyState
                            className="px-6 py-14"
                            icon={Search}
                            title="No stock balances found"
                            description="Adjust or clear the filters to view available stock."
                        />
                    ) : (
                        <>
                            <div
                                className="divide-y divide-border md:hidden"
                                data-testid="mobile-stock-on-hand"
                            >
                                {rows.map((row) => (
                                    <article
                                        key={row.id}
                                        className="space-y-3 p-4"
                                        aria-labelledby={`stock-on-hand-${row.id}`}
                                    >
                                        <div className="min-w-0">
                                            <p
                                                id={`stock-on-hand-${row.id}`}
                                                className="truncate font-medium"
                                            >
                                                {row.itemName}
                                            </p>
                                            <p className="mt-0.5 truncate text-xs text-muted-foreground">
                                                {row.itemSku}
                                                {row.categoryName
                                                    ? ` · ${row.categoryName}`
                                                    : ''}
                                            </p>
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
                                                    Quantity
                                                </dt>
                                                <dd className="mt-1 tabular-nums">
                                                    {formatDecimal(
                                                        row.quantityOnHand,
                                                    )}{' '}
                                                    {row.baseUnitSymbol}
                                                </dd>
                                            </div>
                                            {canViewCosts && (
                                                <>
                                                    <div>
                                                        <dt className="text-xs text-muted-foreground">
                                                            Avg. cost
                                                        </dt>
                                                        <dd className="mt-1 tabular-nums">
                                                            {row.averageUnitCost ===
                                                            null
                                                                ? '—'
                                                                : formatCurrency(
                                                                      row.averageUnitCost,
                                                                      currency,
                                                                  )}
                                                        </dd>
                                                    </div>
                                                    <div>
                                                        <dt className="text-xs text-muted-foreground">
                                                            Value
                                                        </dt>
                                                        <dd className="mt-1 font-medium tabular-nums">
                                                            {row.inventoryValue ===
                                                            null
                                                                ? '—'
                                                                : formatCurrency(
                                                                      row.inventoryValue,
                                                                      currency,
                                                                  )}
                                                        </dd>
                                                    </div>
                                                </>
                                            )}
                                        </dl>
                                    </article>
                                ))}
                            </div>

                            <div className="hidden overflow-x-auto md:block">
                                <table className="w-full min-w-[900px] text-sm">
                                    <caption className="sr-only">
                                        Current stock balances grouped by item,
                                        location, and storage location.
                                    </caption>

                                    <thead className="border-b bg-muted/40 text-left">
                                        <tr>
                                            <th
                                                scope="col"
                                                className="px-4 py-3 font-medium text-muted-foreground"
                                            >
                                                Item
                                            </th>

                                            <th
                                                scope="col"
                                                className="px-4 py-3 font-medium text-muted-foreground"
                                            >
                                                Category
                                            </th>

                                            <th
                                                scope="col"
                                                className="px-4 py-3 font-medium text-muted-foreground"
                                            >
                                                Location
                                            </th>

                                            <th
                                                scope="col"
                                                className="px-4 py-3 font-medium text-muted-foreground"
                                            >
                                                Storage
                                            </th>

                                            <th
                                                scope="col"
                                                className="px-4 py-3 text-right font-medium text-muted-foreground"
                                            >
                                                Quantity
                                            </th>

                                            {canViewCosts && (
                                                <>
                                                    <th
                                                        scope="col"
                                                        className="px-4 py-3 text-right font-medium text-muted-foreground"
                                                    >
                                                        Avg. cost
                                                    </th>

                                                    <th
                                                        scope="col"
                                                        className="px-4 py-3 text-right font-medium text-muted-foreground"
                                                    >
                                                        Value
                                                    </th>
                                                </>
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
                                                            <div className="truncate font-medium">
                                                                {row.itemName}
                                                            </div>

                                                            <div className="mt-0.5 truncate text-xs text-muted-foreground">
                                                                {row.itemSku}
                                                            </div>
                                                        </div>
                                                    </div>
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

                                                <td className="px-4 py-3 text-right font-medium whitespace-nowrap tabular-nums">
                                                    {formatDecimal(
                                                        row.quantityOnHand,
                                                    )}{' '}
                                                    <span className="font-normal text-muted-foreground">
                                                        {row.baseUnitSymbol}
                                                    </span>
                                                </td>

                                                {canViewCosts && (
                                                    <>
                                                        <td className="px-4 py-3 text-right whitespace-nowrap tabular-nums">
                                                            {row.averageUnitCost ===
                                                            null
                                                                ? '—'
                                                                : formatCurrency(
                                                                      row.averageUnitCost,
                                                                      currency,
                                                                  )}
                                                        </td>

                                                        <td className="px-4 py-3 text-right font-medium whitespace-nowrap tabular-nums">
                                                            {row.inventoryValue ===
                                                            null
                                                                ? '—'
                                                                : formatCurrency(
                                                                      row.inventoryValue,
                                                                      currency,
                                                                  )}
                                                        </td>
                                                    </>
                                                )}
                                            </tr>
                                        ))}
                                    </tbody>

                                    {canViewCosts &&
                                        summary.totalValue !== null &&
                                        rows.length > 0 && (
                                            <tfoot>
                                                <tr className="border-t bg-muted/30 font-medium">
                                                    <td
                                                        className="px-4 py-3"
                                                        colSpan={6}
                                                    >
                                                        Total value
                                                    </td>

                                                    <td className="px-4 py-3 text-right font-semibold whitespace-nowrap tabular-nums">
                                                        {formatCurrency(
                                                            summary.totalValue,
                                                            currency,
                                                        )}
                                                    </td>
                                                </tr>
                                            </tfoot>
                                        )}
                                </table>
                            </div>
                        </>
                    )}
                </section>
            </div>
        </>
    );
}

StockOnHandReport.layout = {
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
            title: 'Stock on hand',
            href: StockOnHandReportController.index(),
        },
    ],
};
