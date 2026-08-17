import { Form, Head, Link } from '@inertiajs/react';
import InventoryItemController from '@/actions/App/Http/Controllers/Inventory/InventoryItemController';
import InventoryValuationReportController from '@/actions/App/Http/Controllers/Inventory/InventoryValuationReportController';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';

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

type Total = {
    quantity: string;
    value: string;
};

type LocationTotal = Total & {
    locationId: number;
    locationName: string;
};

type CategoryTotal = Total & {
    categoryId: number | null;
    categoryName: string | null;
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

const formatDecimal = (value: string): string => {
    const [rawInteger, rawDecimal = ''] = value.trim().split('.');
    const negative = rawInteger.startsWith('-');
    const integerDigits = negative ? rawInteger.slice(1) : rawInteger;
    const groupedInteger = integerDigits.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    const decimal = rawDecimal.replace(/0+$/, '');

    return `${negative ? '-' : ''}${groupedInteger}${
        decimal === '' ? '' : `.${decimal}`
    }`;
};

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
    return (
        <>
            <Head title="Inventory valuation report" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">
                            Inventory valuation
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Current inventory value aggregated by location and
                            category.
                        </p>
                    </div>

                    <Button variant="outline" asChild>
                        <Link href={InventoryItemController.index()}>
                            Inventory items
                        </Link>
                    </Button>
                </div>

                <Form
                    action={InventoryValuationReportController.index().url}
                    method="get"
                >
                    {({ processing }) => (
                        <div className="grid gap-4 rounded-xl border border-sidebar-border/70 p-5 md:grid-cols-[1fr_1fr_auto_auto] dark:border-sidebar-border">
                            <div className="grid gap-2">
                                <Label>Location</Label>
                                <select
                                    name="location_id"
                                    defaultValue={
                                        filters.locationId?.toString() ?? ''
                                    }
                                    className="h-9 rounded-md border bg-background px-3 text-sm"
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
                                <Label>Category</Label>
                                <select
                                    name="inventory_category_id"
                                    defaultValue={
                                        filters.inventoryCategoryId?.toString() ??
                                        ''
                                    }
                                    className="h-9 rounded-md border bg-background px-3 text-sm"
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

                            <div className="flex items-end">
                                <Button type="submit" disabled={processing}>
                                    Apply
                                </Button>
                            </div>

                            <div className="flex items-end">
                                <Button variant="outline" asChild>
                                    <Link
                                        href={InventoryValuationReportController.index()}
                                    >
                                        Clear
                                    </Link>
                                </Button>
                            </div>
                        </div>
                    )}
                </Form>

                {canViewCosts && (
                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                            <table className="w-full text-sm">
                                <thead className="border-b text-left">
                                    <tr>
                                        <th className="px-4 py-3">
                                            Location
                                        </th>
                                        <th className="px-4 py-3 text-right">
                                            Quantity
                                        </th>
                                        <th className="px-4 py-3 text-right">
                                            Value
                                        </th>
                                    </tr>
                                </thead>

                                <tbody>
                                    {locationTotals.length === 0 ? (
                                        <tr>
                                            <td
                                                colSpan={3}
                                                className="px-4 py-8 text-center text-muted-foreground"
                                            >
                                                No totals to report.
                                            </td>
                                        </tr>
                                    ) : (
                                        locationTotals.map((total) => (
                                            <tr
                                                key={total.locationId}
                                                className="border-b last:border-b-0"
                                            >
                                                <td className="px-4 py-3">
                                                    {total.locationName}
                                                </td>
                                                <td className="px-4 py-3 text-right">
                                                    {formatDecimal(
                                                        total.quantity,
                                                    )}
                                                </td>
                                                <td className="px-4 py-3 text-right">
                                                    {currency}{' '}
                                                    {formatDecimal(
                                                        total.value,
                                                    )}
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>

                        <div className="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                            <table className="w-full text-sm">
                                <thead className="border-b text-left">
                                    <tr>
                                        <th className="px-4 py-3">
                                            Category
                                        </th>
                                        <th className="px-4 py-3 text-right">
                                            Quantity
                                        </th>
                                        <th className="px-4 py-3 text-right">
                                            Value
                                        </th>
                                    </tr>
                                </thead>

                                <tbody>
                                    {categoryTotals.length === 0 ? (
                                        <tr>
                                            <td
                                                colSpan={3}
                                                className="px-4 py-8 text-center text-muted-foreground"
                                            >
                                                No totals to report.
                                            </td>
                                        </tr>
                                    ) : (
                                        categoryTotals.map((total) => (
                                            <tr
                                                key={total.categoryId ?? 'uncategorized'}
                                                className="border-b last:border-b-0"
                                            >
                                                <td className="px-4 py-3">
                                                    {total.categoryName ??
                                                        'Uncategorized'}
                                                </td>
                                                <td className="px-4 py-3 text-right">
                                                    {formatDecimal(
                                                        total.quantity,
                                                    )}
                                                </td>
                                                <td className="px-4 py-3 text-right">
                                                    {currency}{' '}
                                                    {formatDecimal(
                                                        total.value,
                                                    )}
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}

                <div className="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full text-sm">
                        <thead className="border-b text-left">
                            <tr>
                                <th className="px-4 py-3">Item</th>
                                <th className="px-4 py-3">Category</th>
                                <th className="px-4 py-3">Location</th>
                                <th className="px-4 py-3 text-right">
                                    Quantity
                                </th>

                                {canViewCosts && (
                                    <>
                                        <th className="px-4 py-3 text-right">
                                            Avg. cost
                                        </th>
                                        <th className="px-4 py-3 text-right">
                                            Value
                                        </th>
                                    </>
                                )}
                            </tr>
                        </thead>

                        <tbody>
                            {rows.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={canViewCosts ? 6 : 4}
                                        className="px-4 py-8 text-center text-muted-foreground"
                                    >
                                        No inventory value matches the
                                        selected filters.
                                    </td>
                                </tr>
                            ) : (
                                rows.map((row) => (
                                    <tr
                                        key={row.id}
                                        className="border-b last:border-b-0"
                                    >
                                        <td className="px-4 py-3">
                                            <div className="font-medium">
                                                {row.itemName}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {row.itemSku}
                                            </div>
                                        </td>

                                        <td className="px-4 py-3">
                                            {row.categoryName ?? '—'}
                                        </td>

                                        <td className="px-4 py-3">
                                            {row.locationName}
                                        </td>

                                        <td className="px-4 py-3 text-right">
                                            {formatDecimal(
                                                row.quantityOnHand,
                                            )}{' '}
                                            {row.baseUnitSymbol}
                                        </td>

                                        {canViewCosts && (
                                            <>
                                                <td className="px-4 py-3 text-right">
                                                    {row.averageUnitCost ===
                                                    null
                                                        ? '—'
                                                        : `${currency} ${formatDecimal(
                                                              row.averageUnitCost,
                                                          )}`}
                                                </td>

                                                <td className="px-4 py-3 text-right">
                                                    {row.inventoryValue ===
                                                    null
                                                        ? '—'
                                                        : `${currency} ${formatDecimal(
                                                              row.inventoryValue,
                                                          )}`}
                                                </td>
                                            </>
                                        )}
                                    </tr>
                                ))
                            )}
                        </tbody>

                        {canViewCosts && grandTotal !== null && (
                            <tfoot>
                                <tr className="border-t font-medium">
                                    <td className="px-4 py-3" colSpan={5}>
                                        Total value
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        {currency}{' '}
                                        {formatDecimal(grandTotal)}
                                    </td>
                                </tr>
                            </tfoot>
                        )}
                    </table>
                </div>
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
