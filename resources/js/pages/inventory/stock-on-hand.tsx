import { Form, Head, Link } from '@inertiajs/react';
import InventoryItemController from '@/actions/App/Http/Controllers/Inventory/InventoryItemController';
import StockOnHandReportController from '@/actions/App/Http/Controllers/Inventory/StockOnHandReportController';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
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

type Option = {
    id: number;
    name: string;
};

type Props = {
    rows: StockOnHandRow[];
    locationOptions: Option[];
    storageLocationOptions: Option[];
    categoryOptions: Option[];
    filters: {
        locationId: number | null;
        storageLocationId: number | null;
        inventoryCategoryId: number | null;
        inventoryItemId: number | null;
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

export default function StockOnHandReport({
    rows,
    locationOptions,
    storageLocationOptions,
    categoryOptions,
    filters,
    currency,
    canViewCosts,
}: Props) {
    const totalValue = canViewCosts
        ? rows.reduce((sum, row) => sum + Number(row.inventoryValue ?? '0'), 0)
        : null;

    return (
        <>
            <Head title="Stock on hand report" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">
                            Stock on hand
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Current balance quantities and values by location.
                        </p>
                    </div>

                    <Button variant="outline" asChild>
                        <Link href={InventoryItemController.index()}>
                            Inventory items
                        </Link>
                    </Button>
                </div>

                <Form
                    action={StockOnHandReportController.index().url}
                    method="get"
                >
                    {({ processing }) => (
                        <div className="grid gap-4 rounded-xl border border-sidebar-border/70 p-5 md:grid-cols-[1fr_1fr_1fr_1fr_auto_auto] dark:border-sidebar-border">
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
                                <Label>Storage location</Label>
                                <select
                                    name="storage_location_id"
                                    defaultValue={
                                        filters.storageLocationId?.toString() ??
                                        ''
                                    }
                                    className="h-9 rounded-md border bg-background px-3 text-sm"
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

                            <div className="grid gap-2">
                                <Label>Item</Label>
                                <input
                                    type="number"
                                    name="inventory_item_id"
                                    defaultValue={
                                        filters.inventoryItemId?.toString() ??
                                        ''
                                    }
                                    placeholder="Item ID"
                                    className="h-9 rounded-md border bg-background px-3 text-sm"
                                />
                            </div>

                            <div className="flex items-end">
                                <Button type="submit" disabled={processing}>
                                    Apply
                                </Button>
                            </div>

                            <div className="flex items-end">
                                <Button variant="outline" asChild>
                                    <Link
                                        href={StockOnHandReportController.index()}
                                    >
                                        Clear
                                    </Link>
                                </Button>
                            </div>
                        </div>
                    )}
                </Form>

                <div className="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full text-sm">
                        <thead className="border-b text-left">
                            <tr>
                                <th className="px-4 py-3">Item</th>
                                <th className="px-4 py-3">Category</th>
                                <th className="px-4 py-3">Location</th>
                                <th className="px-4 py-3">Storage</th>
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
                                        colSpan={canViewCosts ? 7 : 5}
                                        className="px-4 py-8 text-center text-muted-foreground"
                                    >
                                        No stock on hand matches the selected
                                        filters.
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

                                        <td className="px-4 py-3">
                                            {row.storageLocationName}
                                        </td>

                                        <td className="px-4 py-3 text-right">
                                            {formatDecimal(row.quantityOnHand)}{' '}
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
                                                    {row.inventoryValue === null
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

                        {canViewCosts && totalValue !== null && (
                            <tfoot>
                                <tr className="border-t font-medium">
                                    <td className="px-4 py-3" colSpan={6}>
                                        Total value
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        {currency}{' '}
                                        {formatDecimal(totalValue.toFixed(4))}
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
