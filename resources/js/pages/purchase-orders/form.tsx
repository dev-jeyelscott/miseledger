import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import GoodsReceiptController from '@/actions/App/Http/Controllers/Purchasing/GoodsReceiptController';
import PurchaseOrderController from '@/actions/App/Http/Controllers/Purchasing/PurchaseOrderController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';

type SupplierItemOption = {
    id: number;
    supplierSku: string;
    itemName: string;
    purchaseUnit: string;
    baseQuantity: string;
    currentPrice: string;
};

type SupplierOption = {
    id: number;
    name: string;
    items: SupplierItemOption[];
};

type LocationOption = {
    id: number;
    name: string;
};

type PurchaseOrderLine = {
    id: number;
    supplierItemId: number;
    itemName: string;
    supplierSku: string;
    orderedQuantity: string;
    purchaseUnit: {
        id: number;
        symbol: string;
    };
    baseQuantity: string;
    unitPrice: string;
    lineTotal: string;
    receivedBaseQuantity: string;
};

type PurchaseOrder = {
    id: number;
    number: string;
    status: string;
    supplierId: number;
    supplierName: string;
    locationId: number;
    locationName: string;
    orderDate: string;
    expectedDeliveryDate: string | null;
    subtotal: string;
    taxTotal: string;
    discountTotal: string;
    total: string;
    notes: string | null;
    approvedAt: string | null;
    lines: PurchaseOrderLine[];
};

type LineState = {
    supplierItemId: string;
    orderedQuantity: string;
};

type Props = {
    purchaseOrder: PurchaseOrder | null;
    supplierOptions: SupplierOption[];
    locationOptions: LocationOption[];
    currency: string;
    canManage: boolean;
    canReceive: boolean;
};

