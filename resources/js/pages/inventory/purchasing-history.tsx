import { Form, Head, Link } from '@inertiajs/react';
import InventoryItemController from '@/actions/App/Http/Controllers/Inventory/InventoryItemController';
import PurchasingHistoryReportController from '@/actions/App/Http/Controllers/Inventory/PurchasingHistoryReportController';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';

type PurchasingHistoryRow = {
    id: number;
    purchaseOrderId: number;
    purchaseOrderNumber: string;
    purchaseOrderStatus: string;
    orderDate: string;
    supplierId: number;
    supplierName: string;
    locationId: number;
    locationName: string;
    itemId: number;
    itemName: string;
    supplierSku: string;
    orderedQuantity: string;
    purchaseUnitSymbol: string;
    baseQuantity: string;
    baseUnitSymbol: string;
    receivedBaseQuantity: string;
    remainingBaseQuantity: string;
    overReceivedBaseQuantity: string;
    receiptState: string;
    unitPrice: string;
    lineTotal: string;
};

type Option = {
    id: number;
    name: string;
};

type Props = {
    rows: PurchasingHistoryRow[];
    supplierOptions: Option[];
    locationOptions: Option[];
    filters: {
        supplierId: number | null;
        locationId: number | null;
        from: string | null;
        to: string | null;
    };
    currency: string;
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

const formatLabel = (value: string): string =>
    value
        .toLowerCase()
        .split('_')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');

export default function PurchasingHistoryReport({
    rows,
    supplierOptions,
    locationOptions,
    filters,
    currency,
}: Props) {
    return (
        <>
            <Head title="Purchasing history report" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">
                            Purchasing history
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Purchase orders and receiving history with
                            ordered-versus-received quantities.
                        </p>
                    </div>

                    <Button variant="outline" asChild>
                        <Link href={InventoryItemController.index()}>
                            Inventory items
                        </Link>
                    </Button>
                </div>

                <Form
                    action={PurchasingHistoryReportController.index().url}
                    method="get"
                >
                    {({ processing }) => (
                        <div className="grid gap-4 rounded-xl border border-sidebar-border/70 p-5 md:grid-cols-2 lg:grid-cols-5 dark:border-sidebar-border">
                            <div className="grid gap-2">
                                <Label>Supplier</Label>
                                <select
                                    name="supplier_id"
                                    defaultValue={
                                        filters.supplierId?.toString() ?? ''
                                    }
                                    className="h-9 rounded-md border bg-background px-3 text-sm"
                                >
                                    <option value="">All suppliers</option>

                                    {supplierOptions.map((supplier) => (
                                        <option
                                            key={supplier.id}
                                            value={supplier.id}
                                        >
                                            {supplier.name}
                                        </option>
                                    ))}
                                </select>
                            </div>

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
                                            PurchasingHistoryReportController.index()
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
                                <th className="px-4 py-3">PO number</th>
                                <th className="px-4 py-3">Order date</th>
                                <th className="px-4 py-3">Supplier</th>
                                <th className="px-4 py-3">Location</th>
                                <th className="px-4 py-3">Item</th>
                                <th className="px-4 py-3 text-right">
                                    Ordered
                                </th>
                                <th className="px-4 py-3 text-right">
                                    Received
                                </th>
                                <th className="px-4 py-3">Receipt state</th>
                                <th className="px-4 py-3 text-right">
                                    Unit price
                                </th>
                                <th className="px-4 py-3 text-right">
                                    Line total
                                </th>
                            </tr>
                        </thead>

                        <tbody>
                            {rows.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={10}
                                        className="px-4 py-8 text-center text-muted-foreground"
                                    >
                                        No purchasing history matches the
                                        selected filters.
                                    </td>
                                </tr>
                            ) : (
                                rows.map((row) => (
                                    <tr
                                        key={row.id}
                                        className="border-b align-top last:border-b-0"
                                    >
                                        <td className="px-4 py-3">
                                            <div className="font-medium">
                                                {row.purchaseOrderNumber}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {formatLabel(
                                                    row.purchaseOrderStatus,
                                                )}
                                            </div>
                                        </td>

                                        <td className="px-4 py-3">
                                            {row.orderDate}
                                        </td>

                                        <td className="px-4 py-3">
                                            {row.supplierName}
                                        </td>

                                        <td className="px-4 py-3">
                                            {row.locationName}
                                        </td>

                                        <td className="px-4 py-3">
                                            <div className="font-medium">
                                                {row.itemName}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {row.supplierSku}
                                            </div>
                                        </td>

                                        <td className="px-4 py-3 text-right">
                                            {formatDecimal(
                                                row.orderedQuantity,
                                            )}{' '}
                                            {row.purchaseUnitSymbol}
                                        </td>

                                        <td className="px-4 py-3 text-right">
                                            {formatDecimal(
                                                row.receivedBaseQuantity,
                                            )}{' '}
                                            {row.baseUnitSymbol}
                                        </td>

                                        <td className="px-4 py-3">
                                            {formatLabel(row.receiptState)}
                                        </td>

                                        <td className="px-4 py-3 text-right">
                                            {currency}{' '}
                                            {formatDecimal(row.unitPrice)}
                                        </td>

                                        <td className="px-4 py-3 text-right">
                                            {currency}{' '}
                                            {formatDecimal(row.lineTotal)}
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

PurchasingHistoryReport.layout = {
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
            title: 'Purchasing history',
            href: PurchasingHistoryReportController.index(),
        },
    ],
};
