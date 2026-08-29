import { Form, Head, Link, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import GoodsReceiptController from '@/actions/App/Http/Controllers/Purchasing/GoodsReceiptController';
import PurchaseOrderController from '@/actions/App/Http/Controllers/Purchasing/PurchaseOrderController';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
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
        actorName: string | null;
    } | null;
};

type AuditEntry = {
    id: number;
    action: string;
    actorName: string | null;
    createdAt: string | null;
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
    timezone: string;
    canFinalize: boolean;
    auditTrail: AuditEntry[];
};

type DirtyStateTrackerProps = {
    dirty: boolean;
    onChange: (dirty: boolean) => void;
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
 * Format decimal strings for display without converting through JavaScript floats.
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

/** Format organization-owned timestamps in the active organization's configured timezone. */
const formatOrganizationDate = (
    value: string | null,
    timezone: string,
): string => {
    if (value === null) {
        return 'Not yet';
    }

    return new Intl.DateTimeFormat('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        timeZone: timezone,
        timeZoneName: 'short',
    }).format(new Date(value));
};

/** Map persisted receipt lifecycle states to the shared semantic badge vocabulary. */
function receiptStatusVariant(
    status: string,
): 'neutral' | 'success' | 'warning' | 'info' | 'danger' {
    switch (status) {
        case 'finalized':
            return 'success';
        case 'cancelled':
            return 'danger';
        default:
            return 'neutral';
    }
}

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

/** Render immutable receipt lines as mobile evidence cards. */
function ReceiptLineCards({
    goodsReceipt,
    currency,
    timezone,
}: {
    goodsReceipt: GoodsReceipt;
    currency: string;
    timezone: string;
}) {
    return (
        <div className="grid gap-3 md:hidden">
            {goodsReceipt.lines.map((line) => (
                <article
                    key={line.key}
                    className="rounded-xl border border-border bg-card p-4 shadow-sm"
                >
                    <div className="flex items-start justify-between gap-3">
                        <h3 className="min-w-0 font-medium">{line.itemName}</h3>

                        {line.movement !== null && (
                            <span className="shrink-0 font-mono text-xs text-muted-foreground">
                                #{line.movement.id}
                            </span>
                        )}
                    </div>

                    <p className="mt-1 text-xs text-muted-foreground">
                        {line.storageLocationName ?? 'No storage recorded'}
                    </p>

                    <dl className="mt-4 grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                        <div>
                            <dt className="text-xs text-muted-foreground">
                                Accepted
                            </dt>
                            <dd className="mt-0.5 tabular-nums">
                                {isPositiveQuantity(line.receivedQuantity) &&
                                line.receivedUnitSymbol
                                    ? `${formatDecimal(
                                          line.receivedQuantity,
                                      )} ${line.receivedUnitSymbol}`
                                    : 'None'}
                            </dd>
                        </div>

                        <div>
                            <dt className="text-xs text-muted-foreground">
                                Accepted base
                            </dt>
                            <dd className="mt-0.5 tabular-nums">
                                {isPositiveQuantity(line.receivedQuantity)
                                    ? formatDecimal(line.baseQuantity)
                                    : 'None'}
                            </dd>
                        </div>

                        <div>
                            <dt className="text-xs text-muted-foreground">
                                Rejected
                            </dt>
                            <dd className="mt-0.5 tabular-nums">
                                {isPositiveQuantity(line.rejectedQuantity) &&
                                line.rejectedUnitSymbol
                                    ? `${formatDecimal(
                                          line.rejectedQuantity,
                                      )} ${line.rejectedUnitSymbol}`
                                    : 'None'}
                            </dd>
                        </div>

                        <div>
                            <dt className="text-xs text-muted-foreground">
                                Damaged
                            </dt>
                            <dd className="mt-0.5 tabular-nums">
                                {isPositiveQuantity(line.damagedQuantity) &&
                                line.damagedUnitSymbol
                                    ? `${formatDecimal(
                                          line.damagedQuantity,
                                      )} ${line.damagedUnitSymbol}`
                                    : 'None'}
                            </dd>
                        </div>

                        <div>
                            <dt className="text-xs text-muted-foreground">
                                Unit cost
                            </dt>
                            <dd className="mt-0.5 tabular-nums">
                                {isPositiveQuantity(line.receivedQuantity)
                                    ? `${currency} ${formatDecimal(
                                          line.unitCost,
                                      )}`
                                    : 'Not recorded'}
                            </dd>
                        </div>

                        <div>
                            <dt className="text-xs text-muted-foreground">
                                Movement
                            </dt>
                            <dd className="mt-0.5">
                                {line.movement === null
                                    ? 'Not recorded'
                                    : formatOrganizationDate(
                                          line.movement.occurredAt,
                                          timezone,
                                      )}
                            </dd>
                        </div>
                    </dl>
                </article>
            ))}
        </div>
    );
}