export default function PurchaseOrderForm({
    purchaseOrder,
    supplierOptions,
    locationOptions,
    currency,
    canManage,
    canReceive,
}: Props) {
    const editable =
        purchaseOrder === null ||
        (purchaseOrder.status === 'draft' && canManage);

    const [supplierId, setSupplierId] = useState(
        purchaseOrder?.supplierId.toString() ?? '',
    );

    const [lines, setLines] = useState<LineState[]>(
        purchaseOrder?.lines.map((line) => ({
            supplierItemId: line.supplierItemId.toString(),
            orderedQuantity: line.orderedQuantity,
        })) ?? [
            {
                supplierItemId: '',
                orderedQuantity: '1',
            },
        ],
    );

    const selectedSupplier = supplierOptions.find(
        (supplier) => supplier.id.toString() === supplierId,
    );

    const addLine = () => {
        setLines((current) => [
            ...current,
            {
                supplierItemId: '',
                orderedQuantity: '1',
            },
        ]);
    };

    const removeLine = (index: number) => {
        setLines((current) =>
            current.filter((_, currentIndex) => currentIndex !== index),
        );
    };

    const updateLine = (
        index: number,
        field: keyof LineState,
        value: string,
    ) => {
        setLines((current) =>
            current.map((line, currentIndex) =>
                currentIndex === index
                    ? {
                          ...line,
                          [field]: value,
                      }
                    : line,
            ),
        );
    };

    const title =
        purchaseOrder === null ? 'Create purchase order' : purchaseOrder.number;

    const formAttributes =
        purchaseOrder === null
            ? PurchaseOrderController.store.form()
            : PurchaseOrderController.update.form.put(purchaseOrder.id);

    return (
        <>
            <Head title={title} />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div>
                    <h1 className="text-2xl font-semibold">{title}</h1>
                    {purchaseOrder && (
                        <p className="mt-1 text-sm text-muted-foreground capitalize">
                            {purchaseOrder.status.replaceAll('_', ' ')}
                        </p>
                    )}
                </div>

                {editable ? (
                    <Form
                        {...formAttributes}
                        options={{ preserveState: 'errors' }}
                        className="space-y-6"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-5 rounded-xl border border-sidebar-border/70 p-5 md:grid-cols-2 dark:border-sidebar-border">
                                    <div className="grid gap-2">
                                        <Label htmlFor="number">
                                            PO number
                                        </Label>
                                        <Input
                                            id="number"
                                            name="number"
                                            defaultValue={
                                                purchaseOrder?.number ?? ''
                                            }
                                            required
                                        />
                                        <InputError message={errors.number} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="supplier_id">
                                            Supplier
                                        </Label>
                                        <select
                                            id="supplier_id"
                                            name="supplier_id"
                                            value={supplierId}
                                            onChange={(event) => {
                                                setSupplierId(
                                                    event.target.value,
                                                );
                                                setLines([
                                                    {
                                                        supplierItemId: '',
                                                        orderedQuantity: '1',
                                                    },
                                                ]);
                                            }}
                                            required
                                            className="h-9 rounded-md border bg-background px-3 text-sm"
                                        >
                                            <option value="">
                                                Select supplier
                                            </option>
                                            {supplierOptions.map((supplier) => (
                                                <option
                                                    key={supplier.id}
                                                    value={supplier.id}
                                                >
                                                    {supplier.name}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError
                                            message={errors.supplier_id}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="location_id">
                                            Destination location
                                        </Label>
                                        <select
                                            id="location_id"
                                            name="location_id"
                                            defaultValue={
                                                purchaseOrder?.locationId ?? ''
                                            }
                                            required
                                            className="h-9 rounded-md border bg-background px-3 text-sm"
                                        >
                                            <option value="">
                                                Select location
                                            </option>
                                            {locationOptions.map((location) => (
                                                <option
                                                    key={location.id}
                                                    value={location.id}
                                                >
                                                    {location.name}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError
                                            message={errors.location_id}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="order_date">
                                            Order date
                                        </Label>
                                        <Input
                                            id="order_date"
                                            name="order_date"
                                            type="date"
                                            defaultValue={
                                                purchaseOrder?.orderDate ??
                                                new Date()
                                                    .toISOString()
                                                    .slice(0, 10)
                                            }
                                            required
                                        />
                                        <InputError
                                            message={errors.order_date}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="expected_delivery_date">
                                            Expected delivery
                                        </Label>
                                        <Input
                                            id="expected_delivery_date"
                                            name="expected_delivery_date"
                                            type="date"
                                            defaultValue={
                                                purchaseOrder?.expectedDeliveryDate ??
                                                ''
                                            }
                                        />
                                        <InputError
                                            message={
                                                errors.expected_delivery_date
                                            }
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="tax_total">
                                            Tax total
                                        </Label>
                                        <Input
                                            id="tax_total"
                                            name="tax_total"
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            defaultValue={
                                                purchaseOrder?.taxTotal ??
                                                '0.00'
                                            }
                                            required
                                        />
                                        <InputError
                                            message={errors.tax_total}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="discount_total">
                                            Discount total
                                        </Label>
                                        <Input
                                            id="discount_total"
                                            name="discount_total"
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            defaultValue={
                                                purchaseOrder?.discountTotal ??
                                                '0.00'
                                            }
                                            required
                                        />
                                        <InputError
                                            message={errors.discount_total}
                                        />
                                    </div>

                                    <div className="grid gap-2 md:col-span-2">
                                        <Label htmlFor="notes">Notes</Label>
                                        <textarea
                                            id="notes"
                                            name="notes"
                                            defaultValue={
                                                purchaseOrder?.notes ?? ''
                                            }
                                            rows={3}
                                            className="rounded-md border bg-background px-3 py-2 text-sm"
                                        />
                                        <InputError message={errors.notes} />
                                    </div>
                                </div>

                                <div className="space-y-4 rounded-xl border border-sidebar-border/70 p-5 dark:border-sidebar-border">
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <h2 className="font-semibold">
                                                Purchase lines
                                            </h2>
                                            <p className="text-sm text-muted-foreground">
                                                Prices and pack conversions are
                                                snapshotted server-side.
                                            </p>
                                        </div>

                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={addLine}
                                            disabled={!selectedSupplier}
                                        >
                                            Add line
                                        </Button>
                                    </div>

                                    {lines.map((line, index) => (
                                        <div
                                            key={index}
                                            className="grid gap-4 border-t pt-4 md:grid-cols-[1fr_180px_auto]"
                                        >
                                            <div className="grid gap-2">
                                                <Label>Supplier item</Label>
                                                <select
                                                    name={`lines[${index}][supplier_item_id]`}
                                                    value={line.supplierItemId}
                                                    onChange={(event) =>
                                                        updateLine(
                                                            index,
                                                            'supplierItemId',
                                                            event.target.value,
                                                        )
                                                    }
                                                    required
                                                    className="h-9 rounded-md border bg-background px-3 text-sm"
                                                >
                                                    <option value="">
                                                        Select item
                                                    </option>
                                                    {selectedSupplier?.items.map(
                                                        (item) => (
                                                            <option
                                                                key={item.id}
                                                                value={item.id}
                                                            >
                                                                {item.itemName}{' '}
                                                                —{' '}
                                                                {
                                                                    item.supplierSku
                                                                }{' '}
                                                                (
                                                                {
                                                                    item.purchaseUnit
                                                                }
                                                                , {currency}{' '}
                                                                {
                                                                    item.currentPrice
                                                                }
                                                                )
                                                            </option>
                                                        ),
                                                    )}
                                                </select>
                                                <InputError
                                                    message={
                                                        errors[
                                                            `lines.${index}.supplier_item_id`
                                                        ]
                                                    }
                                                />
                                            </div>

                                            <div className="grid gap-2">
                                                <Label>Quantity</Label>
                                                <Input
                                                    name={`lines[${index}][ordered_quantity]`}
                                                    type="number"
                                                    min="0.000001"
                                                    step="0.000001"
                                                    value={line.orderedQuantity}
                                                    onChange={(event) =>
                                                        updateLine(
                                                            index,
                                                            'orderedQuantity',
                                                            event.target.value,
                                                        )
                                                    }
                                                    required
                                                />
                                                <InputError
                                                    message={
                                                        errors[
                                                            `lines.${index}.ordered_quantity`
                                                        ]
                                                    }
                                                />
                                            </div>

                                            <div className="flex items-end">
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    onClick={() =>
                                                        removeLine(index)
                                                    }
                                                    disabled={
                                                        lines.length === 1
                                                    }
                                                >
                                                    Remove
                                                </Button>
                                            </div>
                                        </div>
                                    ))}

                                    <InputError message={errors.lines} />
                                </div>

                                <div className="flex flex-wrap gap-2">
                                    <Button type="submit" disabled={processing}>
                                        {purchaseOrder === null
                                            ? 'Create purchase order'
                                            : 'Save draft'}
                                    </Button>

                                    <Button variant="outline" asChild>
                                        <Link
                                            href={PurchaseOrderController.index()}
                                        >
                                            Back
                                        </Link>
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                ) : (
                    <div className="space-y-5 rounded-xl border border-sidebar-border/70 p-5 dark:border-sidebar-border">
                        <div className="grid gap-4 md:grid-cols-2">
                            <div>
                                <div className="text-sm text-muted-foreground">
                                    Supplier
                                </div>
                                <div className="font-medium">
                                    {purchaseOrder?.supplierName}
                                </div>
                            </div>
                            <div>
                                <div className="text-sm text-muted-foreground">
                                    Location
                                </div>
                                <div className="font-medium">
                                    {purchaseOrder?.locationName}
                                </div>
                            </div>
                        </div>

                        <div className="grid gap-4 rounded-lg bg-muted/40 p-4 sm:grid-cols-2 lg:grid-cols-4">
                            <div>
                                <div className="text-sm text-muted-foreground">
                                    Subtotal
                                </div>
                                <div className="font-medium">
                                    {currency} {purchaseOrder?.subtotal}
                                </div>
                            </div>
                            <div>
                                <div className="text-sm text-muted-foreground">
                                    Tax
                                </div>
                                <div className="font-medium">
                                    {currency} {purchaseOrder?.taxTotal}
                                </div>
                            </div>
                            <div>
                                <div className="text-sm text-muted-foreground">
                                    Discount
                                </div>
                                <div className="font-medium">
                                    {currency} {purchaseOrder?.discountTotal}
                                </div>
                            </div>
                            <div>
                                <div className="text-sm text-muted-foreground">
                                    Final total
                                </div>
                                <div className="font-semibold">
                                    {currency} {purchaseOrder?.total}
                                </div>
                            </div>
                        </div>

                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="border-b text-left">
                                    <tr>
                                        <th className="py-2">Item</th>
                                        <th className="py-2">SKU</th>
                                        <th className="py-2">Ordered</th>
                                        <th className="py-2">Received base</th>
                                        <th className="py-2 text-right">
                                            Price
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {purchaseOrder?.lines.map((line) => (
                                        <tr
                                            key={line.id}
                                            className="border-b last:border-b-0"
                                        >
                                            <td className="py-2">
                                                {line.itemName}
                                            </td>
                                            <td className="py-2">
                                                {line.supplierSku}
                                            </td>
                                            <td className="py-2">
                                                {line.orderedQuantity}{' '}
                                                {line.purchaseUnit.symbol}
                                            </td>
                                            <td className="py-2">
                                                {line.receivedBaseQuantity}
                                            </td>
                                            <td className="py-2 text-right">
                                                {currency} {line.unitPrice}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}

                {purchaseOrder &&
                    canManage &&
                    purchaseOrder.status === 'draft' && (
                        <div className="flex gap-2">
                            <Form
                                {...PurchaseOrderController.approve.form(
                                    purchaseOrder.id,
                                )}
                                options={{ preserveState: 'errors' }}
                            >
                                {({ processing }) => (
                                    <Button type="submit" disabled={processing}>
                                        Approve purchase order
                                    </Button>
                                )}
                            </Form>

                            <Form
                                {...PurchaseOrderController.cancel.form(
                                    purchaseOrder.id,
                                )}
                                options={{ preserveState: 'errors' }}
                            >
                                {({ processing }) => (
                                    <Button
                                        type="submit"
                                        variant="outline"
                                        disabled={processing}
                                    >
                                        Cancel purchase order
                                    </Button>
                                )}
                            </Form>
                        </div>
                    )}

                {purchaseOrder &&
                    canReceive &&
                    ['approved', 'partially_received'].includes(
                        purchaseOrder.status,
                    ) && (
                        <Button asChild className="w-fit">
                            <Link
                                href={GoodsReceiptController.create(
                                    purchaseOrder.id,
                                )}
                            >
                                Receive stock
                            </Link>
                        </Button>
                    )}
            </div>
        </>
    );
}

PurchaseOrderForm.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
        {
            title: 'Purchase orders',
            href: PurchaseOrderController.index(),
        },
    ],
};
