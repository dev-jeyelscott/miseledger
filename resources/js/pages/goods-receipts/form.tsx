import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import GoodsReceiptController from '@/actions/App/Http/Controllers/Purchasing/GoodsReceiptController';
import PurchaseOrderController from '@/actions/App/Http/Controllers/Purchasing/PurchaseOrderController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';

type PurchaseOrderLine = {
    id: number;
    itemName: string;
    supplierSku: string;
    orderedQuantity: string;
    baseQuantity: string;
    receivedBaseQuantity: string;
    remainingBaseQuantity: string;
    overReceivedBaseQuantity: string;
    purchaseUnit: {
        id: number;
        symbol: string;
    };
};

type PurchaseOrder = {
    id: number;
    number: string;
    status: string;
    supplierName: string;
    locationName: string;
    lines: PurchaseOrderLine[];
};

type ReceiptLine = {
    key: string;
    id: number | null;
    purchaseOrderLineId: number;
    itemName: string;
    storageLocationId: number | null;
    storageLocationName: string | null;
    receivedQuantity: string;
    receivedUnitId: number | null;
    receivedUnitSymbol: string | null;
    baseQuantity: string;
    unitCost: string;
    totalCost: string;
    rejectedQuantity: string;
    rejectedUnitId: number | null;
    rejectedUnitSymbol: string | null;
    rejectedBaseQuantity: string | null;
    damagedQuantity: string;
    damagedUnitId: number | null;
    damagedUnitSymbol: string | null;
    damagedBaseQuantity: string | null;
    notes: string | null;
    movement: {
        id: number;
        quantity: string;
        unitCost: string | null;
        occurredAt: string;
    } | null;
};

type GoodsReceipt = {
    id: number;
    number: string;
    status: string;
    supplierReference: string | null;
    notes: string | null;
    receivedAt: string | null;
    receivedBy: string | null;
    lines: ReceiptLine[];
};

type StorageLocationOption = {
    id: number;
    name: string;
};

type UnitOption = {
    id: number;
    name: string;
    symbol: string;
};

type LineState = {
    purchaseOrderLineId: string;
    storageLocationId: string;
    receivedQuantity: string;
    receivedUnitId: string;
    rejectedQuantity: string;
    rejectedUnitId: string;
    damagedQuantity: string;
    damagedUnitId: string;
    notes: string;
};

type Props = {
    goodsReceipt: GoodsReceipt | null;
    purchaseOrder: PurchaseOrder;
    storageLocationOptions: StorageLocationOption[];
    unitOptions: UnitOption[];
    currency: string;
    canFinalize: boolean;
};

const emptyLine = (): LineState => ({
    purchaseOrderLineId: '',
    storageLocationId: '',
    receivedQuantity: '1',
    receivedUnitId: '',
    rejectedQuantity: '0',
    rejectedUnitId: '',
    damagedQuantity: '0',
    damagedUnitId: '',
    notes: '',
});

/**
 * Client-only required-field helper. Server-side decimal conversion remains authoritative.
 */
const isPositiveQuantity = (value: string): boolean => {
    const normalized = value.trim();

    return normalized !== '' && !/^0(?:\.0+)?$/.test(normalized);
};

/**
 * Render receipt editing, PO fulfillment, non-stock evidence, and movement traceability.
 */