/**
 * Render receipt editing, PO fulfillment, non-stock evidence, and movement traceability.
 */
export default function GoodsReceiptForm({
    goodsReceipt,
    purchaseOrder,
    storageLocationOptions,
    unitOptions,
    currency,
    timezone,
    canFinalize,
    auditTrail,
}: Props) {
    const editable = goodsReceipt === null || goodsReceipt.status === 'draft';

    const [draftDirty, setDraftDirty] = useState(false);
    const [leaveDialogOpen, setLeaveDialogOpen] = useState(false);
    const [finalizeDialogOpen, setFinalizeDialogOpen] = useState(false);
    const [cancelDialogOpen, setCancelDialogOpen] = useState(false);
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
                'You have unsaved goods receipt changes. Leave without saving them?',
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

        navigateToPreviousPage(GoodsReceiptController.index().url);
    };

    const discardChangesAndNavigateBack = () => {
        allowNextNavigation.current = true;
        setDraftDirty(false);
        setLeaveDialogOpen(false);
        navigateToPreviousPage(GoodsReceiptController.index().url);
    };

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

    const acceptedFinalizeLines =
        goodsReceipt?.lines.filter((line) =>
            isPositiveQuantity(line.receivedQuantity),
        ) ?? [];

    return (
        <>
            <Head title={title} />

            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <PageHeader
                    title={title}
                    description={`${purchaseOrder.supplierName} → ${purchaseOrder.locationName}`}
                    actions={
                        goodsReceipt !== null ? (
                            <StatusBadge
                                label={
                                    goodsReceipt.status
                                        .charAt(0)
                                        .toUpperCase() +
                                    goodsReceipt.status.slice(1)
                                }
                                variant={receiptStatusVariant(
                                    goodsReceipt.status,
                                )}
                            />
                        ) : undefined
                    }
                />

                <section
                    className="grid gap-4 rounded-xl border border-border bg-card p-5 shadow-sm"
                    aria-labelledby="po-fulfillment-heading"
                >
                    <div>
                        <h2
                            id="po-fulfillment-heading"
                            className="font-semibold"
                        >
                            PO fulfillment
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Only accepted quantities count toward PO fulfillment
                            and inventory.
                        </p>
                    </div>

                    <div className="grid gap-3 md:hidden">
                        {purchaseOrder.lines.map((line) => (
                            <div
                                key={line.id}
                                className="rounded-lg border border-border bg-background p-3 text-sm"
                            >
                                <div className="font-medium">
                                    {line.itemName}
                                </div>
                                <dl className="mt-2 grid grid-cols-2 gap-x-4 gap-y-2">
                                    <div>
                                        <dt className="text-xs text-muted-foreground">
                                            Ordered base
                                        </dt>
                                        <dd className="tabular-nums">
                                            {formatDecimal(line.baseQuantity)}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-xs text-muted-foreground">
                                            Accepted base
                                        </dt>
                                        <dd className="tabular-nums">
                                            {formatDecimal(
                                                line.receivedBaseQuantity,
                                            )}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-xs text-muted-foreground">
                                            Remaining
                                        </dt>
                                        <dd className="tabular-nums">
                                            {formatDecimal(
                                                line.remainingBaseQuantity,
                                            )}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-xs text-muted-foreground">
                                            Over received
                                        </dt>
                                        <dd className="tabular-nums">
                                            {line.overReceivedBaseQuantity ===
                                            '0.000000'
                                                ? 'None'
                                                : formatDecimal(
                                                      line.overReceivedBaseQuantity,
                                                  )}
                                        </dd>
                                    </div>
                                </dl>
                            </div>
                        ))}
                    </div>

                    <div className="hidden overflow-x-auto md:block">
                        <table className="w-full text-sm">
                            <thead className="border-b border-border text-left">
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
                                        className="border-b border-border last:border-b-0"
                                    >
                                        <td className="py-2">
                                            {line.itemName}
                                        </td>
                                        <td className="py-2 tabular-nums">
                                            {formatDecimal(line.baseQuantity)}
                                        </td>
                                        <td className="py-2 tabular-nums">
                                            {formatDecimal(
                                                line.receivedBaseQuantity,
                                            )}
                                        </td>
                                        <td className="py-2 tabular-nums">
                                            {formatDecimal(
                                                line.remainingBaseQuantity,
                                            )}
                                        </td>
                                        <td className="py-2 tabular-nums">
                                            {line.overReceivedBaseQuantity ===
                                            '0.000000'
                                                ? 'None'
                                                : formatDecimal(
                                                      line.overReceivedBaseQuantity,
                                                  )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>

                {editable ? (
                    <Form
                        {...formAttributes}
                        setDefaultsOnSuccess
                        options={{
                            preserveState: 'errors',
                            replace: goodsReceipt === null,
                        }}
                        className="grid gap-6"
                    >
                        {({ processing, errors, isDirty }) => (
                            <>
                                <DirtyStateTracker
                                    dirty={isDirty}
                                    onChange={setDraftDirty}
                                />

                                <section
                                    className="grid gap-5 rounded-xl border border-border bg-card p-5 shadow-sm md:grid-cols-2"
                                    aria-labelledby="receipt-details-heading"
                                >
                                    <h2
                                        id="receipt-details-heading"
                                        className="sr-only"
                                    >
                                        Receipt details
                                    </h2>

                                    <Field
                                        id="number"
                                        label="Receipt number"
                                        error={errors.number}
                                    >
                                        <Input
                                            name="number"
                                            defaultValue={
                                                goodsReceipt?.number ?? ''
                                            }
                                            required
                                        />
                                    </Field>

                                    <Field
                                        id="supplier_reference"
                                        label="Supplier reference"
                                        error={errors.supplier_reference}
                                    >
                                        <Input
                                            name="supplier_reference"
                                            defaultValue={
                                                goodsReceipt?.supplierReference ??
                                                ''
                                            }
                                        />
                                    </Field>

                                    <div className="md:col-span-2">
                                        <Field
                                            id="notes"
                                            label="Notes"
                                            error={errors.notes}
                                        >
                                            <textarea
                                                name="notes"
                                                defaultValue={
                                                    goodsReceipt?.notes ?? ''
                                                }
                                                rows={3}
                                                className="w-full resize-y rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive aria-invalid:ring-[3px] aria-invalid:ring-destructive/20"
                                            />
                                        </Field>
                                    </div>
                                </section>

                                <section
                                    className="grid gap-4 rounded-xl border border-border bg-card p-5 shadow-sm"
                                    aria-labelledby="receiving-quantities-heading"
                                >
                                    <div className="flex items-center justify-between gap-4">
                                        <div>
                                            <h2
                                                id="receiving-quantities-heading"
                                                className="font-semibold"
                                            >
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
                                            <article
                                                key={index}
                                                className="grid gap-4 rounded-lg border border-border bg-background p-4"
                                            >
                                                <div className="flex items-center justify-between gap-3">
                                                    <span className="text-sm font-semibold">
                                                        Line {index + 1}
                                                    </span>

                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
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

                                                <div className="grid gap-4 lg:grid-cols-2">
                                                    <Field
                                                        id={`line-${index}-po-line`}
                                                        label="PO line"
                                                        error={
                                                            errors[
                                                                `lines.${index}.purchase_order_line_id`
                                                            ]
                                                        }
                                                    >
                                                        <NativeSelect
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
                                                                        {`${poLine.itemName} — remaining ${formatDecimal(
                                                                            poLine.remainingBaseQuantity,
                                                                        )} base${
                                                                            poLine.overReceivedBaseQuantity !==
                                                                            '0.000000'
                                                                                ? ` · over received ${formatDecimal(
                                                                                      poLine.overReceivedBaseQuantity,
                                                                                  )} base`
                                                                                : ''
                                                                        }`}
                                                                    </option>
                                                                ),
                                                            )}
                                                        </NativeSelect>
                                                    </Field>

                                                    <Field
                                                        id={`line-${index}-storage`}
                                                        label="Storage location"
                                                        error={
                                                            errors[
                                                                `lines.${index}.storage_location_id`
                                                            ]
                                                        }
                                                    >
                                                        <NativeSelect
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
                                                        </NativeSelect>
                                                    </Field>
                                                </div>

                                                <div className="grid gap-4 lg:grid-cols-3">
                                                    <div className="grid gap-3 rounded-lg border border-border p-4">
                                                        <div>
                                                            <h3 className="text-sm font-medium">
                                                                Accepted
                                                            </h3>
                                                            <p className="text-xs text-muted-foreground">
                                                                Stock-bearing
                                                            </p>
                                                        </div>

                                                        <Field
                                                            id={`line-${index}-received-qty`}
                                                            label="Quantity"
                                                            error={
                                                                errors[
                                                                    `lines.${index}.received_quantity`
                                                                ]
                                                            }
                                                        >
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
                                                                className="tabular-nums"
                                                                required
                                                            />
                                                        </Field>

                                                        <Field
                                                            id={`line-${index}-received-unit`}
                                                            label="Accepted unit"
                                                            error={
                                                                errors[
                                                                    `lines.${index}.received_unit_of_measure_id`
                                                                ]
                                                            }
                                                        >
                                                            <NativeSelect
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
                                                            </NativeSelect>
                                                        </Field>
                                                    </div>

                                                    <div className="grid gap-3 rounded-lg border border-border p-4">
                                                        <div>
                                                            <h3 className="text-sm font-medium">
                                                                Rejected
                                                            </h3>
                                                            <p className="text-xs text-muted-foreground">
                                                                Does not enter
                                                                inventory
                                                            </p>
                                                        </div>

                                                        <Field
                                                            id={`line-${index}-rejected-qty`}
                                                            label="Quantity"
                                                            error={
                                                                errors[
                                                                    `lines.${index}.rejected_quantity`
                                                                ]
                                                            }
                                                        >
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
                                                                className="tabular-nums"
                                                            />
                                                        </Field>

                                                        <Field
                                                            id={`line-${index}-rejected-unit`}
                                                            label="Rejected unit"
                                                            error={
                                                                errors[
                                                                    `lines.${index}.rejected_unit_of_measure_id`
                                                                ]
                                                            }
                                                        >
                                                            <NativeSelect
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
                                                            </NativeSelect>
                                                        </Field>
                                                    </div>

                                                    <div className="grid gap-3 rounded-lg border border-border p-4">
                                                        <div>
                                                            <h3 className="text-sm font-medium">
                                                                Damaged
                                                            </h3>
                                                            <p className="text-xs text-muted-foreground">
                                                                Does not enter
                                                                inventory
                                                            </p>
                                                        </div>

                                                        <Field
                                                            id={`line-${index}-damaged-qty`}
                                                            label="Quantity"
                                                            error={
                                                                errors[
                                                                    `lines.${index}.damaged_quantity`
                                                                ]
                                                            }
                                                        >
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
                                                                className="tabular-nums"
                                                            />
                                                        </Field>

                                                        <Field
                                                            id={`line-${index}-damaged-unit`}
                                                            label="Damaged unit"
                                                            error={
                                                                errors[
                                                                    `lines.${index}.damaged_unit_of_measure_id`
                                                                ]
                                                            }
                                                        >
                                                            <NativeSelect
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
                                                            </NativeSelect>
                                                        </Field>
                                                    </div>
                                                </div>

                                                <div className="grid gap-4 lg:grid-cols-4">
                                                    <div className="lg:col-span-3">
                                                        <Field
                                                            id={`line-${index}-notes`}
                                                            label="Line notes"
                                                            error={
                                                                errors[
                                                                    `lines.${index}.notes`
                                                                ]
                                                            }
                                                        >
                                                            <Input
                                                                name={`lines[${index}][notes]`}
                                                                value={
                                                                    line.notes
                                                                }
                                                                onChange={(
                                                                    event,
                                                                ) =>
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
                                                        </Field>
                                                    </div>

                                                    <div className="flex items-end text-xs text-muted-foreground">
                                                        {selectedPoLine &&
                                                            `Purchase UOM: ${selectedPoLine.purchaseUnit.symbol}`}
                                                    </div>
                                                </div>
                                            </article>
                                        );
                                    })}

                                    <InputError message={errors.lines} />
                                </section>

                                <div className="flex flex-wrap gap-2">
                                    <Button type="submit" disabled={processing}>
                                        {processing
                                            ? 'Saving…'
                                            : goodsReceipt === null
                                              ? 'Create draft'
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
                    <section
                        className="grid gap-5 rounded-xl border border-border bg-card p-5 shadow-sm"
                        aria-labelledby="receipt-evidence-heading"
                    >
                        <div>
                            <h2
                                id="receipt-evidence-heading"
                                className="font-semibold"
                            >
                                Receipt evidence
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                {goodsReceipt?.status === 'finalized'
                                    ? 'This receipt is finalized. Quantities and stock movements below are immutable.'
                                    : 'This receipt is cancelled and can no longer be edited.'}
                            </p>
                        </div>

                        <div className="grid gap-4 md:grid-cols-3">
                            <div>
                                <div className="text-sm text-muted-foreground">
                                    Status
                                </div>
                                <div className="mt-1">
                                    <StatusBadge
                                        label={
                                            goodsReceipt !== null
                                                ? goodsReceipt.status
                                                      .charAt(0)
                                                      .toUpperCase() +
                                                  goodsReceipt.status.slice(1)
                                                : ''
                                        }
                                        variant={
                                            goodsReceipt !== null
                                                ? receiptStatusVariant(
                                                      goodsReceipt.status,
                                                  )
                                                : 'neutral'
                                        }
                                    />
                                </div>
                            </div>
                            <div>
                                <div className="text-sm text-muted-foreground">
                                    Received by
                                </div>
                                <div className="font-medium">
                                    {goodsReceipt?.receivedBy ?? 'Not recorded'}
                                </div>
                            </div>
                            <div>
                                <div className="text-sm text-muted-foreground">
                                    Received at
                                </div>
                                <div className="font-medium">
                                    {formatOrganizationDate(
                                        goodsReceipt?.receivedAt ?? null,
                                        timezone,
                                    )}
                                </div>
                            </div>
                        </div>

                        {goodsReceipt !== null && (
                            <ReceiptLineCards
                                goodsReceipt={goodsReceipt}
                                currency={currency}
                                timezone={timezone}
                            />
                        )}

                        <div className="hidden overflow-x-auto md:block">
                            <table className="w-full text-sm">
                                <thead className="border-b border-border text-left">
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
                                            className="border-b border-border last:border-b-0"
                                        >
                                            <td className="py-2">
                                                {line.itemName}
                                            </td>
                                            <td className="py-2">
                                                {line.storageLocationName ??
                                                    'Not recorded'}
                                            </td>
                                            <td className="py-2 tabular-nums">
                                                {isPositiveQuantity(
                                                    line.receivedQuantity,
                                                ) && line.receivedUnitSymbol
                                                    ? `${formatDecimal(
                                                          line.receivedQuantity,
                                                      )} ${line.receivedUnitSymbol}`
                                                    : 'None'}
                                            </td>
                                            <td className="py-2 tabular-nums">
                                                {isPositiveQuantity(
                                                    line.rejectedQuantity,
                                                ) && line.rejectedUnitSymbol
                                                    ? `${formatDecimal(
                                                          line.rejectedQuantity,
                                                      )} ${line.rejectedUnitSymbol}`
                                                    : 'None'}
                                            </td>
                                            <td className="py-2 tabular-nums">
                                                {isPositiveQuantity(
                                                    line.damagedQuantity,
                                                ) && line.damagedUnitSymbol
                                                    ? `${formatDecimal(
                                                          line.damagedQuantity,
                                                      )} ${line.damagedUnitSymbol}`
                                                    : 'None'}
                                            </td>
                                            <td className="py-2 tabular-nums">
                                                {isPositiveQuantity(
                                                    line.receivedQuantity,
                                                )
                                                    ? formatDecimal(
                                                          line.baseQuantity,
                                                      )
                                                    : 'None'}
                                            </td>
                                            <td className="py-2">
                                                {line.movement ? (
                                                    <div>
                                                        <div>
                                                            #{line.movement.id}
                                                        </div>
                                                        <div className="text-xs text-muted-foreground">
                                                            {line.movement
                                                                .actorName ??
                                                                'System'}{' '}
                                                            ·{' '}
                                                            {formatOrganizationDate(
                                                                line.movement
                                                                    .occurredAt,
                                                                timezone,
                                                            )}
                                                        </div>
                                                    </div>
                                                ) : (
                                                    'Not recorded'
                                                )}
                                            </td>
                                            <td className="py-2 text-right tabular-nums">
                                                {isPositiveQuantity(
                                                    line.receivedQuantity,
                                                )
                                                    ? `${currency} ${formatDecimal(
                                                          line.unitCost,
                                                      )}`
                                                    : 'Not recorded'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {auditTrail.length > 0 && (
                            <div>
                                <div className="mb-2 text-sm font-medium">
                                    Audit history
                                </div>

                                <div className="overflow-x-auto">
                                    <table className="w-full text-sm">
                                        <thead className="border-b border-border text-left">
                                            <tr>
                                                <th className="py-2">Action</th>
                                                <th className="py-2">Actor</th>
                                                <th className="py-2">
                                                    Timestamp
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {auditTrail.map((entry) => (
                                                <tr
                                                    key={entry.id}
                                                    className="border-b border-border last:border-b-0"
                                                >
                                                    <td className="py-2">
                                                        {entry.action}
                                                    </td>
                                                    <td className="py-2">
                                                        {entry.actorName ??
                                                            'System'}
                                                    </td>
                                                    <td className="py-2">
                                                        {formatOrganizationDate(
                                                            entry.createdAt,
                                                            timezone,
                                                        )}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        )}
                    </section>
                )}

                {goodsReceipt?.status === 'draft' && canFinalize && (
                    <section
                        className="grid gap-2 rounded-xl border border-border bg-card p-5 shadow-sm"
                        aria-labelledby="receipt-actions-heading"
                    >
                        <h2 id="receipt-actions-heading" className="sr-only">
                            Lifecycle actions
                        </h2>

                        <div className="flex flex-wrap gap-2">
                            <Dialog
                                open={finalizeDialogOpen}
                                onOpenChange={setFinalizeDialogOpen}
                            >
                                <DialogTrigger asChild>
                                    <Button type="button" disabled={draftDirty}>
                                        Finalize receipt
                                    </Button>
                                </DialogTrigger>

                                <DialogContent>
                                    <Form
                                        {...GoodsReceiptController.finalize.form(
                                            goodsReceipt.id,
                                        )}
                                        options={{
                                            preserveState: 'errors',
                                        }}
                                        onSuccess={() =>
                                            setFinalizeDialogOpen(false)
                                        }
                                    >
                                        {({ processing, errors }) => {
                                            const actionError =
                                                firstActionError(errors);

                                            return (
                                                <div className="grid gap-4">
                                                    <DialogHeader>
                                                        <DialogTitle>
                                                            Finalize goods
                                                            receipt?
                                                        </DialogTitle>
                                                        <DialogDescription>
                                                            Finalizing posts the
                                                            accepted quantities
                                                            below to inventory,
                                                            updates purchase
                                                            order fulfillment,
                                                            records audit
                                                            history, and makes
                                                            this receipt
                                                            immutable. Rejected
                                                            and damaged
                                                            quantities remain
                                                            stock-neutral.
                                                        </DialogDescription>
                                                    </DialogHeader>

                                                    <div className="rounded-lg border border-border bg-muted/30 p-4 text-sm">
                                                        <div className="font-medium">
                                                            Stock will increase
                                                            at{' '}
                                                            {
                                                                purchaseOrder.locationName
                                                            }
                                                        </div>

                                                        <ul className="mt-3 grid gap-1.5">
                                                            {acceptedFinalizeLines.map(
                                                                (line) => (
                                                                    <li
                                                                        key={
                                                                            line.key
                                                                        }
                                                                        className="flex justify-between gap-4"
                                                                    >
                                                                        <span>
                                                                            {
                                                                                line.itemName
                                                                            }
                                                                        </span>
                                                                        <span className="shrink-0 tabular-nums">
                                                                            {formatDecimal(
                                                                                line.baseQuantity,
                                                                            )}
                                                                        </span>
                                                                    </li>
                                                                ),
                                                            )}

                                                            {acceptedFinalizeLines.length ===
                                                                0 && (
                                                                <li className="text-muted-foreground">
                                                                    No accepted
                                                                    quantities
                                                                    will post to
                                                                    inventory.
                                                                </li>
                                                            )}
                                                        </ul>
                                                    </div>

                                                    {actionError !== null && (
                                                        <p
                                                            role="alert"
                                                            className="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive"
                                                        >
                                                            {actionError}
                                                        </p>
                                                    )}

                                                    <DialogFooter>
                                                        <DialogClose asChild>
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
                                                                ? 'Finalizing…'
                                                                : 'Finalize receipt'}
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
                                        Cancel receipt
                                    </Button>
                                </DialogTrigger>

                                <DialogContent>
                                    <Form
                                        {...GoodsReceiptController.cancel.form(
                                            goodsReceipt.id,
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
                                                <div className="grid gap-4">
                                                    <DialogHeader>
                                                        <DialogTitle>
                                                            Cancel goods
                                                            receipt?
                                                        </DialogTitle>
                                                        <DialogDescription>
                                                            Cancelling this
                                                            draft stops it from
                                                            being edited or
                                                            finalized. The
                                                            receipt remains in
                                                            history and no
                                                            inventory or
                                                            purchase order
                                                            received quantities
                                                            are changed.
                                                        </DialogDescription>
                                                    </DialogHeader>

                                                    {actionError !== null && (
                                                        <p
                                                            role="alert"
                                                            className="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive"
                                                        >
                                                            {actionError}
                                                        </p>
                                                    )}

                                                    <DialogFooter>
                                                        <DialogClose asChild>
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
                                                                : 'Cancel receipt'}
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
                                finalizing or cancelling this receipt.
                            </p>
                        )}
                    </section>
                )}

                <div className="flex flex-wrap gap-2">
                    {!editable && (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={requestBackNavigation}
                        >
                            Back
                        </Button>
                    )}

                    <Button variant="outline" asChild>
                        <Link
                            href={PurchaseOrderController.edit(
                                purchaseOrder.id,
                            )}
                        >
                            View purchase order
                        </Link>
                    </Button>
                </div>
            </div>

            <Dialog open={leaveDialogOpen} onOpenChange={setLeaveDialogOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            Discard unsaved receipt changes?
                        </DialogTitle>
                        <DialogDescription>
                            Your unsaved goods receipt changes will be lost.
                            This does not undo any receipt state or inventory
                            activity already saved on the server.
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
