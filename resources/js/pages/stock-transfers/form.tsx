import { Form, Head, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import StockTransferController from '@/actions/App/Http/Controllers/Inventory/StockTransferController';
import InputError from '@/components/input-error';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
    canCreate: boolean;
    canShip: boolean;
    canReceive: boolean;
    canViewCosts: boolean;
};

type DirtyStateTrackerProps = {
    dirty: boolean;
    onChange: (dirty: boolean) => void;
};

const emptyLine = (): LineState => ({
    inventoryItemId: '',
    requestedQuantity: '1',
    unitId: '',
});

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

const formatDate = (value: string | null): string =>
    value === null ? '—' : new Date(value).toLocaleString();

/** Keep parent navigation guards synchronized with Inertia Form dirty state. */
function DirtyStateTracker({ dirty, onChange }: DirtyStateTrackerProps) {
    useEffect(() => {
        onChange(dirty);
    }, [dirty, onChange]);

    return null;
}

/** Return the first server-side action error for a compact dialog summary. */
function firstActionError(errors: Record<string, string>): string | null {
    return Object.values(errors)[0] ?? null;
}

export default function StockTransferForm({
    stockTransfer,
    locationOptions,
    storageLocationOptions,
    inventoryItemOptions,
    unitOptions,
    currency,
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

    const requestBackNavigation = () => {
        if (hasUnsavedChanges) {
            setLeaveDialogOpen(true);

            return;
        }

        navigateToPreviousPage(StockTransferController.index().url);
    };

    const discardChangesAndNavigateBack = () => {
        allowNextNavigation.current = true;
        setDraftDirty(false);
        setReceiptDirty(false);
        setLeaveDialogOpen(false);
        navigateToPreviousPage(StockTransferController.index().url);
    };

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

    const handleItemChange = (index: number, value: string) => {
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
    };

    const handleFromLocationChange = (value: string) => {
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
    };

    const handleFromStorageChange = (value: string) => {
        setFromStorageLocationId(value);

        if (value === toStorageLocationId) {
            const nextDestination = storageLocationOptions.find(
                (storage) =>
                    storage.locationId.toString() === toLocationId &&
                    storage.id.toString() !== value,
            );

            setToStorageLocationId(nextDestination?.id.toString() ?? '');
        }
    };

    const handleToLocationChange = (value: string) => {
        setToLocationId(value);

        const firstStorage = storageLocationOptions.find(
            (storage) =>
                storage.locationId.toString() === value &&
                storage.id.toString() !== fromStorageLocationId,
        );

        setToStorageLocationId(firstStorage?.id.toString() ?? '');
    };

    const formAttributes =
        stockTransfer === null
            ? StockTransferController.store.form()
            : StockTransferController.update.form.put(stockTransfer.id);

    const title =
        stockTransfer === null ? 'New stock transfer' : stockTransfer.number;

    return (
        <>
            <Head title={title} />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div>
                    <h1 className="text-2xl font-semibold">{title}</h1>

                    {stockTransfer !== null && (
                        <p className="mt-1 text-sm text-muted-foreground">
                            {stockTransfer.fromLocationName} /{' '}
                            {stockTransfer.fromStorageLocationName} →{' '}
                            {stockTransfer.toLocationName} /{' '}
                            {stockTransfer.toStorageLocationName} ·{' '}
                            <span className="capitalize">
                                {stockTransfer.status}
                            </span>
                        </p>
                    )}
                </div>

                {editable ? (
                    <Form
                        {...formAttributes}
                        setDefaultsOnSuccess
                        options={{ replace: stockTransfer === null }}
                    >
                        {({ processing, errors, isDirty }) => (
                            <div className="space-y-6">
                                <DirtyStateTracker
                                    dirty={isDirty}
                                    onChange={setDraftDirty}
                                />

                                <div className="grid gap-4 rounded-xl border border-sidebar-border/70 p-5 md:grid-cols-2 dark:border-sidebar-border">
                                    <div className="grid gap-2">
                                        <Label>Transfer number</Label>
                                        <Input
                                            name="number"
                                            defaultValue={
                                                stockTransfer?.number ?? ''
                                            }
                                            required
                                        />
                                        <InputError message={errors.number} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label>Notes</Label>
                                        <textarea
                                            name="notes"
                                            defaultValue={
                                                stockTransfer?.notes ?? ''
                                            }
                                            rows={3}
                                            className="rounded-md border bg-background px-3 py-2 text-sm"
                                        />
                                        <InputError message={errors.notes} />
                                    </div>
                                </div>

                                <div className="grid gap-4 rounded-xl border border-sidebar-border/70 p-5 md:grid-cols-2 dark:border-sidebar-border">
                                    <div className="space-y-4">
                                        <h2 className="font-medium">Source</h2>

                                        <div className="grid gap-2">
                                            <Label>Location</Label>
                                            <select
                                                name="from_location_id"
                                                value={fromLocationId}
                                                onChange={(event) =>
                                                    handleFromLocationChange(
                                                        event.target.value,
                                                    )
                                                }
                                                required
                                                className="h-9 rounded-md border bg-background px-3 text-sm"
                                            >
                                                <option value="">
                                                    Select location
                                                </option>

                                                {locationOptions.map(
                                                    (location) => (
                                                        <option
                                                            key={location.id}
                                                            value={location.id}
                                                        >
                                                            {location.name}
                                                        </option>
                                                    ),
                                                )}
                                            </select>
                                            <InputError
                                                message={
                                                    errors.from_location_id
                                                }
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label>Storage location</Label>
                                            <select
                                                name="from_storage_location_id"
                                                value={fromStorageLocationId}
                                                onChange={(event) =>
                                                    handleFromStorageChange(
                                                        event.target.value,
                                                    )
                                                }
                                                required
                                                className="h-9 rounded-md border bg-background px-3 text-sm"
                                            >
                                                <option value="">
                                                    Select storage
                                                </option>

                                                {fromStorageOptions.map(
                                                    (storage) => (
                                                        <option
                                                            key={storage.id}
                                                            value={storage.id}
                                                        >
                                                            {storage.name}
                                                        </option>
                                                    ),
                                                )}
                                            </select>
                                            <InputError
                                                message={
                                                    errors.from_storage_location_id
                                                }
                                            />
                                        </div>
                                    </div>

                                    <div className="space-y-4">
                                        <h2 className="font-medium">
                                            Destination
                                        </h2>

                                        <div className="grid gap-2">
                                            <Label>Location</Label>
                                            <select
                                                name="to_location_id"
                                                value={toLocationId}
                                                onChange={(event) =>
                                                    handleToLocationChange(
                                                        event.target.value,
                                                    )
                                                }
                                                required
                                                className="h-9 rounded-md border bg-background px-3 text-sm"
                                            >
                                                <option value="">
                                                    Select location
                                                </option>

                                                {locationOptions.map(
                                                    (location) => (
                                                        <option
                                                            key={location.id}
                                                            value={location.id}
                                                        >
                                                            {location.name}
                                                        </option>
                                                    ),
                                                )}
                                            </select>
                                            <InputError
                                                message={errors.to_location_id}
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label>Storage location</Label>
                                            <select
                                                name="to_storage_location_id"
                                                value={toStorageLocationId}
                                                onChange={(event) =>
                                                    setToStorageLocationId(
                                                        event.target.value,
                                                    )
                                                }
                                                required
                                                className="h-9 rounded-md border bg-background px-3 text-sm"
                                            >
                                                <option value="">
                                                    Select storage
                                                </option>

                                                {toStorageOptions.map(
                                                    (storage) => (
                                                        <option
                                                            key={storage.id}
                                                            value={storage.id}
                                                        >
                                                            {storage.name}
                                                        </option>
                                                    ),
                                                )}
                                            </select>
                                            <InputError
                                                message={
                                                    errors.to_storage_location_id
                                                }
                                            />
                                        </div>
                                    </div>
                                </div>

                                <div className="space-y-4">
                                    <div className="flex items-center justify-between">
                                        <h2 className="text-lg font-medium">
                                            Items
                                        </h2>

                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={addLine}
                                        >
                                            Add item
                                        </Button>
                                    </div>

                                    {lines.map((line, index) => {
                                        const selectedItem =
                                            inventoryItemOptions.find(
                                                (item) =>
                                                    item.id.toString() ===
                                                    line.inventoryItemId,
                                            );

                                        const validUnits = unitOptions.filter(
                                            (unit) =>
                                                selectedItem?.validUnitIds.includes(
                                                    unit.id,
                                                ) ?? false,
                                        );

                                        return (
                                            <div
                                                key={index}
                                                className="grid gap-4 rounded-xl border border-sidebar-border/70 p-5 md:grid-cols-[2fr_1fr_1fr_auto] dark:border-sidebar-border"
                                            >
                                                <div className="grid gap-2">
                                                    <Label>Item</Label>
                                                    <select
                                                        name={`lines[${index}][inventory_item_id]`}
                                                        value={
                                                            line.inventoryItemId
                                                        }
                                                        onChange={(event) =>
                                                            handleItemChange(
                                                                index,
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                        required
                                                        className="h-9 rounded-md border bg-background px-3 text-sm"
                                                    >
                                                        <option value="">
                                                            Select item
                                                        </option>

                                                        {inventoryItemOptions.map(
                                                            (item) => (
                                                                <option
                                                                    key={
                                                                        item.id
                                                                    }
                                                                    value={
                                                                        item.id
                                                                    }
                                                                >
                                                                    {item.name}{' '}
                                                                    ({item.sku})
                                                                </option>
                                                            ),
                                                        )}
                                                    </select>
                                                    <InputError
                                                        message={
                                                            errors[
                                                                `lines.${index}.inventory_item_id`
                                                            ]
                                                        }
                                                    />
                                                </div>

                                                <div className="grid gap-2">
                                                    <Label>Quantity</Label>
                                                    <Input
                                                        name={`lines[${index}][requested_quantity]`}
                                                        type="number"
                                                        min="0.000001"
                                                        max="999999999.999999"
                                                        step="0.000001"
                                                        value={
                                                            line.requestedQuantity
                                                        }
                                                        onChange={(event) =>
                                                            updateLine(index, {
                                                                requestedQuantity:
                                                                    event.target
                                                                        .value,
                                                            })
                                                        }
                                                        required
                                                    />
                                                    <InputError
                                                        message={
                                                            errors[
                                                                `lines.${index}.requested_quantity`
                                                            ]
                                                        }
                                                    />
                                                </div>

                                                <div className="grid gap-2">
                                                    <Label>Unit</Label>
                                                    <select
                                                        name={`lines[${index}][unit_id]`}
                                                        value={line.unitId}
                                                        onChange={(event) =>
                                                            updateLine(index, {
                                                                unitId: event
                                                                    .target
                                                                    .value,
                                                            })
                                                        }
                                                        required
                                                        className="h-9 rounded-md border bg-background px-3 text-sm"
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
                                                                    {unit.name}{' '}
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
                                                                `lines.${index}.unit_id`
                                                            ]
                                                        }
                                                    />

                                                    {selectedItem && (
                                                        <p className="text-xs text-muted-foreground">
                                                            Base unit:{' '}
                                                            {
                                                                selectedItem.baseUnitSymbol
                                                            }
                                                        </p>
                                                    )}
                                                </div>

                                                <div className="flex items-end">
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        disabled={
                                                            lines.length === 1
                                                        }
                                                        onClick={() =>
                                                            removeLine(index)
                                                        }
                                                    >
                                                        Remove
                                                    </Button>
                                                </div>
                                            </div>
                                        );
                                    })}

                                    <InputError message={errors.lines} />
                                </div>

                                <div className="flex gap-2">
                                    <Button type="submit" disabled={processing}>
                                        {processing
                                            ? 'Saving…'
                                            : stockTransfer === null
                                              ? 'Create draft'
                                              : 'Save draft'}
                                    </Button>

                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={requestBackNavigation}
                                    >
                                        Back
                                    </Button>
                                </div>
                            </div>
                        )}
                    </Form>
                ) : (
                    stockTransfer && (
                        <div className="space-y-5">
                            <div className="grid gap-4 rounded-xl border border-sidebar-border/70 p-5 md:grid-cols-4 dark:border-sidebar-border">
                                <div>
                                    <div className="text-sm text-muted-foreground">
                                        Requested
                                    </div>
                                    <div className="font-medium">
                                        {formatDate(stockTransfer.requestedAt)}
                                    </div>
                                </div>

                                <div>
                                    <div className="text-sm text-muted-foreground">
                                        Shipped
                                    </div>
                                    <div className="font-medium">
                                        {formatDate(stockTransfer.shippedAt)}
                                    </div>
                                </div>

                                <div>
                                    <div className="text-sm text-muted-foreground">
                                        Received
                                    </div>
                                    <div className="font-medium">
                                        {formatDate(stockTransfer.receivedAt)}
                                    </div>
                                </div>

                                <div>
                                    <div className="text-sm text-muted-foreground">
                                        Status
                                    </div>
                                    <div className="font-medium capitalize">
                                        {stockTransfer.status}
                                    </div>
                                </div>
                            </div>

                            <div className="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                                <table className="w-full text-sm">
                                    <thead className="border-b text-left">
                                        <tr>
                                            <th className="px-4 py-3">Item</th>
                                            <th className="px-4 py-3">
                                                Requested
                                            </th>
                                            <th className="px-4 py-3">
                                                Requested base
                                            </th>
                                            <th className="px-4 py-3">
                                                Shipped
                                            </th>

                                            {stockTransfer.status ===
                                                'received' && (
                                                <>
                                                    <th className="px-4 py-3">
                                                        Received
                                                    </th>
                                                    <th className="px-4 py-3">
                                                        Variance
                                                    </th>
                                                </>
                                            )}

                                            {canViewCosts && (
                                                <th className="px-4 py-3 text-right">
                                                    Unit cost
                                                </th>
                                            )}

                                            <th className="px-4 py-3">
                                                Movements
                                            </th>
                                        </tr>
                                    </thead>

                                    <tbody>
                                        {stockTransfer.lines.map((line) => (
                                            <tr
                                                key={line.id}
                                                className="border-b last:border-b-0"
                                            >
                                                <td className="px-4 py-3">
                                                    <div className="font-medium">
                                                        {line.itemName}
                                                    </div>
                                                    <div className="text-xs text-muted-foreground">
                                                        {line.itemSku}
                                                    </div>
                                                </td>

                                                <td className="px-4 py-3">
                                                    {formatDecimal(
                                                        line.requestedQuantity,
                                                    )}{' '}
                                                    {line.unitSymbol}
                                                </td>

                                                <td className="px-4 py-3">
                                                    {formatDecimal(
                                                        line.requestedBaseQuantity,
                                                    )}{' '}
                                                    {line.baseUnitSymbol}
                                                </td>

                                                <td className="px-4 py-3">
                                                    {line.shippedBaseQuantity ===
                                                    null
                                                        ? '—'
                                                        : `${formatDecimal(
                                                              line.shippedBaseQuantity,
                                                          )} ${
                                                              line.baseUnitSymbol
                                                          }`}
                                                </td>

                                                {stockTransfer.status ===
                                                    'received' && (
                                                    <>
                                                        <td className="px-4 py-3">
                                                            {line.receivedBaseQuantity ===
                                                            null
                                                                ? '—'
                                                                : `${formatDecimal(
                                                                      line.receivedBaseQuantity,
                                                                  )} ${
                                                                      line.baseUnitSymbol
                                                                  }`}
                                                        </td>

                                                        <td className="px-4 py-3">
                                                            {line.varianceBaseQuantity ===
                                                            null
                                                                ? '—'
                                                                : `${formatDecimal(
                                                                      line.varianceBaseQuantity,
                                                                  )} ${
                                                                      line.baseUnitSymbol
                                                                  }`}
                                                        </td>
                                                    </>
                                                )}

                                                {canViewCosts && (
                                                    <td className="px-4 py-3 text-right">
                                                        {line.unitCost === null
                                                            ? '—'
                                                            : `${currency} ${formatDecimal(
                                                                  line.unitCost,
                                                              )}`}
                                                    </td>
                                                )}

                                                <td className="px-4 py-3">
                                                    <div>
                                                        OUT:{' '}
                                                        {line.outboundMovementId ===
                                                        null
                                                            ? '—'
                                                            : `#${line.outboundMovementId}`}
                                                    </div>
                                                    <div>
                                                        IN:{' '}
                                                        {line.inboundMovementId ===
                                                        null
                                                            ? '—'
                                                            : `#${line.inboundMovementId}`}
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    )
                )}

                {stockTransfer?.status === 'draft' && (
                    <div className="space-y-2">
                        <div className="flex flex-wrap gap-2">
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
                                                    <div className="space-y-4">
                                                        <DialogHeader>
                                                            <DialogTitle>
                                                                Ship stock
                                                                transfer?
                                                            </DialogTitle>
                                                            <DialogDescription>
                                                                Shipping posts
                                                                outbound stock
                                                                movements and
                                                                removes the
                                                                shipped
                                                                quantities from
                                                                the source
                                                                storage. Review
                                                                the saved draft
                                                                before
                                                                continuing.
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
                                                                    ? 'Shipping…'
                                                                    : 'Ship transfer'}
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
                                                    <div className="space-y-4">
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
                                                                inventory. The
                                                                cancelled
                                                                transfer remains
                                                                in the audit
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
                            <p className="text-xs text-muted-foreground">
                                Save draft changes before shipping or cancelling
                                this transfer.
                            </p>
                        )}
                    </div>
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
                            <div className="space-y-4 rounded-xl border border-sidebar-border/70 p-5 dark:border-sidebar-border">
                                <DirtyStateTracker
                                    dirty={isDirty}
                                    onChange={setReceiptDirty}
                                />

                                <div>
                                    <h2 className="font-medium">
                                        Receive transfer
                                    </h2>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        Enter the actual quantity received in
                                        each item's base unit.
                                    </p>
                                </div>

                                {stockTransfer.lines.map((line, index) => {
                                    const inputId = `received-base-quantity-${line.id}`;

                                    return (
                                        <div
                                            key={line.id}
                                            className="grid gap-4 md:grid-cols-[2fr_1fr]"
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
                                                <div className="text-xs text-muted-foreground">
                                                    Shipped:{' '}
                                                    {line.shippedBaseQuantity ===
                                                    null
                                                        ? '—'
                                                        : formatDecimal(
                                                              line.shippedBaseQuantity,
                                                          )}{' '}
                                                    {line.baseUnitSymbol}
                                                </div>
                                            </div>

                                            <div className="grid gap-2">
                                                <Label htmlFor={inputId}>
                                                    Received (
                                                    {line.baseUnitSymbol})
                                                </Label>
                                                <Input
                                                    id={inputId}
                                                    name={`lines[${index}][received_base_quantity]`}
                                                    type="number"
                                                    min="0"
                                                    max="999999999.999999"
                                                    step="0.000001"
                                                    defaultValue={
                                                        line.shippedBaseQuantity ??
                                                        '0'
                                                    }
                                                    required
                                                />
                                                <InputError
                                                    message={
                                                        errors[
                                                            `lines.${index}.received_base_quantity`
                                                        ]
                                                    }
                                                />
                                            </div>
                                        </div>
                                    );
                                })}

                                <InputError message={errors.lines} />

                                <Dialog
                                    open={receiveDialogOpen}
                                    onOpenChange={setReceiveDialogOpen}
                                >
                                    <DialogTrigger asChild>
                                        <Button
                                            type="button"
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
                                                Receipt posts inbound stock to
                                                the destination and permanently
                                                records the received quantities
                                                and any variance. Review all
                                                quantities before continuing.
                                            </DialogDescription>
                                        </DialogHeader>

                                        {Object.keys(errors).length > 0 && (
                                            <p
                                                role="alert"
                                                className="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive"
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
                            </div>
                        )}
                    </Form>
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
                            Your unsaved stock transfer changes will be lost.
                            This does not undo any transfer state already saved
                            on the server.
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
