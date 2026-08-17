import { Form, Head, Link } from '@inertiajs/react';
import InventoryItemController from '@/actions/App/Http/Controllers/Inventory/InventoryItemController';
import StockMovementLedgerReportController from '@/actions/App/Http/Controllers/Inventory/StockMovementLedgerReportController';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';

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

type Option = {
    id: number;
    name: string;
};

type Props = {
    rows: PaginatedStockMovementRows;
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

const formatDate = (value: string): string => new Date(value).toLocaleString();

const formatTypeLabel = (type: string): string =>
    type
        .toLowerCase()
        .split('_')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');

export default function StockMovementLedgerReport({
    rows,
    locationOptions,
    storageLocationOptions,
    itemOptions,
    typeOptions,
    filters,
    currency,
    canViewCosts,
}: Props) {
    return (
        <>
            <Head title="Stock movement ledger" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">
                            Stock movement ledger
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Immutable stock movement history in append order.
                        </p>
                    </div>

                    <Button variant="outline" asChild>
                        <Link href={InventoryItemController.index()}>
                            Inventory items
                        </Link>
                    </Button>
                </div>

                <Form
                    action={StockMovementLedgerReportController.index().url}
                    method="get"
                >
                    {({ processing }) => (
                        <div className="grid gap-4 rounded-xl border border-sidebar-border/70 p-5 md:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7 dark:border-sidebar-border">
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
                                <Label>Item</Label>
                                <select
                                    name="inventory_item_id"
                                    defaultValue={
                                        filters.inventoryItemId?.toString() ??
                                        ''
                                    }
                                    className="h-9 rounded-md border bg-background px-3 text-sm"
                                >
                                    <option value="">All items</option>

                                    {itemOptions.map((item) => (
                                        <option key={item.id} value={item.id}>
                                            {item.name}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div className="grid gap-2">
                                <Label>Type</Label>
                                <select
                                    name="type"
                                    defaultValue={filters.type ?? ''}
                                    className="h-9 rounded-md border bg-background px-3 text-sm"
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
                                <Label>From</Label>
                                <Input
                                    name="from"
                                    type="date"
                                    defaultValue={filters.from ?? ''}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label>To</Label>
                                <Input
                                    name="to"
                                    type="date"
                                    defaultValue={filters.to ?? ''}
                                />
                            </div>

                            <div className="flex items-end gap-2">
                                <Button type="submit" disabled={processing}>
                                    Apply
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link
                                        href={
                                            StockMovementLedgerReportController.index()
                                        }
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
                                <th className="px-4 py-3">Occurred</th>
                                <th className="px-4 py-3">Item</th>
                                <th className="px-4 py-3">Location</th>
                                <th className="px-4 py-3">Type</th>
                                <th className="px-4 py-3 text-right">
                                    Quantity
                                </th>
                                <th className="px-4 py-3">Source</th>
                                <th className="px-4 py-3">Actor</th>

                                {canViewCosts && (
                                    <>
                                        <th className="px-4 py-3 text-right">
                                            Unit cost
                                        </th>
                                        <th className="px-4 py-3 text-right">
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
                                        className="px-4 py-8 text-center text-muted-foreground"
                                    >
                                        No stock movements match the selected
                                        filters.
                                    </td>
                                </tr>
                            ) : (
                                rows.data.map((row) => (
                                    <tr
                                        key={row.id}
                                        className="border-b align-top last:border-b-0"
                                    >
                                        <td className="px-4 py-3">
                                            {formatDate(row.occurredAt)}
                                        </td>

                                        <td className="px-4 py-3">
                                            <div className="font-medium">
                                                {row.itemName}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {row.itemSku}
                                            </div>
                                        </td>

                                        <td className="px-4 py-3">
                                            <div>{row.locationName}</div>
                                            <div className="text-xs text-muted-foreground">
                                                {row.storageLocationName}
                                            </div>
                                        </td>

                                        <td className="px-4 py-3">
                                            {formatTypeLabel(row.type)}
                                        </td>

                                        <td className="px-4 py-3 text-right">
                                            {formatDecimal(row.quantity)}{' '}
                                            {row.baseUnitSymbol}
                                        </td>

                                        <td className="px-4 py-3">
                                            <div>{row.referenceType}</div>
                                            <div className="text-xs text-muted-foreground">
                                                #{row.referenceId}
                                            </div>
                                        </td>

                                        <td className="px-4 py-3">
                                            {row.actorName ?? '—'}
                                        </td>

                                        {canViewCosts && (
                                            <>
                                                <td className="px-4 py-3 text-right">
                                                    {row.unitCost === null
                                                        ? '—'
                                                        : `${currency} ${formatDecimal(
                                                              row.unitCost,
                                                          )}`}
                                                </td>

                                                <td className="px-4 py-3 text-right">
                                                    {row.totalCost === null
                                                        ? '—'
                                                        : `${currency} ${formatDecimal(
                                                              row.totalCost,
                                                          )}`}
                                                </td>
                                            </>
                                        )}
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                {rows.total > 0 && (
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <p className="text-sm text-muted-foreground">
                            Showing {rows.from ?? 0} to {rows.to ?? 0} of{' '}
                            {rows.total} stock movements.
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
