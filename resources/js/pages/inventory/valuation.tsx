import { Form, Head, Link, usePage } from '@inertiajs/react';
import {
    Building2,
    CircleDollarSign,
    Download,
    Filter,
    MapPin,
    Package,
    RotateCcw,
    Search,
    Tags,
} from 'lucide-react';

import InventoryItemController from '@/actions/App/Http/Controllers/Inventory/InventoryItemController';
import InventoryValuationReportController from '@/actions/App/Http/Controllers/Inventory/InventoryValuationReportController';
import { DashboardMetricCard } from '@/components/dashboard/dashboard-metric-card';
import { EmptyState } from '@/components/empty-state';
import { FilterToolbar } from '@/components/filter-toolbar';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Field } from '@/components/ui/field';
import { NativeSelect } from '@/components/ui/native-select';
import { dashboard } from '@/routes';
import type { OrganizationContext } from '@/types';

type ValuationRow = {
    id: number;
    locationId: number;
    locationName: string;
    itemId: number;
    itemName: string;
    itemSku: string;
    categoryId: number | null;
    categoryName: string | null;
    quantityOnHand: string;
    baseUnitSymbol: string;
    averageUnitCost: string | null;
    inventoryValue: string | null;
};

type LocationTotal = {
    locationId: number;
    locationName: string;
    value: string;
};

type CategoryTotal = {
    categoryId: number | null;
    categoryName: string | null;
    value: string;
};

type Option = {
    id: number;
    name: string;
};

type Props = {
    rows: ValuationRow[];
    locationTotals: LocationTotal[];
    categoryTotals: CategoryTotal[];
    grandTotal: string | null;
    locationOptions: Option[];
    categoryOptions: Option[];
    filters: {
        locationId: number | null;
        inventoryCategoryId: number | null;
    };
    currency: string;
    canViewCosts: boolean;
};

/** Format persisted decimal strings without converting quantities or money to floats. */
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

/** Format one persisted monetary value without floating-point conversion. */
function formatCurrency(value: string, currency: string): string {
    return `${currency} ${formatDecimal(value)}`;
}

/** Keep CSV exports scoped to the exact valuation filters currently applied. */
function buildExportUrl(filters: Props['filters']): string {
    const params = new URLSearchParams();

    if (filters.locationId !== null) {
        params.set('location_id', filters.locationId.toString());
    }

    if (filters.inventoryCategoryId !== null) {
        params.set(
            'inventory_category_id',
            filters.inventoryCategoryId.toString(),
        );
    }

    const baseUrl = InventoryValuationReportController.export().url;
    const query = params.toString();

    return query === '' ? baseUrl : `${baseUrl}?${query}`;
}

/** Produce a stable grouping key for categorized and uncategorized balances. */
function categorySummaryKey(categoryId: number | null): string {
    return categoryId === null ? 'uncategorized' : `category:${categoryId}`;
}

