import { Form, Head, router } from '@inertiajs/react';
import { ArrowDown, ArrowRight, CircleCheck, CircleDashed } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

import StockTransferController from '@/actions/App/Http/Controllers/Inventory/StockTransferController';
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

type LocationOption = {
    id: number;
    name: string;
};

type StorageLocationOption = {
    id: number;
    locationId: number;
    name: string;
};

type InventoryItemOption = {
    id: number;
    name: string;
    sku: string;
    baseUnitId: number;
    baseUnitSymbol: string;
    validUnitIds: number[];
};

type UnitOption = {
    id: number;
    name: string;
    symbol: string;
};

type TransferLine = {
    id: number;
    inventoryItemId: number;
    itemName: string;
    itemSku: string;
    requestedQuantity: string;
    unitId: number;
    unitSymbol: string;
    requestedBaseQuantity: string;
    shippedBaseQuantity: string | null;
    receivedBaseQuantity: string | null;
    unitCost: string | null;
    varianceBaseQuantity: string | null;
    baseUnitSymbol: string;
    outboundMovementId: number | null;
    inboundMovementId: number | null;
};

type StockTransfer = {
    id: number;
    number: string;
    status: string;
    fromLocationId: number;
    fromLocationName: string;
    fromStorageLocationId: number;
    fromStorageLocationName: string;
    toLocationId: number;
    toLocationName: string;
    toStorageLocationId: number;
    toStorageLocationName: string;
    requestedAt: string | null;
    shippedAt: string | null;
    receivedAt: string | null;
    createdBy: string | null;
    shippedBy: string | null;
    receivedBy: string | null;
    notes: string | null;
    lines: TransferLine[];
};

type LineState = {
    inventoryItemId: string;
    requestedQuantity: string;
    unitId: string;
};

type Props = {
    stockTransfer: StockTransfer | null;
    locationOptions: LocationOption[];
    storageLocationOptions: StorageLocationOption[];
    inventoryItemOptions: InventoryItemOption[];
    unitOptions: UnitOption[];
    currency: string;
    timezone: string;
    canCreate: boolean;
    canShip: boolean;
    canReceive: boolean;
    canViewCosts: boolean;
};

type DirtyStateTrackerProps = {
    dirty: boolean;
    onChange: (dirty: boolean) => void;
};

const textareaClassName =
    'border-input bg-background min-h-24 w-full resize-y rounded-md border px-3 py-2 text-sm shadow-xs outline-none transition-[color,box-shadow] placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive aria-invalid:ring-[3px] aria-invalid:ring-destructive/20 disabled:cursor-not-allowed disabled:opacity-50';

/** Create one empty transfer draft line with a safe positive default quantity. */
function emptyLine(): LineState {
    return {
        inventoryItemId: '',
        requestedQuantity: '1',
        unitId: '',
    };
}

/** Format authoritative decimal strings without binary floating-point conversion. */
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

/** Format operational timestamps in the active organization's configured timezone. */
function formatOrganizationDate(
    value: string | null,
    timezone: string,
): string {
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
}

/** Map persisted transfer states to readable sentence-case labels. */
function transferStatusLabel(status: string): string {
    switch (status) {
        case 'draft':
            return 'Draft';
        case 'shipped':
            return 'Shipped';
        case 'received':
            return 'Received';
        case 'cancelled':
            return 'Cancelled';
        default:
            return status;
    }
}

/** Map transfer lifecycle state to the shared semantic badge vocabulary. */
function transferStatusVariant(
    status: string,
): 'neutral' | 'success' | 'warning' | 'info' | 'danger' {
    switch (status) {
        case 'shipped':
            return 'info';
        case 'received':
            return 'success';
        case 'cancelled':
            return 'danger';
        default:
            return 'neutral';
    }
}

/** Keep parent navigation guards synchronized with Inertia Form dirty state. */
function DirtyStateTracker({ dirty, onChange }: DirtyStateTrackerProps) {
    useEffect(() => {
        onChange(dirty);
    }, [dirty, onChange]);

    return null;
}

/** Return the first authoritative action validation error for dialog feedback. */
function firstActionError(errors: Record<string, string>): string | null {
    return Object.values(errors)[0] ?? null;
}

/** Render one lifecycle milestone with actor and organization-local time. */
function LifecycleStep({
    label,
    complete,
    actor,
    occurredAt,
    timezone,
}: {
    label: string;
    complete: boolean;
    actor: string | null;
    occurredAt: string | null;
    timezone: string;
}) {
    const Icon = complete ? CircleCheck : CircleDashed;

    return (
        <div className="flex min-w-0 flex-1 gap-3">
            <Icon
                className={
                    complete
                        ? 'mt-0.5 size-5 shrink-0 text-success-foreground'
                        : 'mt-0.5 size-5 shrink-0 text-muted-foreground'
                }
                aria-hidden="true"
            />

            <div className="min-w-0">
                <div className="text-sm font-semibold">{label}</div>

                {complete ? (
                    <>
                        <div className="mt-1 text-sm text-muted-foreground">
                            {formatOrganizationDate(occurredAt, timezone)}
                        </div>
                        <div className="text-xs text-muted-foreground">
                            {actor ?? 'Actor unavailable'}
                        </div>
                    </>
                ) : (
                    <div className="mt-1 text-sm text-muted-foreground">
                        Pending
                    </div>
                )}
            </div>
        </div>
    );
}

