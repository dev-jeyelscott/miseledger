import { Form, Head, Link, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import GoodsReceiptController from '@/actions/App/Http/Controllers/Purchasing/GoodsReceiptController';
import PurchaseOrderController from '@/actions/App/Http/Controllers/Purchasing/PurchaseOrderController';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import type { StatusBadgeProps } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { navigateToPreviousPage } from '@/lib/navigation-history';
import { dashboard } from '@/routes';

type SupplierItemOption = {
    id: number;
    supplierSku: string;
    itemName: string;
    purchaseUnit: string;
    baseQuantity: string;
    currentPrice: string | null;
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
    unitPrice: string | null;
    lineTotal: string | null;
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
    subtotal: string | null;
    taxTotal: string | null;
    discountTotal: string | null;
    total: string | null;
    notes: string | null;
    approvedAt: string | null;
    lines: PurchaseOrderLine[];
};

type LineState = {
    clientId: number;
    supplierItemId: string;
    orderedQuantity: string;
};

type Props = {
    purchaseOrder: PurchaseOrder | null;
    defaultOrderDate?: string;
    supplierOptions: SupplierOption[];
    locationOptions: LocationOption[];
    currency: string;
    canManage: boolean;
    canReceive: boolean;
    canViewCosts: boolean;
};

let nextLineClientId = 0;

function createLine(): LineState {
    nextLineClientId += 1;

    return {
        clientId: nextLineClientId,
        supplierItemId: '',
        orderedQuantity: '1',
    };
}

type DirtyStateTrackerProps = {
    dirty: boolean;
    onChange: (dirty: boolean) => void;
};

/**
 * Format fixed-precision decimal strings for display without JavaScript floats.
 */
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

/**
 * Format money with thousands separators, at least two decimals, and no
 * unnecessary precision beyond meaningful stored digits.
 */
const formatMoney = (value: string): string => {
    const [rawInteger, rawDecimal = ''] = value.trim().split('.');
    const negative = rawInteger.startsWith('-');
    const integerDigits = negative ? rawInteger.slice(1) : rawInteger;
    const groupedInteger = integerDigits.replace(/\B(?=(\d{3})+(?!\d))/g, ',');

    let decimal = rawDecimal;

    while (decimal.length > 2 && decimal.endsWith('0')) {
        decimal = decimal.slice(0, -1);
    }

    decimal = decimal.padEnd(2, '0');

    return `${negative ? '-' : ''}${groupedInteger}.${decimal}`;
};

/** Keep navigation guards synchronized with the Inertia Form dirty state. */
function DirtyStateTracker({ dirty, onChange }: DirtyStateTrackerProps) {
    useEffect(() => {
        onChange(dirty);
    }, [dirty, onChange]);

    return null;
}

/** Return the first server-side lifecycle error for a compact dialog alert. */
function firstActionError(errors: Record<string, string>): string | null {
    return Object.values(errors)[0] ?? null;
}

function statusLabel(status: string): string {
    const labels: Record<string, string> = {
        approved: 'Approved',
        cancelled: 'Cancelled',
        draft: 'Draft',
        partially_received: 'Partially received',
        received: 'Received',
    };

    return labels[status] ?? status;
}

function statusVariant(status: string): StatusBadgeProps['variant'] {
    switch (status) {
        case 'approved':
            return 'info';
        case 'partially_received':
            return 'warning';
        case 'received':
            return 'success';
        case 'cancelled':
            return 'danger';
        case 'draft':
        default:
            return 'neutral';
    }
}

export default function PurchaseOrderForm({
    purchaseOrder,
    defaultOrderDate,
    supplierOptions,
    locationOptions,
    currency,
    canManage,
    canReceive,
    canViewCosts,
}: Props) {
    const editable =
        purchaseOrder === null ||
        (purchaseOrder.status === 'draft' && canManage);

    const [draftDirty, setDraftDirty] = useState(false);
    const [leaveDialogOpen, setLeaveDialogOpen] = useState(false);
    const [approveDialogOpen, setApproveDialogOpen] = useState(false);
    const [cancelDialogOpen, setCancelDialogOpen] = useState(false);
    const [supplierChangeDialogOpen, setSupplierChangeDialogOpen] =
        useState(false);
    const [pendingSupplierId, setPendingSupplierId] = useState<string | null>(
        null,
    );
    const allowNextNavigation = useRef(false);

    useEffect(() => {
        if (!draftDirty) {
            return;
        }

        const removeBeforeListener = router.on('before', (event) => {
            if (event.detail.visit.method !== 'get') {
                return;
            }

            if (allowNextNavigation.current) {
                allowNextNavigation.current = false;

                return;
            }

            return window.confirm(
                'You have unsaved purchase order changes. Leave without saving them?',
            );
        });

        const handleBeforeUnload = (event: BeforeUnloadEvent) => {
            event.preventDefault();
            event.returnValue = '';
        };

        window.addEventListener('beforeunload', handleBeforeUnload);

        return () => {
            removeBeforeListener();
            window.removeEventListener('beforeunload', handleBeforeUnload);
        };
    }, [draftDirty]);

    const requestBackNavigation = () => {
        if (draftDirty) {
            setLeaveDialogOpen(true);

            return;
        }

        navigateToPreviousPage(PurchaseOrderController.index().url);
    };

    const discardChangesAndNavigateBack = () => {
        allowNextNavigation.current = true;
        setDraftDirty(false);
        setLeaveDialogOpen(false);
        navigateToPreviousPage(PurchaseOrderController.index().url);
    };

    const [supplierId, setSupplierId] = useState(
        purchaseOrder?.supplierId.toString() ?? '',
    );

    const [lines, setLines] = useState<LineState[]>(
        purchaseOrder?.lines.map((line) => ({
            clientId: createLine().clientId,
            supplierItemId: line.supplierItemId.toString(),
            orderedQuantity: line.orderedQuantity,
        })) ?? [createLine()],
    );

    const selectedSupplier = supplierOptions.find(
        (supplier) => supplier.id.toString() === supplierId,
    );

    const addLine = () => {
        setLines((current) => [...current, createLine()]);
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

    const receivingAvailable =
        purchaseOrder !== null &&
        canReceive &&
        ['approved', 'partially_received'].includes(purchaseOrder.status);

    const requestSupplierChange = (nextSupplierId: string) => {
        if (nextSupplierId === supplierId) {
            return;
        }

        if (lines.some((line) => line.supplierItemId !== '')) {
            setPendingSupplierId(nextSupplierId);
            setSupplierChangeDialogOpen(true);

            return;
        }

        setSupplierId(nextSupplierId);
        setLines([createLine()]);
    };

    const confirmSupplierChange = () => {
        if (pendingSupplierId === null) {
            return;
        }

        setSupplierId(pendingSupplierId);
        setLines([createLine()]);
        setPendingSupplierId(null);
        setSupplierChangeDialogOpen(false);
    };

    const formAttributes =
        purchaseOrder === null
            ? PurchaseOrderController.store.form()
            : PurchaseOrderController.update.form.put(purchaseOrder.id);

    return (
        <>
            <Head title={title} />

            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <PageHeader
                    title={title}
                    description={
                        purchaseOrder === null
                            ? 'Create a draft order using active supplier items.'
                            : `Review this ${statusLabel(
                                  purchaseOrder.status,
                              )} purchase order and its fulfillment state.`
                    }
                    actions={
                        receivingAvailable ? (
                            <Button asChild>
                                <Link
                                    href={GoodsReceiptController.create(
                                        purchaseOrder.id,
                                    )}
                                >
                                    Receive stock
                                </Link>
                            </Button>
                        ) : undefined
                    }
                />

                {purchaseOrder ? (
                    <StatusBadge
                        label={statusLabel(purchaseOrder.status)}
                        variant={statusVariant(purchaseOrder.status)}
                    />
                ) : null}

                {editable ? (
                    <Form
                        {...formAttributes}
                        setDefaultsOnSuccess
                        options={{
                            preserveState: 'errors',
                            replace: purchaseOrder === null,
                        }}
                        className="space-y-6"
                    >
                        {({ processing, errors, isDirty }) => (
                            <>
                                <DirtyStateTracker
                                    dirty={isDirty}
                                    onChange={setDraftDirty}
                                />

                                <div className="grid gap-5 rounded-xl border border-border bg-card p-5 shadow-sm md:grid-cols-2">
                                    <Field
                                        id="number"
                                        label="PO number"
                                        error={errors.number}
                                    >
                                        <Input
                                            name="number"
                                            defaultValue={
                                                purchaseOrder?.number ?? ''
                                            }
                                            required
                                        />
                                    </Field>

                                    <Field
                                        id="supplier_id"
                                        label="Supplier"
                                        error={errors.supplier_id}
                                    >
                                        <NativeSelect
                                            name="supplier_id"
                                            value={supplierId}
                                            onChange={(event) =>
                                                requestSupplierChange(
                                                    event.target.value,
                                                )
                                            }
                                            required
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
                                        </NativeSelect>
                                    </Field>

                                    <Field
                                        id="location_id"
                                        label="Destination location"
                                        error={errors.location_id}
                                    >
                                        <NativeSelect
                                            name="location_id"
                                            defaultValue={
                                                purchaseOrder?.locationId ?? ''
                                            }
                                            required
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
                                        </NativeSelect>
                                    </Field>

                                    <Field
                                        id="order_date"
                                        label="Order date"
                                        error={errors.order_date}
                                    >
                                        <Input
                                            name="order_date"
                                            type="date"
                                            defaultValue={
                                                purchaseOrder?.orderDate ??
                                                defaultOrderDate
                                            }
                                            required
                                        />
                                    </Field>

                                    <Field
                                        id="expected_delivery_date"
                                        label="Expected delivery"
                                        error={errors.expected_delivery_date}
                                    >
                                        <Input
                                            name="expected_delivery_date"
                                            type="date"
                                            defaultValue={
                                                purchaseOrder?.expectedDeliveryDate ??
                                                ''
                                            }
                                        />
                                    </Field>

                                    {canViewCosts ? (
                                        <>
                                            <Field
                                                id="tax_total"
                                                label={`Tax total (${currency})`}
                                                error={errors.tax_total}
                                            >
                                                <Input
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
                                            </Field>

                                            <Field
                                                id="discount_total"
                                                label={`Discount total (${currency})`}
                                                error={errors.discount_total}
                                            >
                                                <Input
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
                                            </Field>
                                        </>
                                    ) : null}

                                    <Field
                                        id="notes"
                                        label="Notes"
                                        error={errors.notes}
                                        className="md:col-span-2"
                                    >
                                        <textarea
                                            name="notes"
                                            defaultValue={
                                                purchaseOrder?.notes ?? ''
                                            }
                                            rows={3}
                                            className="flex min-h-20 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                        />
                                    </Field>
                                </div>

                                <div className="space-y-4 rounded-xl border border-sidebar-border/70 p-5 dark:border-sidebar-border">
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <h2 className="font-semibold">
                                                Purchase lines
                                            </h2>
                                            <p className="text-sm text-muted-foreground">
                                                Pack conversions are snapshotted
                                                server-side.
                                                {canViewCosts
                                                    ? ` Amounts are in ${currency}.`
                                                    : ''}
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
                                            key={line.clientId}
                                            className="grid gap-4 border-t pt-4 md:grid-cols-[1fr_180px_auto]"
                                        >
                                            <Field
                                                id={`line-${line.clientId}-supplier-item`}
                                                label="Supplier item"
                                                error={
                                                    errors[
                                                        `lines.${index}.supplier_item_id`
                                                    ]
                                                }
                                            >
                                                <NativeSelect
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
                                                                {canViewCosts &&
                                                                item.currentPrice !==
                                                                    null
                                                                    ? `, ${currency} ${formatMoney(item.currentPrice)}`
                                                                    : ''}
                                                                )
                                                            </option>
                                                        ),
                                                    )}
                                                </NativeSelect>
                                            </Field>

                                            <Field
                                                id={`line-${line.clientId}-quantity`}
                                                label="Quantity"
                                                error={
                                                    errors[
                                                        `lines.${index}.ordered_quantity`
                                                    ]
                                                }
                                            >
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
                                            </Field>

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

                                    {errors.lines ? (
                                        <p
                                            role="alert"
                                            className="text-sm text-destructive"
                                        >
                                            {errors.lines}
                                        </p>
                                    ) : null}
                                </div>

                                <div className="flex flex-wrap gap-2">
                                    <Button type="submit" disabled={processing}>
                                        {processing
                                            ? 'Saving…'
                                            : purchaseOrder === null
                                              ? 'Create purchase order'
                                              : 'Save draft'}
                                    </Button>

                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={processing}
                                        onClick={requestBackNavigation}
                                    >
                                        Back
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                ) : (
                    <div className="space-y-5 rounded-xl border border-border bg-card p-5 shadow-sm">
                        <dl className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <div>
                                <dt className="text-xs text-muted-foreground">
                                    Supplier
                                </dt>
                                <dd className="mt-1 font-medium">
                                    {purchaseOrder?.supplierName}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs text-muted-foreground">
                                    Location
                                </dt>
                                <dd className="mt-1 font-medium">
                                    {purchaseOrder?.locationName}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs text-muted-foreground">
                                    Order date
                                </dt>
                                <dd className="mt-1 font-medium tabular-nums">
                                    {purchaseOrder?.orderDate}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs text-muted-foreground">
                                    Expected delivery
                                </dt>
                                <dd className="mt-1 font-medium tabular-nums">
                                    {purchaseOrder?.expectedDeliveryDate ?? '—'}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs text-muted-foreground">
                                    Approved at
                                </dt>
                                <dd className="mt-1 font-medium tabular-nums">
                                    {purchaseOrder?.approvedAt ??
                                        'Not approved'}
                                </dd>
                            </div>
                        </dl>

                        {purchaseOrder?.notes ? (
                            <div>
                                <h2 className="text-sm font-semibold">Notes</h2>
                                <p className="mt-1 text-sm whitespace-pre-wrap text-muted-foreground">
                                    {purchaseOrder.notes}
                                </p>
                            </div>
                        ) : null}

                        {canViewCosts ? (
                            <div className="grid gap-4 rounded-lg bg-muted/40 p-4 sm:grid-cols-2 lg:grid-cols-4">
                                <div>
                                    <div className="text-sm text-muted-foreground">
                                        Subtotal
                                    </div>
                                    <div className="font-medium">
                                        {currency}{' '}
                                        {formatMoney(
                                            purchaseOrder?.subtotal ?? '0.00',
                                        )}
                                    </div>
                                </div>
                                <div>
                                    <div className="text-sm text-muted-foreground">
                                        Tax
                                    </div>
                                    <div className="font-medium">
                                        {currency}{' '}
                                        {formatMoney(
                                            purchaseOrder?.taxTotal ?? '0.00',
                                        )}
                                    </div>
                                </div>
                                <div>
                                    <div className="text-sm text-muted-foreground">
                                        Discount
                                    </div>
                                    <div className="font-medium">
                                        {currency}{' '}
                                        {formatMoney(
                                            purchaseOrder?.discountTotal ??
                                                '0.00',
                                        )}
                                    </div>
                                </div>
                                <div>
                                    <div className="text-sm text-muted-foreground">
                                        Final total
                                    </div>
                                    <div className="font-semibold">
                                        {currency}{' '}
                                        {formatMoney(
                                            purchaseOrder?.total ?? '0.00',
                                        )}
                                    </div>
                                </div>
                            </div>
                        ) : null}

                        <div className="hidden overflow-x-auto md:block">
                            <table className="w-full min-w-180 text-sm">
                                <caption className="sr-only">
                                    Purchase order line details
                                </caption>
                                <thead className="border-b text-left">
                                    <tr>
                                        <th scope="col" className="px-4 py-3">
                                            Item
                                        </th>
                                        <th scope="col" className="px-4 py-3">
                                            SKU
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-4 py-3 text-right"
                                        >
                                            Ordered
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-4 py-3 text-right"
                                        >
                                            Received base
                                        </th>
                                        {canViewCosts ? (
                                            <th
                                                scope="col"
                                                className="px-4 py-3 text-right"
                                            >
                                                Unit price ({currency})
                                            </th>
                                        ) : null}
                                    </tr>
                                </thead>
                                <tbody>
                                    {purchaseOrder?.lines.map((line) => (
                                        <tr
                                            key={line.id}
                                            className="border-b last:border-b-0"
                                        >
                                            <td className="px-4 py-3">
                                                {line.itemName}
                                            </td>
                                            <td className="px-4 py-3 font-mono text-xs">
                                                {line.supplierSku}
                                            </td>
                                            <td className="px-4 py-3 text-right tabular-nums">
                                                {formatDecimal(
                                                    line.orderedQuantity,
                                                )}{' '}
                                                {line.purchaseUnit.symbol}
                                            </td>
                                            <td className="px-4 py-3 text-right tabular-nums">
                                                {formatDecimal(
                                                    line.receivedBaseQuantity,
                                                )}
                                            </td>
                                            {canViewCosts &&
                                            line.unitPrice !== null ? (
                                                <td className="px-4 py-3 text-right tabular-nums">
                                                    {formatMoney(
                                                        line.unitPrice,
                                                    )}
                                                </td>
                                            ) : null}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        <div className="divide-y divide-border md:hidden">
                            {purchaseOrder?.lines.map((line) => (
                                <article
                                    key={line.id}
                                    className="space-y-3 py-4"
                                >
                                    <div>
                                        <h2 className="font-medium">
                                            {line.itemName}
                                        </h2>
                                        <p className="mt-1 font-mono text-xs text-muted-foreground">
                                            {line.supplierSku}
                                        </p>
                                    </div>
                                    <dl className="grid grid-cols-2 gap-3 text-sm">
                                        <div>
                                            <dt className="text-xs text-muted-foreground">
                                                Ordered
                                            </dt>
                                            <dd className="mt-1 tabular-nums">
                                                {formatDecimal(
                                                    line.orderedQuantity,
                                                )}{' '}
                                                {line.purchaseUnit.symbol}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt className="text-xs text-muted-foreground">
                                                Received base
                                            </dt>
                                            <dd className="mt-1 tabular-nums">
                                                {formatDecimal(
                                                    line.receivedBaseQuantity,
                                                )}
                                            </dd>
                                        </div>
                                        {canViewCosts &&
                                        line.unitPrice !== null ? (
                                            <div>
                                                <dt className="text-xs text-muted-foreground">
                                                    Unit price ({currency})
                                                </dt>
                                                <dd className="mt-1 tabular-nums">
                                                    {formatMoney(
                                                        line.unitPrice,
                                                    )}
                                                </dd>
                                            </div>
                                        ) : null}
                                    </dl>
                                </article>
                            ))}
                        </div>
                    </div>
                )}

                {purchaseOrder &&
                    canManage &&
                    purchaseOrder.status === 'draft' && (
                        <div className="space-y-2">
                            <div className="flex flex-wrap gap-2">
                                <Dialog
                                    open={approveDialogOpen}
                                    onOpenChange={setApproveDialogOpen}
                                >
                                    <DialogTrigger asChild>
                                        <Button
                                            type="button"
                                            disabled={draftDirty}
                                        >
                                            Approve purchase order
                                        </Button>
                                    </DialogTrigger>

                                    <DialogContent>
                                        <Form
                                            {...PurchaseOrderController.approve.form(
                                                purchaseOrder.id,
                                            )}
                                            options={{
                                                preserveState: 'errors',
                                            }}
                                            onSuccess={() =>
                                                setApproveDialogOpen(false)
                                            }
                                        >
                                            {({ processing, errors }) => {
                                                const actionError =
                                                    firstActionError(errors);

                                                return (
                                                    <div className="space-y-4">
                                                        <DialogHeader>
                                                            <DialogTitle>
                                                                Approve purchase
                                                                order?
                                                            </DialogTitle>
                                                            <DialogDescription>
                                                                Approval locks
                                                                this draft from
                                                                further editing
                                                                and makes it
                                                                available for
                                                                receiving. It
                                                                does not change
                                                                inventory; stock
                                                                changes only
                                                                when a goods
                                                                receipt is
                                                                finalized.
                                                            </DialogDescription>
                                                        </DialogHeader>

                                                        {actionError !==
                                                            null && (
                                                            <p
                                                                role="alert"
                                                                className="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive"
                                                            >
                                                                {actionError}
                                                            </p>
                                                        )}

                                                        <DialogFooter>
                                                            <DialogClose
                                                                asChild
                                                            >
                                                                <Button
                                                                    type="button"
                                                                    variant="outline"
                                                                    disabled={
                                                                        processing
                                                                    }
                                                                >
                                                                    Keep draft
                                                                </Button>
                                                            </DialogClose>

                                                            <Button
                                                                type="submit"
                                                                disabled={
                                                                    processing
                                                                }
                                                            >
                                                                {processing
                                                                    ? 'Approving…'
                                                                    : 'Approve purchase order'}
                                                            </Button>
                                                        </DialogFooter>
                                                    </div>
                                                );
                                            }}
                                        </Form>
                                    </DialogContent>
                                </Dialog>

                                <Dialog
                                    open={cancelDialogOpen}
                                    onOpenChange={setCancelDialogOpen}
                                >
                                    <DialogTrigger asChild>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            disabled={draftDirty}
                                        >
                                            Cancel purchase order
                                        </Button>
                                    </DialogTrigger>

                                    <DialogContent>
                                        <Form
                                            {...PurchaseOrderController.cancel.form(
                                                purchaseOrder.id,
                                            )}
                                            options={{
                                                preserveState: 'errors',
                                            }}
                                            onSuccess={() =>
                                                setCancelDialogOpen(false)
                                            }
                                        >
                                            {({ processing, errors }) => {
                                                const actionError =
                                                    firstActionError(errors);

                                                return (
                                                    <div className="space-y-4">
                                                        <DialogHeader>
                                                            <DialogTitle>
                                                                Cancel purchase
                                                                order?
                                                            </DialogTitle>
                                                            <DialogDescription>
                                                                Cancelling this
                                                                draft stops it
                                                                from being
                                                                approved or
                                                                received. The
                                                                purchase order
                                                                remains in
                                                                history and no
                                                                inventory is
                                                                changed.
                                                            </DialogDescription>
                                                        </DialogHeader>

                                                        {actionError !==
                                                            null && (
                                                            <p
                                                                role="alert"
                                                                className="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive"
                                                            >
                                                                {actionError}
                                                            </p>
                                                        )}

                                                        <DialogFooter>
                                                            <DialogClose
                                                                asChild
                                                            >
                                                                <Button
                                                                    type="button"
                                                                    variant="outline"
                                                                    disabled={
                                                                        processing
                                                                    }
                                                                >
                                                                    Keep draft
                                                                </Button>
                                                            </DialogClose>

                                                            <Button
                                                                type="submit"
                                                                variant="destructive"
                                                                disabled={
                                                                    processing
                                                                }
                                                            >
                                                                {processing
                                                                    ? 'Cancelling…'
                                                                    : 'Cancel purchase order'}
                                                            </Button>
                                                        </DialogFooter>
                                                    </div>
                                                );
                                            }}
                                        </Form>
                                    </DialogContent>
                                </Dialog>
                            </div>

                            {draftDirty && (
                                <p className="text-sm text-muted-foreground">
                                    Save or discard your draft changes before
                                    approving or cancelling this purchase order.
                                </p>
                            )}
                        </div>
                    )}

                {!editable && (
                    <Button
                        type="button"
                        variant="outline"
                        className="w-fit"
                        onClick={requestBackNavigation}
                    >
                        Back
                    </Button>
                )}
            </div>

            <Dialog open={leaveDialogOpen} onOpenChange={setLeaveDialogOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Discard unsaved changes?</DialogTitle>
                        <DialogDescription>
                            Your unsaved purchase order changes will be lost.
                            This does not undo any purchase order state already
                            saved on the server.
                        </DialogDescription>
                    </DialogHeader>

                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Stay on page
                            </Button>
                        </DialogClose>

                        <Button
                            type="button"
                            variant="destructive"
                            onClick={discardChangesAndNavigateBack}
                        >
                            Discard and leave
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                open={supplierChangeDialogOpen}
                onOpenChange={(open) => {
                    setSupplierChangeDialogOpen(open);

                    if (!open) {
                        setPendingSupplierId(null);
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            Change supplier and discard lines?
                        </DialogTitle>
                        <DialogDescription>
                            Changing the supplier removes the populated purchase
                            lines because supplier items belong to a specific
                            supplier. This only changes your unsaved draft.
                        </DialogDescription>
                    </DialogHeader>

                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Keep current supplier
                            </Button>
                        </DialogClose>
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={confirmSupplierChange}
                        >
                            Change supplier and discard lines
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

PurchaseOrderForm.layout = (page: Props) => ({
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
        {
            title:
                page.purchaseOrder === null
                    ? 'Create purchase order'
                    : page.purchaseOrder.number,
            href:
                page.purchaseOrder === null
                    ? PurchaseOrderController.create()
                    : PurchaseOrderController.edit(page.purchaseOrder.id),
        },
    ],
});