/** Render the current inventory valuation as a compact operational report. */
export default function InventoryValuationReport({
    rows,
    locationTotals,
    categoryTotals,
    grandTotal,
    locationOptions,
    categoryOptions,
    filters,
    currency,
    canViewCosts,
}: Props) {
    const exportUrl = buildExportUrl(filters);

    const { organizationContext } = usePage<{
        organizationContext: OrganizationContext;
    }>().props;
    const canExportReports =
        organizationContext.entitlements?.grants['reports.export'] ?? false;

    const itemCount = new Set(rows.map((row) => row.itemId)).size;
    const locationCount = new Set(rows.map((row) => row.locationId)).size;
    const categoryCount = new Set(
        rows.map((row) => categorySummaryKey(row.categoryId)),
    ).size;

    const locationBalanceCounts = new Map<number, number>();
    const categoryBalanceCounts = new Map<string, number>();

    for (const row of rows) {
        locationBalanceCounts.set(
            row.locationId,
            (locationBalanceCounts.get(row.locationId) ?? 0) + 1,
        );

        const categoryKey = categorySummaryKey(row.categoryId);

        categoryBalanceCounts.set(
            categoryKey,
            (categoryBalanceCounts.get(categoryKey) ?? 0) + 1,
        );
    }

    return (
        <>
            <Head title="Inventory valuation" />

            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <PageHeader
                    title="Inventory valuation"
                    description="Current inventory value aggregated by location and category."
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
                        canViewCosts && grandTotal !== null
                            ? 'grid gap-4 sm:grid-cols-2 xl:grid-cols-4'
                            : 'grid gap-4 sm:grid-cols-2 xl:grid-cols-3'
                    }
                >
                    {canViewCosts && grandTotal !== null && (
                        <DashboardMetricCard
                            title="Total inventory value"
                            value={formatCurrency(grandTotal, currency)}
                            description="Across the current filtered stock balances"
                            icon={CircleDollarSign}
                            tone="blue"
                        />
                    )}

                    <DashboardMetricCard
                        title="Items with stock"
                        value={itemCount.toLocaleString()}
                        description="Distinct inventory items represented in the report"
                        icon={Package}
                        tone="emerald"
                    />

                    <DashboardMetricCard
                        title="Locations with stock"
                        value={locationCount.toLocaleString()}
                        description="Restaurant locations represented by current balances"
                        icon={MapPin}
                        tone="amber"
                    />

                    <DashboardMetricCard
                        title="Categories represented"
                        value={categoryCount.toLocaleString()}
                        description="Including uncategorized inventory where applicable"
                        icon={Tags}
                        tone="violet"
                    />
                </div>

                <Form
                    action={InventoryValuationReportController.index().url}
                    method="get"
                >
                    {({ errors, processing }) => (
                        <FilterToolbar>
                            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-[1fr_1fr_auto]">
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
                                            href={InventoryValuationReportController.index()}
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
                                        className="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive md:col-span-2 xl:col-span-3"
                                    >
                                        One or more valuation filters are
                                        invalid. Review the filter values or
                                        clear them and try again.
                                    </div>
                                )}
                            </div>
                        </FilterToolbar>
                    )}
                </Form>

                {canViewCosts && (
                    <div className="grid gap-4 lg:grid-cols-2">
                        <section
                            className="overflow-hidden rounded-xl border border-border bg-card text-card-foreground"
                            aria-labelledby="valuation-by-location-title"
                        >
                            <div className="flex min-h-12 items-center gap-2 border-b border-border px-4">
                                <Building2
                                    className="size-4 text-muted-foreground"
                                    aria-hidden="true"
                                />

                                <h2
                                    id="valuation-by-location-title"
                                    className="text-sm font-semibold"
                                >
                                    Valuation by location
                                </h2>
                            </div>

                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[440px] text-sm">
                                    <caption className="sr-only">
                                        Current inventory value grouped by
                                        location.
                                    </caption>

                                    <thead className="border-b bg-muted/40 text-left">
                                        <tr>
                                            <th
                                                scope="col"
                                                className="px-4 py-3 font-medium text-muted-foreground"
                                            >
                                                Location
                                            </th>

                                            <th
                                                scope="col"
                                                className="px-4 py-3 text-right font-medium text-muted-foreground"
                                            >
                                                Balances
                                            </th>

                                            <th
                                                scope="col"
                                                className="px-4 py-3 text-right font-medium text-muted-foreground"
                                            >
                                                Total value
                                            </th>
                                        </tr>
                                    </thead>

                                    <tbody>
                                        {locationTotals.length === 0 ? (
                                            <tr>
                                                <td
                                                    colSpan={3}
                                                    className="px-6 py-10 text-center text-muted-foreground"
                                                >
                                                    No location valuation totals
                                                    match the selected filters.
                                                </td>
                                            </tr>
                                        ) : (
                                            locationTotals.map((total) => (
                                                <tr
                                                    key={total.locationId}
                                                    className="border-b border-border last:border-b-0"
                                                >
                                                    <td className="px-4 py-3 font-medium">
                                                        {total.locationName}
                                                    </td>

                                                    <td className="px-4 py-3 text-right tabular-nums">
                                                        {(
                                                            locationBalanceCounts.get(
                                                                total.locationId,
                                                            ) ?? 0
                                                        ).toLocaleString()}
                                                    </td>

                                                    <td className="px-4 py-3 text-right font-medium whitespace-nowrap tabular-nums">
                                                        {formatCurrency(
                                                            total.value,
                                                            currency,
                                                        )}
                                                    </td>
                                                </tr>
                                            ))
                                        )}
                                    </tbody>

                                    {locationTotals.length > 0 &&
                                        grandTotal !== null && (
                                            <tfoot>
                                                <tr className="border-t bg-muted/30 font-medium">
                                                    <td className="px-4 py-3">
                                                        Total
                                                    </td>

                                                    <td className="px-4 py-3 text-right tabular-nums">
                                                        {rows.length.toLocaleString()}
                                                    </td>

                                                    <td className="px-4 py-3 text-right font-semibold whitespace-nowrap tabular-nums">
                                                        {formatCurrency(
                                                            grandTotal,
                                                            currency,
                                                        )}
                                                    </td>
                                                </tr>
                                            </tfoot>
                                        )}
                                </table>
                            </div>
                        </section>

                        <section
                            className="overflow-hidden rounded-xl border border-border bg-card text-card-foreground"
                            aria-labelledby="valuation-by-category-title"
                        >
                            <div className="flex min-h-12 items-center gap-2 border-b border-border px-4">
                                <Tags
                                    className="size-4 text-muted-foreground"
                                    aria-hidden="true"
                                />

                                <h2
                                    id="valuation-by-category-title"
                                    className="text-sm font-semibold"
                                >
                                    Valuation by category
                                </h2>
                            </div>

                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[440px] text-sm">
                                    <caption className="sr-only">
                                        Current inventory value grouped by
                                        category.
                                    </caption>

                                    <thead className="border-b bg-muted/40 text-left">
                                        <tr>
                                            <th
                                                scope="col"
                                                className="px-4 py-3 font-medium text-muted-foreground"
                                            >
                                                Category
                                            </th>

                                            <th
                                                scope="col"
                                                className="px-4 py-3 text-right font-medium text-muted-foreground"
                                            >
                                                Balances
                                            </th>

                                            <th
                                                scope="col"
                                                className="px-4 py-3 text-right font-medium text-muted-foreground"
                                            >
                                                Total value
                                            </th>
                                        </tr>
                                    </thead>

                                    <tbody>
                                        {categoryTotals.length === 0 ? (
                                            <tr>
                                                <td
                                                    colSpan={3}
                                                    className="px-6 py-10 text-center text-muted-foreground"
                                                >
                                                    No category valuation totals
                                                    match the selected filters.
                                                </td>
                                            </tr>
                                        ) : (
                                            categoryTotals.map((total) => {
                                                const key = categorySummaryKey(
                                                    total.categoryId,
                                                );

                                                return (
                                                    <tr
                                                        key={key}
                                                        className="border-b border-border last:border-b-0"
                                                    >
                                                        <td className="px-4 py-3 font-medium">
                                                            {total.categoryName ??
                                                                'Uncategorized'}
                                                        </td>

                                                        <td className="px-4 py-3 text-right tabular-nums">
                                                            {(
                                                                categoryBalanceCounts.get(
                                                                    key,
                                                                ) ?? 0
                                                            ).toLocaleString()}
                                                        </td>

                                                        <td className="px-4 py-3 text-right font-medium whitespace-nowrap tabular-nums">
                                                            {formatCurrency(
                                                                total.value,
                                                                currency,
                                                            )}
                                                        </td>
                                                    </tr>
                                                );
                                            })
                                        )}
                                    </tbody>

                                    {categoryTotals.length > 0 &&
                                        grandTotal !== null && (
                                            <tfoot>
                                                <tr className="border-t bg-muted/30 font-medium">
                                                    <td className="px-4 py-3">
                                                        Total
                                                    </td>

                                                    <td className="px-4 py-3 text-right tabular-nums">
                                                        {rows.length.toLocaleString()}
                                                    </td>

                                                    <td className="px-4 py-3 text-right font-semibold whitespace-nowrap tabular-nums">
                                                        {formatCurrency(
                                                            grandTotal,
                                                            currency,
                                                        )}
                                                    </td>
                                                </tr>
                                            </tfoot>
                                        )}
                                </table>
                            </div>
                        </section>
                    </div>
                )}

                <section
                    className="overflow-hidden rounded-xl border border-border bg-card text-card-foreground"
                    aria-labelledby="valuation-details-title"
                >
                    <div className="flex min-h-14 flex-wrap items-center justify-between gap-3 border-b border-border px-4 py-2">
                        <div>
                            <h2
                                id="valuation-details-title"
                                className="text-sm font-semibold"
                            >
                                Inventory valuation details
                            </h2>

                            <p className="mt-0.5 text-xs text-muted-foreground">
                                {rows.length.toLocaleString()}{' '}
                                {rows.length === 1
                                    ? 'balance matches'
                                    : 'balances match'}{' '}
                                the selected filters
                            </p>
                        </div>

                        {canExportReports && (
                            <Button variant="outline" size="sm" asChild>
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

                    {rows.length === 0 ? (
                        <EmptyState
                            className="px-6 py-14"
                            icon={Search}
                            title="No inventory balances found"
                            description="Adjust or clear the filters to view current inventory valuation."
                        />
                    ) : (
                        <>
                            <div
                                className="divide-y divide-border md:hidden"
                                data-testid="mobile-valuation"
                            >
                                {rows.map((row) => (
                                    <article
                                        key={row.id}
                                        className="space-y-3 p-4"
                                        aria-labelledby={`valuation-${row.id}`}
                                    >
                                        <div className="min-w-0">
                                            <p
                                                id={`valuation-${row.id}`}
                                                className="truncate font-medium"
                                            >
                                                {row.itemName}
                                            </p>
                                            <p className="mt-0.5 truncate text-xs text-muted-foreground">
                                                {row.itemSku} ·{' '}
                                                {row.categoryName ??
                                                    'Uncategorized'}
                                            </p>
                                        </div>

                                        <dl className="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                                            <div>
                                                <dt className="text-xs text-muted-foreground">
                                                    Location
                                                </dt>
                                                <dd className="mt-1">
                                                    {row.locationName}
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
                                        Current inventory valuation by item and
                                        location. Quantities remain in each
                                        item's base unit.
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
                                                    <div className="min-w-0">
                                                        <div className="truncate font-medium">
                                                            {row.itemName}
                                                        </div>

                                                        <div className="mt-0.5 truncate text-xs text-muted-foreground">
                                                            {row.itemSku}
                                                        </div>
                                                    </div>
                                                </td>

                                                <td className="px-4 py-3">
                                                    {row.categoryName ?? (
                                                        <span className="text-muted-foreground">
                                                            Uncategorized
                                                        </span>
                                                    )}
                                                </td>

                                                <td className="px-4 py-3">
                                                    {row.locationName}
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
                                        grandTotal !== null &&
                                        rows.length > 0 && (
                                            <tfoot>
                                                <tr className="border-t bg-muted/30 font-medium">
                                                    <td
                                                        className="px-4 py-3"
                                                        colSpan={5}
                                                    >
                                                        Total inventory value
                                                    </td>

                                                    <td className="px-4 py-3 text-right font-semibold whitespace-nowrap tabular-nums">
                                                        {formatCurrency(
                                                            grandTotal,
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

InventoryValuationReport.layout = {
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
            title: 'Valuation',
            href: InventoryValuationReportController.index(),
        },
    ],
};