/** Render lifecycle evidence without implying cancelled future stages occurred. */
function TransferLifecycle({
    transfer,
    timezone,
}: {
    transfer: StockTransfer;
    timezone: string;
}) {
    return (
        <section
            className="rounded-xl border border-border bg-card p-5 shadow-sm"
            aria-labelledby="transfer-lifecycle-heading"
        >
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2
                        id="transfer-lifecycle-heading"
                        className="text-base font-semibold"
                    >
                        Transfer lifecycle
                    </h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Requested to Shipped to Received
                    </p>
                </div>

                <StatusBadge
                    label={transferStatusLabel(transfer.status)}
                    variant={transferStatusVariant(transfer.status)}
                />
            </div>

            <div className="mt-5 flex flex-col gap-4 md:flex-row md:items-start">
                <LifecycleStep
                    label="Requested"
                    complete={transfer.requestedAt !== null}
                    actor={transfer.createdBy}
                    occurredAt={transfer.requestedAt}
                    timezone={timezone}
                />

                <ArrowRight
                    className="mt-1 hidden size-4 shrink-0 text-muted-foreground md:block"
                    aria-hidden="true"
                />
                <ArrowDown
                    className="ml-0.5 size-4 shrink-0 text-muted-foreground md:hidden"
                    aria-hidden="true"
                />

                <LifecycleStep
                    label="Shipped"
                    complete={transfer.shippedAt !== null}
                    actor={transfer.shippedBy}
                    occurredAt={transfer.shippedAt}
                    timezone={timezone}
                />

                <ArrowRight
                    className="mt-1 hidden size-4 shrink-0 text-muted-foreground md:block"
                    aria-hidden="true"
                />
                <ArrowDown
                    className="ml-0.5 size-4 shrink-0 text-muted-foreground md:hidden"
                    aria-hidden="true"
                />

                <LifecycleStep
                    label="Received"
                    complete={transfer.receivedAt !== null}
                    actor={transfer.receivedBy}
                    occurredAt={transfer.receivedAt}
                    timezone={timezone}
                />
            </div>

            {transfer.status === 'cancelled' && (
                <div
                    className="mt-5 rounded-lg border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm"
                    role="status"
                >
                    <span className="font-medium text-destructive">
                        Transfer cancelled.
                    </span>{' '}
                    <span className="text-muted-foreground">
                        Future lifecycle stages remain pending because this
                        transfer was closed before completion.
                    </span>
                </div>
            )}
        </section>
    );
}

/** Render immutable transfer lines as mobile evidence cards. */
function TransferLineCards({
    transfer,
    currency,
    canViewCosts,
}: {
    transfer: StockTransfer;
    currency: string;
    canViewCosts: boolean;
}) {
    return (
        <div className="grid gap-3 md:hidden">
            {transfer.lines.map((line) => (
                <article
                    key={line.id}
                    className="rounded-xl border border-border bg-card p-4 shadow-sm"
                >
                    <div className="flex items-start justify-between gap-3">
                        <div className="min-w-0">
                            <h3 className="font-medium">{line.itemName}</h3>
                            <p className="mt-0.5 font-mono text-xs text-muted-foreground">
                                {line.itemSku}
                            </p>
                        </div>
                    </div>

                    <dl className="mt-4 grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                        <div>
                            <dt className="text-xs text-muted-foreground">
                                Requested
                            </dt>
                            <dd className="mt-0.5 tabular-nums">
                                {formatDecimal(line.requestedQuantity)}{' '}
                                {line.unitSymbol}
                            </dd>
                        </div>

                        <div>
                            <dt className="text-xs text-muted-foreground">
                                Requested base
                            </dt>
                            <dd className="mt-0.5 tabular-nums">
                                {formatDecimal(line.requestedBaseQuantity)}{' '}
                                {line.baseUnitSymbol}
                            </dd>
                        </div>

                        <div>
                            <dt className="text-xs text-muted-foreground">
                                Shipped
                            </dt>
                            <dd className="mt-0.5 tabular-nums">
                                {line.shippedBaseQuantity === null
                                    ? 'Not yet'
                                    : `${formatDecimal(
                                          line.shippedBaseQuantity,
                                      )} ${line.baseUnitSymbol}`}
                            </dd>
                        </div>

                        {transfer.status === 'received' && (
                            <div>
                                <dt className="text-xs text-muted-foreground">
                                    Received
                                </dt>
                                <dd className="mt-0.5 tabular-nums">
                                    {line.receivedBaseQuantity === null
                                        ? 'Not recorded'
                                        : `${formatDecimal(
                                              line.receivedBaseQuantity,
                                          )} ${line.baseUnitSymbol}`}
                                </dd>
                            </div>
                        )}

                        {transfer.status === 'received' && (
                            <div>
                                <dt className="text-xs text-muted-foreground">
                                    Variance
                                </dt>
                                <dd className="mt-0.5 tabular-nums">
                                    {line.varianceBaseQuantity === null
                                        ? 'Not recorded'
                                        : `${formatDecimal(
                                              line.varianceBaseQuantity,
                                          )} ${line.baseUnitSymbol}`}
                                </dd>
                            </div>
                        )}

                        {canViewCosts && (
                            <div>
                                <dt className="text-xs text-muted-foreground">
                                    Unit cost
                                </dt>
                                <dd className="mt-0.5 tabular-nums">
                                    {line.unitCost === null
                                        ? 'Not recorded'
                                        : `${currency} ${formatDecimal(
                                              line.unitCost,
                                          )}`}
                                </dd>
                            </div>
                        )}
                    </dl>

                    <div className="mt-4 border-t border-border pt-3 text-xs text-muted-foreground">
                        <div>
                            Out movement:{' '}
                            {line.outboundMovementId === null
                                ? 'Not recorded'
                                : `#${line.outboundMovementId}`}
                        </div>
                        <div className="mt-1">
                            In movement:{' '}
                            {line.inboundMovementId === null
                                ? 'Not recorded'
                                : `#${line.inboundMovementId}`}
                        </div>
                    </div>
                </article>
            ))}
        </div>
    );
}