export default function GoodsReceiptForm({
    goodsReceipt,
    purchaseOrder,
    storageLocationOptions,
    unitOptions,
    currency,
    canFinalize,
}: Props) {
    const editable = goodsReceipt === null || goodsReceipt.status === 'draft';

    const [lines, setLines] = useState<LineState[]>(
        goodsReceipt?.lines.map((line) => ({
            purchaseOrderLineId: line.purchaseOrderLineId.toString(),
            storageLocationId: line.storageLocationId?.toString() ?? '',
            receivedQuantity: line.receivedQuantity,
            receivedUnitId: line.receivedUnitId?.toString() ?? '',
            rejectedQuantity: line.rejectedQuantity,
            rejectedUnitId: line.rejectedUnitId?.toString() ?? '',
            damagedQuantity: line.damagedQuantity,
            damagedUnitId: line.damagedUnitId?.toString() ?? '',
            notes: line.notes ?? '',
        })) ?? [emptyLine()],
    );

    const updateLine = (index: number, values: Partial<LineState>) => {
        setLines((current) =>
            current.map((line, currentIndex) =>
                currentIndex === index
                    ? {
                          ...line,
                          ...values,
                      }
                    : line,
            ),
        );
    };

    const addLine = () => {
        setLines((current) => [...current, emptyLine()]);
    };

    const removeLine = (index: number) => {
        setLines((current) =>
            current.filter((_, currentIndex) => currentIndex !== index),
        );
    };

    const formAttributes =
        goodsReceipt === null
            ? GoodsReceiptController.store.form(purchaseOrder.id)
            : GoodsReceiptController.update.form.put(goodsReceipt.id);

    const title =
        goodsReceipt === null
            ? `Receive ${purchaseOrder.number}`
            : goodsReceipt.number;

    return (
        <>
            <Head title={title} />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div>
                    <h1 className="text-2xl font-semibold">{title}</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {purchaseOrder.supplierName} →{' '}
                        {purchaseOrder.locationName}
                    </p>
                </div>

                <div className="space-y-4 rounded-xl border border-sidebar-border/70 p-5 dark:border-sidebar-border">
                    <div>
                        <h2 className="font-semibold">PO fulfillment</h2>
                        <p className="text-sm text-muted-foreground">
                            Only accepted quantities count toward PO fulfillment
                            and inventory.
                        </p>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="border-b text-left">
                                <tr>
                                    <th className="py-2">Item</th>
                                    <th className="py-2">Ordered base</th>
                                    <th className="py-2">Accepted base</th>
                                    <th className="py-2">Remaining</th>
                                    <th className="py-2">Over received</th>
                                </tr>
                            </thead>
                            <tbody>
                                {purchaseOrder.lines.map((line) => (
                                    <tr
                                        key={line.id}
                                        className="border-b last:border-b-0"
                                    >
                                        <td className="py-2">
                                            {line.itemName}
                                        </td>
                                        <td className="py-2">
                                            {line.baseQuantity}
                                        </td>
                                        <td className="py-2">
                                            {line.receivedBaseQuantity}
                                        </td>
                                        <td className="py-2">
                                            {line.remainingBaseQuantity}
                                        </td>
                                        <td className="py-2">
                                            {line.overReceivedBaseQuantity ===
                                            '0.000000'
                                                ? '—'
                                                : line.overReceivedBaseQuantity}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                {editable ? (
                    <Form {...formAttributes} className="space-y-6">
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-5 rounded-xl border border-sidebar-border/70 p-5 md:grid-cols-2 dark:border-sidebar-border">
                                    <div className="grid gap-2">
                                        <Label htmlFor="number">
                                            Receipt number
                                        </Label>
                                        <Input
                                            id="number"
                                            name="number"
                                            defaultValue={
                                                goodsReceipt?.number ?? ''
                                            }
                                            required
                                        />
                                        <InputError message={errors.number} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="supplier_reference">
                                            Supplier reference
                                        </Label>
                                        <Input
                                            id="supplier_reference"
                                            name="supplier_reference"
                                            defaultValue={
                                                goodsReceipt?.supplierReference ??
                                                ''
                                            }
                                        />
                                        <InputError
                                            message={errors.supplier_reference}
                                        />
                                    </div>

                                    <div className="grid gap-2 md:col-span-2">
                                        <Label htmlFor="notes">Notes</Label>
                                        <textarea
                                            id="notes"
                                            name="notes"
                                            defaultValue={
                                                goodsReceipt?.notes ?? ''
                                            }
                                            rows={3}
                                            className="rounded-md border bg-background px-3 py-2 text-sm"
                                        />
                                        <InputError message={errors.notes} />
                                    </div>
                                </div>

                                <div className="space-y-4 rounded-xl border border-sidebar-border/70 p-5 dark:border-sidebar-border">
                                    <div className="flex items-center justify-between gap-4">
                                        <div>
                                            <h2 className="font-semibold">
                                                Receiving quantities
                                            </h2>
                                            <p className="text-sm text-muted-foreground">
                                                Accepted enters inventory.
                                                Rejected and damaged are
                                                retained as non-stock receiving
                                                evidence.
                                            </p>
                                        </div>

                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={addLine}
                                        >
                                            Add line
                                        </Button>
                                    </div>

                                    {lines.map((line, index) => {
                                        const selectedPoLine =
                                            purchaseOrder.lines.find(
                                                (poLine) =>
                                                    poLine.id.toString() ===
                                                    line.purchaseOrderLineId,
                                            );
                                        const hasAccepted = isPositiveQuantity(
                                            line.receivedQuantity,
                                        );
                                        const hasRejected = isPositiveQuantity(
                                            line.rejectedQuantity,
                                        );
                                        const hasDamaged = isPositiveQuantity(
                                            line.damagedQuantity,
                                        );

                                        return (
                                            <div
                                                key={index}
                                                className="space-y-4 border-t pt-4"
                                            >
                                                <div className="grid gap-4 lg:grid-cols-4">
                                                    <div className="grid gap-2 lg:col-span-2">
                                                        <Label>PO line</Label>
                                                        <select
                                                            name={`lines[${index}][purchase_order_line_id]`}
                                                            value={
                                                                line.purchaseOrderLineId
                                                            }
                                                            onChange={(
                                                                event,
                                                            ) => {
                                                                const poLine =
                                                                    purchaseOrder.lines.find(
                                                                        (
                                                                            candidate,
                                                                        ) =>
                                                                            candidate.id.toString() ===
                                                                            event
                                                                                .target
                                                                                .value,
                                                                    );
                                                                const unitId =
                                                                    poLine?.purchaseUnit.id.toString() ??
                                                                    '';

                                                                updateLine(
                                                                    index,
                                                                    {
                                                                        purchaseOrderLineId:
                                                                            event
                                                                                .target
                                                                                .value,
                                                                        receivedUnitId:
                                                                            unitId,
                                                                        rejectedUnitId:
                                                                            unitId,
                                                                        damagedUnitId:
                                                                            unitId,
                                                                    },
                                                                );
                                                            }}
                                                            required
                                                            className="h-9 rounded-md border bg-background px-3 text-sm"
                                                        >
                                                            <option value="">
                                                                Select PO line
                                                            </option>
                                                            {purchaseOrder.lines.map(
                                                                (poLine) => (
                                                                    <option
                                                                        key={
                                                                            poLine.id
                                                                        }
                                                                        value={
                                                                            poLine.id
                                                                        }
                                                                    >
                                                                        {`${poLine.itemName} — remaining ${poLine.remainingBaseQuantity} base${
                                                                            poLine.overReceivedBaseQuantity !==
                                                                            '0.000000'
                                                                                ? ` · over received ${poLine.overReceivedBaseQuantity} base`
                                                                                : ''
                                                                        }`}
                                                                    </option>
                                                                ),
                                                            )}
                                                        </select>
                                                        <InputError
                                                            message={
                                                                errors[
                                                                    `lines.${index}.purchase_order_line_id`
                                                                ]
                                                            }
                                                        />
                                                    </div>

                                                    <div className="grid gap-2 lg:col-span-2">
                                                        <Label>
                                                            Storage location
                                                        </Label>
                                                        <select
                                                            name={`lines[${index}][storage_location_id]`}
                                                            value={
                                                                line.storageLocationId
                                                            }
                                                            onChange={(event) =>
                                                                updateLine(
                                                                    index,
                                                                    {
                                                                        storageLocationId:
                                                                            event
                                                                                .target
                                                                                .value,
                                                                    },
                                                                )
                                                            }
                                                            required={
                                                                hasAccepted
                                                            }
                                                            className="h-9 rounded-md border bg-background px-3 text-sm"
                                                        >
                                                            <option value="">
                                                                {hasAccepted
                                                                    ? 'Select storage'
                                                                    : 'Not required without accepted stock'}
                                                            </option>
                                                            {storageLocationOptions.map(
                                                                (storage) => (
                                                                    <option
                                                                        key={
                                                                            storage.id
                                                                        }
                                                                        value={
                                                                            storage.id
                                                                        }
                                                                    >
                                                                        {
                                                                            storage.name
                                                                        }
                                                                    </option>
                                                                ),
                                                            )}
                                                        </select>
                                                        <InputError
                                                            message={
                                                                errors[
                                                                    `lines.${index}.storage_location_id`
                                                                ]
                                                            }
                                                        />
                                                    </div>
                                                </div>

                                                <div className="grid gap-4 lg:grid-cols-3">
                                                    <div className="space-y-3 rounded-lg border p-4">
                                                        <div>
                                                            <h3 className="text-sm font-medium">
                                                                Accepted
                                                            </h3>
                                                            <p className="text-xs text-muted-foreground">
                                                                Stock-bearing
                                                            </p>
                                                        </div>
                                                        <div className="grid gap-2">
                                                            <Label>
                                                                Quantity
                                                            </Label>
                                                            <Input
                                                                name={`lines[${index}][received_quantity]`}
                                                                type="number"
                                                                min="0"
                                                                step="0.000001"
                                                                value={
                                                                    line.receivedQuantity
                                                                }
                                                                onChange={(
                                                                    event,
                                                                ) =>
                                                                    updateLine(
                                                                        index,
                                                                        {
                                                                            receivedQuantity:
                                                                                event
                                                                                    .target
                                                                                    .value,
                                                                        },
                                                                    )
                                                                }
                                                                required
                                                            />
                                                            <InputError
                                                                message={
                                                                    errors[
                                                                        `lines.${index}.received_quantity`
                                                                    ]
                                                                }
                                                            />
                                                        </div>
                                                        <div className="grid gap-2">
                                                            <Label>
                                                                Accepted unit
                                                            </Label>
                                                            <select
                                                                name={`lines[${index}][received_unit_of_measure_id]`}
                                                                value={
                                                                    line.receivedUnitId
                                                                }
                                                                onChange={(
                                                                    event,
                                                                ) =>
                                                                    updateLine(
                                                                        index,
                                                                        {
                                                                            receivedUnitId:
                                                                                event
                                                                                    .target
                                                                                    .value,
                                                                        },
                                                                    )
                                                                }
                                                                required={
                                                                    hasAccepted
                                                                }
                                                                className="h-9 rounded-md border bg-background px-3 text-sm"
                                                            >
                                                                <option value="">
                                                                    Select unit
                                                                </option>
                                                                {unitOptions.map(
                                                                    (unit) => (
                                                                        <option
                                                                            key={
                                                                                unit.id
                                                                            }
                                                                            value={
                                                                                unit.id
                                                                            }
                                                                        >
                                                                            {
                                                                                unit.name
                                                                            }{' '}
                                                                            (
                                                                            {
                                                                                unit.symbol
                                                                            }
                                                                            )
                                                                        </option>
                                                                    ),
                                                                )}
                                                            </select>
                                                            <InputError
                                                                message={
                                                                    errors[
                                                                        `lines.${index}.received_unit_of_measure_id`
                                                                    ]
                                                                }
                                                            />
                                                        </div>
                                                    </div>

                                                    <div className="space-y-3 rounded-lg border p-4">
                                                        <div>
                                                            <h3 className="text-sm font-medium">
                                                                Rejected
                                                            </h3>
                                                            <p className="text-xs text-muted-foreground">
                                                                Does not enter
                                                                inventory
                                                            </p>
                                                        </div>
                                                        <div className="grid gap-2">
                                                            <Label>
                                                                Quantity
                                                            </Label>
                                                            <Input
                                                                name={`lines[${index}][rejected_quantity]`}
                                                                type="number"
                                                                min="0"
                                                                step="0.000001"
                                                                value={
                                                                    line.rejectedQuantity
                                                                }
                                                                onChange={(
                                                                    event,
                                                                ) =>
                                                                    updateLine(
                                                                        index,
                                                                        {
                                                                            rejectedQuantity:
                                                                                event
                                                                                    .target
                                                                                    .value,
                                                                        },
                                                                    )
                                                                }
                                                            />
                                                            <InputError
                                                                message={
                                                                    errors[
                                                                        `lines.${index}.rejected_quantity`
                                                                    ]
                                                                }
                                                            />
                                                        </div>
                                                        <div className="grid gap-2">
                                                            <Label>
                                                                Rejected unit
                                                            </Label>
                                                            <select
                                                                name={`lines[${index}][rejected_unit_of_measure_id]`}
                                                                value={
                                                                    line.rejectedUnitId
                                                                }
                                                                onChange={(
                                                                    event,
                                                                ) =>
                                                                    updateLine(
                                                                        index,
                                                                        {
                                                                            rejectedUnitId:
                                                                                event
                                                                                    .target
                                                                                    .value,
                                                                        },
                                                                    )
                                                                }
                                                                required={
                                                                    hasRejected
                                                                }
                                                                className="h-9 rounded-md border bg-background px-3 text-sm"
                                                            >
                                                                <option value="">
                                                                    Select unit
                                                                </option>
                                                                {unitOptions.map(
                                                                    (unit) => (
                                                                        <option
                                                                            key={
                                                                                unit.id
                                                                            }
                                                                            value={
                                                                                unit.id
                                                                            }
                                                                        >
                                                                            {
                                                                                unit.name
                                                                            }{' '}
                                                                            (
                                                                            {
                                                                                unit.symbol
                                                                            }
                                                                            )
                                                                        </option>
                                                                    ),
                                                                )}
                                                            </select>
                                                            <InputError
                                                                message={
                                                                    errors[
                                                                        `lines.${index}.rejected_unit_of_measure_id`
                                                                    ]
                                                                }
                                                            />
                                                        </div>
                                                    </div>

                                                    <div className="space-y-3 rounded-lg border p-4">
                                                        <div>
                                                            <h3 className="text-sm font-medium">
                                                                Damaged
                                                            </h3>
                                                            <p className="text-xs text-muted-foreground">
                                                                Does not enter
                                                                inventory
                                                            </p>
                                                        </div>
                                                        <div className="grid gap-2">
                                                            <Label>
                                                                Quantity
                                                            </Label>
                                                            <Input
                                                                name={`lines[${index}][damaged_quantity]`}
                                                                type="number"
                                                                min="0"
                                                                step="0.000001"
                                                                value={
                                                                    line.damagedQuantity
                                                                }
                                                                onChange={(
                                                                    event,
                                                                ) =>
                                                                    updateLine(
                                                                        index,
                                                                        {
                                                                            damagedQuantity:
                                                                                event
                                                                                    .target
                                                                                    .value,
                                                                        },
                                                                    )
                                                                }
                                                            />
                                                            <InputError
                                                                message={
                                                                    errors[
                                                                        `lines.${index}.damaged_quantity`
                                                                    ]
                                                                }
                                                            />
                                                        </div>
                                                        <div className="grid gap-2">
                                                            <Label>
                                                                Damaged unit
                                                            </Label>
                                                            <select
                                                                name={`lines[${index}][damaged_unit_of_measure_id]`}
                                                                value={
                                                                    line.damagedUnitId
                                                                }
                                                                onChange={(
                                                                    event,
                                                                ) =>
                                                                    updateLine(
                                                                        index,
                                                                        {
                                                                            damagedUnitId:
                                                                                event
                                                                                    .target
                                                                                    .value,
                                                                        },
                                                                    )
                                                                }
                                                                required={
                                                                    hasDamaged
                                                                }
                                                                className="h-9 rounded-md border bg-background px-3 text-sm"
                                                            >
                                                                <option value="">
                                                                    Select unit
                                                                </option>
                                                                {unitOptions.map(
                                                                    (unit) => (
                                                                        <option
                                                                            key={
                                                                                unit.id
                                                                            }
                                                                            value={
                                                                                unit.id
                                                                            }
                                                                        >
                                                                            {
                                                                                unit.name
                                                                            }{' '}
                                                                            (
                                                                            {
                                                                                unit.symbol
                                                                            }
                                                                            )
                                                                        </option>
                                                                    ),
                                                                )}
                                                            </select>
                                                            <InputError
                                                                message={
                                                                    errors[
                                                                        `lines.${index}.damaged_unit_of_measure_id`
                                                                    ]
                                                                }
                                                            />
                                                        </div>
                                                    </div>
                                                </div>

                                                <div className="grid gap-4 lg:grid-cols-4">
                                                    <div className="grid gap-2 lg:col-span-3">
                                                        <Label>
                                                            Line notes
                                                        </Label>
                                                        <Input
                                                            name={`lines[${index}][notes]`}
                                                            value={line.notes}
                                                            onChange={(event) =>
                                                                updateLine(
                                                                    index,
                                                                    {
                                                                        notes: event
                                                                            .target
                                                                            .value,
                                                                    },
                                                                )
                                                            }
                                                        />
                                                        <InputError
                                                            message={
                                                                errors[
                                                                    `lines.${index}.notes`
                                                                ]
                                                            }
                                                        />
                                                    </div>

                                                    <div className="flex items-end justify-between gap-3">
                                                        <div className="text-xs text-muted-foreground">
                                                            {selectedPoLine &&
                                                                `Purchase UOM: ${selectedPoLine.purchaseUnit.symbol}`}
                                                        </div>

                                                        <Button
                                                            type="button"
                                                            variant="outline"
                                                            onClick={() =>
                                                                removeLine(
                                                                    index,
                                                                )
                                                            }
                                                            disabled={
                                                                lines.length ===
                                                                1
                                                            }
                                                        >
                                                            Remove
                                                        </Button>
                                                    </div>
                                                </div>
                                            </div>
                                        );
                                    })}

                                    <InputError message={errors.lines} />
                                </div>

                                <div className="flex gap-2">
                                    <Button type="submit" disabled={processing}>
                                        {goodsReceipt === null
                                            ? 'Create draft'
                                            : 'Save draft'}
                                    </Button>

                                    <Button variant="outline" asChild>
                                        <Link
                                            href={GoodsReceiptController.index()}
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
                        <div className="grid gap-4 md:grid-cols-3">
                            <div>
                                <div className="text-sm text-muted-foreground">
                                    Status
                                </div>
                                <div className="font-medium capitalize">
                                    {goodsReceipt?.status}
                                </div>
                            </div>
                            <div>
                                <div className="text-sm text-muted-foreground">
                                    Received by
                                </div>
                                <div className="font-medium">
                                    {goodsReceipt?.receivedBy ?? '—'}
                                </div>
                            </div>
                            <div>
                                <div className="text-sm text-muted-foreground">
                                    Received at
                                </div>
                                <div className="font-medium">
                                    {goodsReceipt?.receivedAt ?? '—'}
                                </div>
                            </div>
                        </div>

                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="border-b text-left">
                                    <tr>
                                        <th className="py-2">Item</th>
                                        <th className="py-2">Storage</th>
                                        <th className="py-2">Accepted</th>
                                        <th className="py-2">Rejected</th>
                                        <th className="py-2">Damaged</th>
                                        <th className="py-2">Accepted base</th>
                                        <th className="py-2">Movement</th>
                                        <th className="py-2 text-right">
                                            Unit cost
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {goodsReceipt?.lines.map((line) => (
                                        <tr
                                            key={line.key}
                                            className="border-b last:border-b-0"
                                        >
                                            <td className="py-2">
                                                {line.itemName}
                                            </td>
                                            <td className="py-2">
                                                {line.storageLocationName ??
                                                    '—'}
                                            </td>
                                            <td className="py-2">
                                                {isPositiveQuantity(
                                                    line.receivedQuantity,
                                                ) && line.receivedUnitSymbol
                                                    ? `${line.receivedQuantity} ${line.receivedUnitSymbol}`
                                                    : '—'}
                                            </td>
                                            <td className="py-2">
                                                {isPositiveQuantity(
                                                    line.rejectedQuantity,
                                                ) && line.rejectedUnitSymbol
                                                    ? `${line.rejectedQuantity} ${line.rejectedUnitSymbol}`
                                                    : '—'}
                                            </td>
                                            <td className="py-2">
                                                {isPositiveQuantity(
                                                    line.damagedQuantity,
                                                ) && line.damagedUnitSymbol
                                                    ? `${line.damagedQuantity} ${line.damagedUnitSymbol}`
                                                    : '—'}
                                            </td>
                                            <td className="py-2">
                                                {isPositiveQuantity(
                                                    line.receivedQuantity,
                                                )
                                                    ? line.baseQuantity
                                                    : '—'}
                                            </td>
                                            <td className="py-2">
                                                {line.movement
                                                    ? `#${line.movement.id}`
                                                    : '—'}
                                            </td>
                                            <td className="py-2 text-right">
                                                {isPositiveQuantity(
                                                    line.receivedQuantity,
                                                )
                                                    ? `${currency} ${line.unitCost}`
                                                    : '—'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}

                {goodsReceipt?.status === 'draft' && canFinalize && (
                    <div className="flex gap-2">
                        <Form
                            {...GoodsReceiptController.finalize.form(
                                goodsReceipt.id,
                            )}
                        >
                            {({ processing }) => (
                                <Button type="submit" disabled={processing}>
                                    Finalize receipt
                                </Button>
                            )}
                        </Form>

                        <Form
                            {...GoodsReceiptController.cancel.form(
                                goodsReceipt.id,
                            )}
                        >
                            {({ processing }) => (
                                <Button
                                    type="submit"
                                    variant="outline"
                                    disabled={processing}
                                >
                                    Cancel receipt
                                </Button>
                            )}
                        </Form>
                    </div>
                )}

                <Button variant="outline" asChild className="w-fit">
                    <Link href={PurchaseOrderController.edit(purchaseOrder.id)}>
                        View purchase order
                    </Link>
                </Button>
            </div>
        </>
    );
}

GoodsReceiptForm.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
        {
            title: 'Receiving',
            href: GoodsReceiptController.index(),
        },
    ],
};