/** Render immutable transfer-line evidence in a semantic desktop table. */
function TransferLineTable({
    transfer,
    currency,
    canViewCosts,
}: {
    transfer: StockTransfer;
    currency: string;
    canViewCosts: boolean;
}) {
    return (
        <div className="hidden overflow-x-auto rounded-xl border border-border bg-card shadow-sm md:block">
            <table className="w-full text-sm">
                <thead className="border-b border-border bg-muted/40 text-left">
                    <tr>
                        <th scope="col" className="px-4 py-3 font-medium">
                            Item
                        </th>
                        <th scope="col" className="px-4 py-3 font-medium">
                            Requested
                        </th>
                        <th scope="col" className="px-4 py-3 font-medium">
                            Requested base
                        </th>
                        <th scope="col" className="px-4 py-3 font-medium">
                            Shipped
                        </th>

                        {transfer.status === 'received' && (
                            <>
                                <th
                                    scope="col"
                                    className="px-4 py-3 font-medium"
                                >
                                    Received
                                </th>
                                <th
                                    scope="col"
                                    className="px-4 py-3 font-medium"
                                >
                                    Variance
                                </th>
                            </>
                        )}

                        {canViewCosts && (
                            <th
                                scope="col"
                                className="px-4 py-3 text-right font-medium"
                            >
                                Unit cost
                            </th>
                        )}

                        <th scope="col" className="px-4 py-3 font-medium">
                            Movements
                        </th>
                    </tr>
                </thead>

                <tbody>
                    {transfer.lines.map((line) => (
                        <tr
                            key={line.id}
                            className="border-b border-border last:border-b-0"
                        >
                            <td className="px-4 py-3">
                                <div className="font-medium">
                                    {line.itemName}
                                </div>
                                <div className="mt-0.5 font-mono text-xs text-muted-foreground">
                                    {line.itemSku}
                                </div>
                            </td>

                            <td className="px-4 py-3 tabular-nums">
                                {formatDecimal(line.requestedQuantity)}{' '}
                                {line.unitSymbol}
                            </td>

                            <td className="px-4 py-3 tabular-nums">
                                {formatDecimal(line.requestedBaseQuantity)}{' '}
                                {line.baseUnitSymbol}
                            </td>

                            <td className="px-4 py-3 tabular-nums">
                                {line.shippedBaseQuantity === null
                                    ? 'Not yet'
                                    : `${formatDecimal(
                                          line.shippedBaseQuantity,
                                      )} ${line.baseUnitSymbol}`}
                            </td>

                            {transfer.status === 'received' && (
                                <>
                                    <td className="px-4 py-3 tabular-nums">
                                        {line.receivedBaseQuantity === null
                                            ? 'Not recorded'
                                            : `${formatDecimal(
                                                  line.receivedBaseQuantity,
                                              )} ${line.baseUnitSymbol}`}
                                    </td>

                                    <td className="px-4 py-3 tabular-nums">
                                        {line.varianceBaseQuantity === null
                                            ? 'Not recorded'
                                            : `${formatDecimal(
                                                  line.varianceBaseQuantity,
                                              )} ${line.baseUnitSymbol}`}
                                    </td>
                                </>
                            )}

                            {canViewCosts && (
                                <td className="px-4 py-3 text-right tabular-nums">
                                    {line.unitCost === null
                                        ? 'Not recorded'
                                        : `${currency} ${formatDecimal(
                                              line.unitCost,
                                          )}`}
                                </td>
                            )}

                            <td className="px-4 py-3 font-mono text-xs">
                                <div>
                                    OUT:{' '}
                                    {line.outboundMovementId === null
                                        ? 'Pending'
                                        : `#${line.outboundMovementId}`}
                                </div>
                                <div className="mt-1">
                                    IN:{' '}
                                    {line.inboundMovementId === null
                                        ? 'Pending'
                                        : `#${line.inboundMovementId}`}
                                </div>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

/** Render the complete Stock Transfer lifecycle workspace. */
export default function StockTransferForm({
    stockTransfer,
    locationOptions,
    storageLocationOptions,
    inventoryItemOptions,
    unitOptions,
    currency,
    timezone,
    canCreate,
    canShip,
    canReceive,
    canViewCosts,
}: Props) {
    const editable =
        canCreate &&
        (stockTransfer === null || stockTransfer.status === 'draft');

    const [draftDirty, setDraftDirty] = useState(false);
    const [receiptDirty, setReceiptDirty] = useState(false);
    const [leaveDialogOpen, setLeaveDialogOpen] = useState(false);
    const [shipDialogOpen, setShipDialogOpen] = useState(false);
    const [cancelDialogOpen, setCancelDialogOpen] = useState(false);
    const [receiveDialogOpen, setReceiveDialogOpen] = useState(false);
    const allowNextNavigation = useRef(false);
    const hasUnsavedChanges = draftDirty || receiptDirty;

    useEffect(() => {
        if (!hasUnsavedChanges) {
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
                'You have unsaved stock transfer changes. Leave without saving them?',
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
    }, [hasUnsavedChanges]);

    /** Guard Back navigation while either transfer form has unsaved data. */
    function requestBackNavigation(): void {
        if (hasUnsavedChanges) {
            setLeaveDialogOpen(true);

            return;
        }

        navigateToPreviousPage(StockTransferController.index().url);
    }

    /** Explicitly discard unsaved form state before navigating away. */
    function discardChangesAndNavigateBack(): void {
        allowNextNavigation.current = true;
        setDraftDirty(false);
        setReceiptDirty(false);
        setLeaveDialogOpen(false);

        navigateToPreviousPage(StockTransferController.index().url);
    }

    const firstLocationId = locationOptions[0]?.id.toString() ?? '';

    const initialFromLocationId =
        stockTransfer?.fromLocationId.toString() ?? firstLocationId;

    const initialFromStorageLocationId =
        stockTransfer?.fromStorageLocationId.toString() ??
        storageLocationOptions
            .find(
                (storage) =>
                    storage.locationId.toString() === initialFromLocationId,
            )
            ?.id.toString() ??
        '';

    const initialToLocationId =
        stockTransfer?.toLocationId.toString() ??
        locationOptions
            .find(
                (location) => location.id.toString() !== initialFromLocationId,
            )
            ?.id.toString() ??
        initialFromLocationId;

    const initialToStorageLocationId =
        stockTransfer?.toStorageLocationId.toString() ??
        storageLocationOptions
            .find(
                (storage) =>
                    storage.locationId.toString() === initialToLocationId &&
                    storage.id.toString() !== initialFromStorageLocationId,
            )
            ?.id.toString() ??
        '';

    const [fromLocationId, setFromLocationId] = useState(initialFromLocationId);
    const [fromStorageLocationId, setFromStorageLocationId] = useState(
        initialFromStorageLocationId,
    );
    const [toLocationId, setToLocationId] = useState(initialToLocationId);
    const [toStorageLocationId, setToStorageLocationId] = useState(
        initialToStorageLocationId,
    );

    const [lines, setLines] = useState<LineState[]>(
        stockTransfer?.lines.map((line) => ({
            inventoryItemId: line.inventoryItemId.toString(),
            requestedQuantity: line.requestedQuantity,
            unitId: line.unitId.toString(),
        })) ?? [emptyLine()],
    );

    const fromStorageOptions = storageLocationOptions.filter(
        (storage) => storage.locationId.toString() === fromLocationId,
    );

    const toStorageOptions = storageLocationOptions.filter(
        (storage) =>
            storage.locationId.toString() === toLocationId &&
            storage.id.toString() !== fromStorageLocationId,
    );

    /** Update only the targeted draft transfer line. */
    function updateLine(index: number, values: Partial<LineState>): void {
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
    }

    /** Add one empty transfer line without changing existing draft data. */
    function addLine(): void {
        setLines((current) => [...current, emptyLine()]);
    }

    /** Remove one transfer line while preserving the minimum-one-line UX. */
    function removeLine(index: number): void {
        setLines((current) =>
            current.filter((_, currentIndex) => currentIndex !== index),
        );
    }

    /** Detect duplicate item selection without replacing server-authoritative distinct validation. */
    function isItemSelectedElsewhere(
        index: number,
        inventoryItemId: string,
    ): boolean {
        return lines.some(
            (line, currentIndex) =>
                currentIndex !== index &&
                line.inventoryItemId === inventoryItemId,
        );
    }

    /** Change a selected item and choose its base unit or first valid unit. */
    function handleItemChange(index: number, value: string): void {
        const item = inventoryItemOptions.find(
            (option) => option.id.toString() === value,
        );

        updateLine(index, {
            inventoryItemId: value,
            unitId:
                item !== undefined &&
                item.validUnitIds.includes(item.baseUnitId)
                    ? item.baseUnitId.toString()
                    : (item?.validUnitIds[0]?.toString() ?? ''),
        });
    }

    /** Change source location and reconcile dependent source and destination storage selections. */
    function handleFromLocationChange(value: string): void {
        setFromLocationId(value);

        const firstStorage = storageLocationOptions.find(
            (storage) => storage.locationId.toString() === value,
        );

        const nextSource = firstStorage?.id.toString() ?? '';
        setFromStorageLocationId(nextSource);

        if (nextSource === toStorageLocationId) {
            const nextDestination = storageLocationOptions.find(
                (storage) =>
                    storage.locationId.toString() === toLocationId &&
                    storage.id.toString() !== nextSource,
            );

            setToStorageLocationId(nextDestination?.id.toString() ?? '');
        }
    }

    /** Change source storage and clear any destination collision. */
    function handleFromStorageChange(value: string): void {
        setFromStorageLocationId(value);

        if (value === toStorageLocationId) {
            const nextDestination = storageLocationOptions.find(
                (storage) =>
                    storage.locationId.toString() === toLocationId &&
                    storage.id.toString() !== value,
            );

            setToStorageLocationId(nextDestination?.id.toString() ?? '');
        }
    }

    /** Change destination location and select the first non-source storage. */
    function handleToLocationChange(value: string): void {
        setToLocationId(value);

        const firstStorage = storageLocationOptions.find(
            (storage) =>
                storage.locationId.toString() === value &&
                storage.id.toString() !== fromStorageLocationId,
        );

        setToStorageLocationId(firstStorage?.id.toString() ?? '');
    }

    const formAttributes =
        stockTransfer === null
            ? StockTransferController.store.form()
            : StockTransferController.update.form.put(stockTransfer.id);

    const title =
        stockTransfer === null ? 'New stock transfer' : stockTransfer.number;

    const pageDescription =
        stockTransfer === null
            ? 'Create an inventory-neutral transfer draft before committing stock movement.'
            : `${stockTransfer.fromLocationName} / ${stockTransfer.fromStorageLocationName} to ${stockTransfer.toLocationName} / ${stockTransfer.toStorageLocationName}`;

    return (
        <>
            <Head title={title} />

            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <PageHeader
                    title={title}
                    description={pageDescription}
                    actions={
                        <>
                            {stockTransfer !== null && (
                                <StatusBadge
                                    label={transferStatusLabel(
                                        stockTransfer.status,
                                    )}
                                    variant={transferStatusVariant(
                                        stockTransfer.status,
                                    )}
                                />
                            )}

                            <Button
                                type="button"
                                variant="outline"
                                onClick={requestBackNavigation}
                            >
                                Back
                            </Button>
                        </>
                    }
                />

                {stockTransfer !== null && (
                    <TransferLifecycle
                        transfer={stockTransfer}
                        timezone={timezone}
                    />
                )}

                {editable && (
                    <Form
                        id="stock-transfer-draft-form"
                        {...formAttributes}
                        setDefaultsOnSuccess
                        options={{
                            replace: stockTransfer === null,
                        }}
                    >
                        {({ processing, errors, isDirty }) => (
                            <div className="grid gap-6">
                                <DirtyStateTracker
                                    dirty={isDirty}
                                    onChange={setDraftDirty}
                                />

                                <section
                                    className="rounded-xl border border-border bg-card p-5 shadow-sm"
                                    aria-labelledby="transfer-details-heading"
                                >
                                    <div>
                                        <h2
                                            id="transfer-details-heading"
                                            className="text-base font-semibold"
                                        >
                                            Transfer details
                                        </h2>
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            Draft details remain
                                            inventory-neutral until the transfer
                                            is shipped.
                                        </p>
                                    </div>

                                    <div className="mt-5 grid gap-5 md:grid-cols-2">
                                        <Field
                                            id="transfer-number"
                                            label="Transfer number"
                                            error={errors.number}
                                        >
                                            <Input
                                                name="number"
                                                defaultValue={
                                                    stockTransfer?.number ?? ''
                                                }
                                                required
                                            />
                                        </Field>

                                        <Field
                                            id="transfer-notes"
                                            label="Notes"
                                            error={errors.notes}
                                            helper="Optional operational context for this transfer."
                                        >
                                            <textarea
                                                name="notes"
                                                defaultValue={
                                                    stockTransfer?.notes ?? ''
                                                }
                                                rows={4}
                                                className={textareaClassName}
                                            />
                                        </Field>
                                    </div>
                                </section>

                                <section
                                    className="rounded-xl border border-border bg-card p-5 shadow-sm"
                                    aria-labelledby="transfer-direction-heading"
                                >
                                    <div>
                                        <h2
                                            id="transfer-direction-heading"
                                            className="text-base font-semibold"
                                        >
                                            Transfer direction
                                        </h2>
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            Transfer from source to destination.
                                        </p>
                                    </div>

                                    <div className="mt-5 grid gap-4 lg:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] lg:items-center">
                                        <div className="rounded-lg border border-border bg-background p-4">
                                            <div className="mb-4">
                                                <div className="text-sm font-semibold">
                                                    Source
                                                </div>
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    Stock will leave this
                                                    storage when the saved draft
                                                    is shipped.
                                                </p>
                                            </div>

                                            <div className="grid gap-4">
                                                <Field
                                                    id="from-location"
                                                    label="Location"
                                                    error={
                                                        errors.from_location_id
                                                    }
                                                >
                                                    <NativeSelect
                                                        name="from_location_id"
                                                        value={fromLocationId}
                                                        onChange={(event) =>
                                                            handleFromLocationChange(
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                        required
                                                    >
                                                        <option value="">
                                                            Select location
                                                        </option>

                                                        {locationOptions.map(
                                                            (location) => (
                                                                <option
                                                                    key={
                                                                        location.id
                                                                    }
                                                                    value={
                                                                        location.id
                                                                    }
                                                                >
                                                                    {
                                                                        location.name
                                                                    }
                                                                </option>
                                                            ),
                                                        )}
                                                    </NativeSelect>
                                                </Field>

                                                <Field
                                                    id="from-storage-location"
                                                    label="Storage location"
                                                    error={
                                                        errors.from_storage_location_id
                                                    }
                                                    helper="Changing source storage may reset the destination when both would point to the same storage."
                                                >
                                                    <NativeSelect
                                                        name="from_storage_location_id"
                                                        value={
                                                            fromStorageLocationId
                                                        }
                                                        onChange={(event) =>
                                                            handleFromStorageChange(
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                        required
                                                    >
                                                        <option value="">
                                                            Select storage
                                                        </option>

                                                        {fromStorageOptions.map(
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
                                        </div>

                                        <div
                                            className="flex items-center justify-center gap-2 text-sm font-medium text-muted-foreground"
                                            aria-label="Transfer from source to destination"
                                        >
                                            <ArrowDown
                                                className="size-5 lg:hidden"
                                                aria-hidden="true"
                                            />
                                            <ArrowRight
                                                className="hidden size-5 lg:block"
                                                aria-hidden="true"
                                            />
                                            <span className="lg:sr-only">
                                                To destination
                                            </span>
                                        </div>

                                        <div className="rounded-lg border border-border bg-background p-4">
                                            <div className="mb-4">
                                                <div className="text-sm font-semibold">
                                                    Destination
                                                </div>
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    Received stock will enter
                                                    this storage after shipment.
                                                </p>
                                            </div>

                                            <div className="grid gap-4">
                                                <Field
                                                    id="to-location"
                                                    label="Location"
                                                    error={
                                                        errors.to_location_id
                                                    }
                                                >
                                                    <NativeSelect
                                                        name="to_location_id"
                                                        value={toLocationId}
                                                        onChange={(event) =>
                                                            handleToLocationChange(
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                        required
                                                    >
                                                        <option value="">
                                                            Select location
                                                        </option>

                                                        {locationOptions.map(
                                                            (location) => (
                                                                <option
                                                                    key={
                                                                        location.id
                                                                    }
                                                                    value={
                                                                        location.id
                                                                    }
                                                                >
                                                                    {
                                                                        location.name
                                                                    }
                                                                </option>
                                                            ),
                                                        )}
                                                    </NativeSelect>
                                                </Field>

                                                <Field
                                                    id="to-storage-location"
                                                    label="Storage location"
                                                    error={
                                                        errors.to_storage_location_id
                                                    }
                                                    helper="The selected source storage is excluded from valid destinations."
                                                >
                                                    <NativeSelect
                                                        name="to_storage_location_id"
                                                        value={
                                                            toStorageLocationId
                                                        }
                                                        onChange={(event) =>
                                                            setToStorageLocationId(
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                        required
                                                    >
                                                        <option value="">
                                                            Select storage
                                                        </option>

                                                        {toStorageOptions.map(
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
                                        </div>
                                    </div>
                                </section>

                                <section
                                    className="rounded-xl border border-border bg-card shadow-sm"
                                    aria-labelledby="transfer-items-heading"
                                >
                                    <div className="flex flex-wrap items-start justify-between gap-3 border-b border-border p-5">
                                        <div>
                                            <h2
                                                id="transfer-items-heading"
                                                className="text-base font-semibold"
                                            >
                                                Transfer items
                                            </h2>
                                            <p className="mt-1 text-sm text-muted-foreground">
                                                Each inventory item can appear
                                                only once in the transfer.
                                            </p>
                                        </div>

                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={addLine}
                                        >
                                            Add item
                                        </Button>
                                    </div>

                                    <div className="grid gap-4 p-5">
                                        {lines.map((line, index) => {
                                            const selectedItem =
                                                inventoryItemOptions.find(
                                                    (item) =>
                                                        item.id.toString() ===
                                                        line.inventoryItemId,
                                                );

                                            const validUnits =
                                                unitOptions.filter(
                                                    (unit) =>
                                                        selectedItem?.validUnitIds.includes(
                                                            unit.id,
                                                        ) ?? false,
                                                );

                                            const duplicateItem =
                                                line.inventoryItemId !== '' &&
                                                isItemSelectedElsewhere(
                                                    index,
                                                    line.inventoryItemId,
                                                );

                                            const itemError =
                                                errors[
                                                    `lines.${index}.inventory_item_id`
                                                ] ??
                                                (duplicateItem
                                                    ? 'This inventory item is already selected on another transfer line.'
                                                    : undefined);

                                            const itemId = `transfer-line-${index}-item`;
                                            const quantityId = `transfer-line-${index}-quantity`;
                                            const unitId = `transfer-line-${index}-unit`;

                                            return (
                                                <article
                                                    key={index}
                                                    className="rounded-lg border border-border bg-background p-4"
                                                >
                                                    <div className="mb-4 flex items-start justify-between gap-3">
                                                        <div>
                                                            <div className="text-sm font-semibold">
                                                                Line {index + 1}
                                                            </div>
                                                            {selectedItem && (
                                                                <div className="mt-1 font-mono text-xs text-muted-foreground">
                                                                    {
                                                                        selectedItem.sku
                                                                    }
                                                                </div>
                                                            )}
                                                        </div>

                                                        <Button
                                                            type="button"
                                                            variant="outline"
                                                            size="sm"
                                                            disabled={
                                                                lines.length ===
                                                                1
                                                            }
                                                            onClick={() =>
                                                                removeLine(
                                                                    index,
                                                                )
                                                            }
                                                        >
                                                            Remove
                                                        </Button>
                                                    </div>

                                                    <div className="grid gap-4 md:grid-cols-[minmax(0,2fr)_minmax(8rem,1fr)_minmax(10rem,1fr)]">
                                                        <Field
                                                            id={itemId}
                                                            label="Item"
                                                            error={itemError}
                                                            helper={
                                                                selectedItem
                                                                    ? `SKU ${selectedItem.sku}`
                                                                    : 'Already selected items are unavailable in other lines.'
                                                            }
                                                        >
                                                            <NativeSelect
                                                                name={`lines[${index}][inventory_item_id]`}
                                                                value={
                                                                    line.inventoryItemId
                                                                }
                                                                onChange={(
                                                                    event,
                                                                ) =>
                                                                    handleItemChange(
                                                                        index,
                                                                        event
                                                                            .target
                                                                            .value,
                                                                    )
                                                                }
                                                                required
                                                            >
                                                                <option value="">
                                                                    Select item
                                                                </option>

                                                                {inventoryItemOptions.map(
                                                                    (item) => {
                                                                        const itemValue =
                                                                            item.id.toString();
                                                                        const selectedElsewhere =
                                                                            isItemSelectedElsewhere(
                                                                                index,
                                                                                itemValue,
                                                                            );

                                                                        return (
                                                                            <option
                                                                                key={
                                                                                    item.id
                                                                                }
                                                                                value={
                                                                                    item.id
                                                                                }
                                                                                disabled={
                                                                                    selectedElsewhere
                                                                                }
                                                                            >
                                                                                {
                                                                                    item.name
                                                                                }{' '}
                                                                                (
                                                                                {
                                                                                    item.sku
                                                                                }

                                                                                )
                                                                                {selectedElsewhere
                                                                                    ? ' - already selected'
                                                                                    : ''}
                                                                            </option>
                                                                        );
                                                                    },
                                                                )}
                                                            </NativeSelect>
                                                        </Field>

                                                        <Field
                                                            id={quantityId}
                                                            label="Quantity"
                                                            error={
                                                                errors[
                                                                    `lines.${index}.requested_quantity`
                                                                ]
                                                            }
                                                        >
                                                            <Input
                                                                name={`lines[${index}][requested_quantity]`}
                                                                type="number"
                                                                min="0.000001"
                                                                max="999999999.999999"
                                                                step="0.000001"
                                                                value={
                                                                    line.requestedQuantity
                                                                }
                                                                onChange={(
                                                                    event,
                                                                ) =>
                                                                    updateLine(
                                                                        index,
                                                                        {
                                                                            requestedQuantity:
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
                                                            id={unitId}
                                                            label="Unit"
                                                            error={
                                                                errors[
                                                                    `lines.${index}.unit_id`
                                                                ]
                                                            }
                                                            helper={
                                                                selectedItem
                                                                    ? `Base unit: ${selectedItem.baseUnitSymbol}`
                                                                    : undefined
                                                            }
                                                        >
                                                            <NativeSelect
                                                                name={`lines[${index}][unit_id]`}
                                                                value={
                                                                    line.unitId
                                                                }
                                                                onChange={(
                                                                    event,
                                                                ) =>
                                                                    updateLine(
                                                                        index,
                                                                        {
                                                                            unitId: event
                                                                                .target
                                                                                .value,
                                                                        },
                                                                    )
                                                                }
                                                                required
                                                            >
                                                                <option value="">
                                                                    Select unit
                                                                </option>

                                                                {validUnits.map(
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
                                                </article>
                                            );
                                        })}

                                        <InputError
                                            id="transfer-lines-error"
                                            message={errors.lines}
                                        />
                                    </div>
                                </section>

                                <div className="flex flex-wrap gap-2">
                                    <Button type="submit" disabled={processing}>
                                        {processing
                                            ? 'Saving…'
                                            : stockTransfer === null
                                              ? 'Create draft'
                                              : 'Save draft'}
                                    </Button>
                                </div>
                            </div>
                        )}
                    </Form>
                )}

                {stockTransfer !== null && !editable && (
                    <section
                        className="grid gap-4"
                        aria-labelledby="transfer-evidence-heading"
                    >
                        <div>
                            <h2
                                id="transfer-evidence-heading"
                                className="text-base font-semibold"
                            >
                                Transfer evidence
                            </h2>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Saved quantities and stock movement references
                                are immutable lifecycle evidence.
                            </p>
                        </div>

                        <TransferLineCards
                            transfer={stockTransfer}
                            currency={currency}
                            canViewCosts={canViewCosts}
                        />

                        <TransferLineTable
                            transfer={stockTransfer}
                            currency={currency}
                            canViewCosts={canViewCosts}
                        />
                    </section>
                )}

                {stockTransfer?.status === 'draft' && (
                    <section
                        className="rounded-xl border border-border bg-card p-5 shadow-sm"
                        aria-labelledby="transfer-actions-heading"
                    >
                        <div>
                            <h2
                                id="transfer-actions-heading"
                                className="text-base font-semibold"
                            >
                                Lifecycle actions
                            </h2>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Inventory remains unchanged until Ship is
                                confirmed.
                            </p>
                        </div>

                        <div className="mt-4 flex flex-wrap gap-2">
                            {canShip && (
                                <Dialog
                                    open={shipDialogOpen}
                                    onOpenChange={setShipDialogOpen}
                                >
                                    <DialogTrigger asChild>
                                        <Button
                                            type="button"
                                            disabled={draftDirty}
                                        >
                                            Ship transfer
                                        </Button>
                                    </DialogTrigger>

                                    <DialogContent>
                                        <Form
                                            {...StockTransferController.ship.form(
                                                stockTransfer.id,
                                            )}
                                            onSuccess={() =>
                                                setShipDialogOpen(false)
                                            }
                                        >
                                            {({ processing, errors }) => {
                                                const actionError =
                                                    firstActionError(errors);

                                                return (
                                                    <div className="grid gap-4">
                                                        <DialogHeader>
                                                            <DialogTitle>
                                                                Ship stock
                                                                transfer?
                                                            </DialogTitle>
                                                            <DialogDescription>
                                                                Shipping posts
                                                                outbound stock
                                                                movements from
                                                                the source. This
                                                                uses the
                                                                currently saved
                                                                draft.
                                                            </DialogDescription>
                                                        </DialogHeader>

                                                        <div className="rounded-lg border border-border bg-muted/30 p-4 text-sm">
                                                            <div className="font-medium">
                                                                Inventory
                                                                decrease
                                                            </div>
                                                            <div className="mt-1 text-muted-foreground">
                                                                {
                                                                    stockTransfer.fromLocationName
                                                                }{' '}
                                                                /{' '}
                                                                {
                                                                    stockTransfer.fromStorageLocationName
                                                                }
                                                            </div>

                                                            <ul className="mt-3 grid gap-1.5">
                                                                {stockTransfer.lines.map(
                                                                    (line) => (
                                                                        <li
                                                                            key={
                                                                                line.id
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
                                                                                    line.requestedBaseQuantity,
                                                                                )}{' '}
                                                                                {
                                                                                    line.baseUnitSymbol
                                                                                }
                                                                            </span>
                                                                        </li>
                                                                    ),
                                                                )}
                                                            </ul>
                                                        </div>

                                                        {actionError !==
                                                            null && (
                                                            <p
                                                                role="alert"
                                                                className="rounded-lg border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive"
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
                                                                    ? 'Shipping…'
                                                                    : 'Confirm shipment'}
                                                            </Button>
                                                        </DialogFooter>
                                                    </div>
                                                );
                                            }}
                                        </Form>
                                    </DialogContent>
                                </Dialog>
                            )}

                            {canCreate && (
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
                                            Cancel transfer
                                        </Button>
                                    </DialogTrigger>

                                    <DialogContent>
                                        <Form
                                            {...StockTransferController.cancel.form(
                                                stockTransfer.id,
                                            )}
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
                                                                Cancel stock
                                                                transfer?
                                                            </DialogTitle>
                                                            <DialogDescription>
                                                                Cancelling
                                                                closes this
                                                                draft without
                                                                moving
                                                                inventory. It
                                                                remains in audit
                                                                history and can
                                                                no longer be
                                                                edited or
                                                                shipped.
                                                            </DialogDescription>
                                                        </DialogHeader>

                                                        {actionError !==
                                                            null && (
                                                            <p
                                                                role="alert"
                                                                className="rounded-lg border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive"
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
                                                                    : 'Cancel transfer'}
                                                            </Button>
                                                        </DialogFooter>
                                                    </div>
                                                );
                                            }}
                                        </Form>
                                    </DialogContent>
                                </Dialog>
                            )}
                        </div>

                        {draftDirty && (canShip || canCreate) && (
                            <div
                                className="mt-3 rounded-lg border border-border bg-muted/30 px-3 py-2 text-sm text-muted-foreground"
                                role="status"
                            >
                                Save or discard unsaved draft changes before
                                shipping or cancelling this transfer. Lifecycle
                                actions always use the saved server version.
                            </div>
                        )}
                    </section>
                )}

                {stockTransfer?.status === 'shipped' && canReceive && (
                    <Form
                        id="receive-stock-transfer-form"
                        {...StockTransferController.receive.form(
                            stockTransfer.id,
                        )}
                        setDefaultsOnSuccess
                        onSuccess={() => setReceiveDialogOpen(false)}
                    >
                        {({ processing, errors, isDirty }) => (
                            <section
                                className="rounded-xl border border-border bg-card p-5 shadow-sm"
                                aria-labelledby="receive-transfer-heading"
                            >
                                <DirtyStateTracker
                                    dirty={isDirty}
                                    onChange={setReceiptDirty}
                                />

                                <div>
                                    <h2
                                        id="receive-transfer-heading"
                                        className="text-base font-semibold"
                                    >
                                        Receive transfer
                                    </h2>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        Record actual quantities in each item's
                                        base unit before committing destination
                                        stock.
                                    </p>
                                </div>

                                <div className="mt-5 grid gap-4">
                                    {stockTransfer.lines.map((line, index) => {
                                        const inputId = `received-base-quantity-${line.id}`;

                                        return (
                                            <article
                                                key={line.id}
                                                className="grid gap-4 rounded-lg border border-border bg-background p-4 md:grid-cols-[minmax(0,2fr)_minmax(10rem,1fr)] md:items-end"
                                            >
                                                <input
                                                    type="hidden"
                                                    name={`lines[${index}][id]`}
                                                    value={line.id}
                                                />

                                                <div>
                                                    <div className="font-medium">
                                                        {line.itemName}
                                                    </div>
                                                    <div className="mt-1 font-mono text-xs text-muted-foreground">
                                                        {line.itemSku}
                                                    </div>
                                                    <div className="mt-2 text-sm text-muted-foreground">
                                                        Shipped:{' '}
                                                        <span className="tabular-nums">
                                                            {line.shippedBaseQuantity ===
                                                            null
                                                                ? 'Not recorded'
                                                                : formatDecimal(
                                                                      line.shippedBaseQuantity,
                                                                  )}{' '}
                                                            {
                                                                line.baseUnitSymbol
                                                            }
                                                        </span>
                                                    </div>
                                                </div>

                                                <Field
                                                    id={inputId}
                                                    label={`Received (${line.baseUnitSymbol})`}
                                                    error={
                                                        errors[
                                                            `lines.${index}.received_base_quantity`
                                                        ]
                                                    }
                                                >
                                                    <Input
                                                        name={`lines[${index}][received_base_quantity]`}
                                                        type="number"
                                                        min="0"
                                                        max="999999999.999999"
                                                        step="0.000001"
                                                        defaultValue={
                                                            line.shippedBaseQuantity ??
                                                            '0'
                                                        }
                                                        className="tabular-nums"
                                                        required
                                                    />
                                                </Field>
                                            </article>
                                        );
                                    })}

                                    <InputError
                                        id="receive-lines-error"
                                        message={errors.lines}
                                    />
                                </div>

                                <Dialog
                                    open={receiveDialogOpen}
                                    onOpenChange={setReceiveDialogOpen}
                                >
                                    <DialogTrigger asChild>
                                        <Button
                                            type="button"
                                            className="mt-5"
                                            disabled={processing}
                                        >
                                            Review receipt
                                        </Button>
                                    </DialogTrigger>

                                    <DialogContent>
                                        <DialogHeader>
                                            <DialogTitle>
                                                Confirm transfer receipt?
                                            </DialogTitle>
                                            <DialogDescription>
                                                Receipt posts authoritative
                                                inbound stock to{' '}
                                                {stockTransfer.toLocationName} /{' '}
                                                {
                                                    stockTransfer.toStorageLocationName
                                                }
                                                . Received quantities and any
                                                variance become lifecycle
                                                evidence.
                                            </DialogDescription>
                                        </DialogHeader>

                                        {Object.keys(errors).length > 0 && (
                                            <p
                                                role="alert"
                                                className="rounded-lg border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive"
                                            >
                                                Correct the receipt validation
                                                errors before confirming again.
                                            </p>
                                        )}

                                        <DialogFooter>
                                            <DialogClose asChild>
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    disabled={processing}
                                                >
                                                    Review quantities
                                                </Button>
                                            </DialogClose>

                                            <Button
                                                type="submit"
                                                form="receive-stock-transfer-form"
                                                disabled={processing}
                                            >
                                                {processing
                                                    ? 'Recording…'
                                                    : 'Confirm receipt'}
                                            </Button>
                                        </DialogFooter>
                                    </DialogContent>
                                </Dialog>
                            </section>
                        )}
                    </Form>
                )}
            </div>

            <Dialog open={leaveDialogOpen} onOpenChange={setLeaveDialogOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Discard unsaved changes?</DialogTitle>
                        <DialogDescription>
                            Your unsaved stock transfer changes will be lost.
                            This does not undo transfer state already saved on
                            the server.
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

StockTransferForm.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
        {
            title: 'Stock transfers',
            href: StockTransferController.index(),
        },
    ],
};
