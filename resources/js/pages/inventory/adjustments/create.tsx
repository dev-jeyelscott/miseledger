import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import InventoryAdjustmentController from '@/actions/App/Http/Controllers/Inventory/InventoryAdjustmentController';
import InventoryItemController from '@/actions/App/Http/Controllers/Inventory/InventoryItemController';
import InputError from '@/components/input-error';
import { PreviousPageButton } from '@/components/navigation/previous-page-button';
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
import { dashboard } from '@/routes';

type Option = {
    id: number;
    name: string;
};

type StorageLocationOption = Option & {
    locationId: number;
};

type InventoryItemOption = Option & {
    sku: string;
    baseUnitId: number;
    baseUnitSymbol: string;
};

type UnitOption = Option & {
    symbol: string;
};

type Props = {
    operationId: string;
    defaultOccurredAt: string;
    timezone: string;
    locationOptions: Option[];
    storageLocationOptions: StorageLocationOption[];
    inventoryItemOptions: InventoryItemOption[];
    unitOptions: UnitOption[];
};

const textareaClassName =
    'border-input bg-background min-h-24 w-full resize-y rounded-md border px-3 py-2 text-sm shadow-xs outline-none transition-[color,box-shadow] placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive aria-invalid:ring-[3px] aria-invalid:ring-destructive/20 disabled:cursor-not-allowed disabled:opacity-50';

/** Derive the Increase/Decrease semantic from a signed quantity string. */
function adjustmentDirection(quantity: string): {
    label: string;
    variant: 'success' | 'danger' | 'neutral';
} {
    const trimmed = quantity.trim();

    if (trimmed === '' || !/[1-9]/.test(trimmed)) {
        return { label: 'No change', variant: 'neutral' };
    }

    return trimmed.startsWith('-')
        ? { label: 'Decrease', variant: 'danger' }
        : { label: 'Increase', variant: 'success' };
}

/**
 * Format a `datetime-local` input value for confirmation review without
 * reinterpreting it through the browser's local timezone. The value is the
 * exact organization-local wall-clock instant the user entered.
 */
function formatEffectiveTime(value: string, timezone: string): string {
    const match = /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})/.exec(value);

    if (match === null) {
        return value === '' ? 'Not set' : value;
    }

    const [, year, month, day, hour, minute] = match;
    const monthNames = [
        'Jan',
        'Feb',
        'Mar',
        'Apr',
        'May',
        'Jun',
        'Jul',
        'Aug',
        'Sep',
        'Oct',
        'Nov',
        'Dec',
    ];
    const hourNumber = Number(hour);
    const period = hourNumber >= 12 ? 'PM' : 'AM';
    const displayHour = hourNumber % 12 === 0 ? 12 : hourNumber % 12;

    return `${monthNames[Number(month) - 1]} ${Number(day)}, ${year}, ${displayHour}:${minute} ${period} (${timezone})`;
}

export default function InventoryAdjustmentCreate({
    operationId,
    defaultOccurredAt,
    timezone,
    locationOptions,
    storageLocationOptions,
    inventoryItemOptions,
    unitOptions,
}: Props) {
    const [locationId, setLocationId] = useState('');
    const [storageLocationId, setStorageLocationId] = useState('');
    const [inventoryItemId, setInventoryItemId] = useState('');
    const [unitId, setUnitId] = useState('');
    const [quantity, setQuantity] = useState('');
    const [occurredAt, setOccurredAt] = useState(defaultOccurredAt);
    const [reason, setReason] = useState('');
    const [confirmOpen, setConfirmOpen] = useState(false);

    const availableStorageLocations = storageLocationOptions.filter(
        (storageLocation) =>
            storageLocation.locationId.toString() === locationId,
    );

    const hasRequiredOptions =
        locationOptions.length > 0 &&
        storageLocationOptions.length > 0 &&
        inventoryItemOptions.length > 0 &&
        unitOptions.length > 0;

    const selectedLocation = locationOptions.find(
        (location) => location.id.toString() === locationId,
    );
    const selectedStorageLocation = availableStorageLocations.find(
        (storageLocation) =>
            storageLocation.id.toString() === storageLocationId,
    );
    const selectedItem = inventoryItemOptions.find(
        (item) => item.id.toString() === inventoryItemId,
    );
    const selectedUnit = unitOptions.find(
        (unit) => unit.id.toString() === unitId,
    );

    const direction = adjustmentDirection(quantity);
    const canReview =
        hasRequiredOptions &&
        locationId !== '' &&
        storageLocationId !== '' &&
        inventoryItemId !== '' &&
        unitId !== '' &&
        quantity.trim() !== '' &&
        reason.trim() !== '';

    return (
        <>
            <Head title="Manual inventory adjustment" />

            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <PageHeader
                    title="Manual inventory adjustment"
                    description="Correct on-hand stock with an audited, reasoned movement. Use a positive quantity to add stock and a negative quantity to remove stock."
                />

                <div className="rounded-xl border border-border bg-card p-5 shadow-sm">
                    <div className="mb-6 rounded-lg border border-border bg-muted/30 p-4 text-sm">
                        <p className="font-medium">Privileged correction</p>

                        <p className="mt-1 text-muted-foreground">
                            Every adjustment requires a documented reason and is
                            recorded on the immutable stock ledger. An
                            adjustment that would take stock negative is
                            rejected.
                        </p>
                    </div>

                    <Form
                        id="inventory-adjustment-form"
                        action={InventoryAdjustmentController.store().url}
                        method="post"
                        onSuccess={() => setConfirmOpen(false)}
                    >
                        {({ errors, processing }) => (
                            <div className="grid gap-6">
                                <input
                                    type="hidden"
                                    name="operation_id"
                                    value={operationId}
                                />

                                <InputError message={errors.operation_id} />

                                <div className="grid gap-5 md:grid-cols-2">
                                    <Field
                                        id="location_id"
                                        label="Location"
                                        error={errors.location_id}
                                    >
                                        <NativeSelect
                                            name="location_id"
                                            value={locationId}
                                            onChange={(event) => {
                                                setLocationId(
                                                    event.target.value,
                                                );
                                                setStorageLocationId('');
                                            }}
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
                                        id="storage_location_id"
                                        label="Storage location"
                                        error={errors.storage_location_id}
                                    >
                                        <NativeSelect
                                            name="storage_location_id"
                                            value={storageLocationId}
                                            onChange={(event) =>
                                                setStorageLocationId(
                                                    event.target.value,
                                                )
                                            }
                                            required
                                            disabled={locationId === ''}
                                        >
                                            <option value="">
                                                Select storage location
                                            </option>

                                            {availableStorageLocations.map(
                                                (storageLocation) => (
                                                    <option
                                                        key={storageLocation.id}
                                                        value={
                                                            storageLocation.id
                                                        }
                                                    >
                                                        {storageLocation.name}
                                                    </option>
                                                ),
                                            )}
                                        </NativeSelect>
                                    </Field>

                                    <Field
                                        id="inventory_item_id"
                                        label="Inventory item"
                                        error={errors.inventory_item_id}
                                    >
                                        <NativeSelect
                                            name="inventory_item_id"
                                            value={inventoryItemId}
                                            onChange={(event) => {
                                                const nextItemId =
                                                    event.target.value;

                                                setInventoryItemId(nextItemId);

                                                const item =
                                                    inventoryItemOptions.find(
                                                        (option) =>
                                                            option.id.toString() ===
                                                            nextItemId,
                                                    );

                                                setUnitId(
                                                    item?.baseUnitId.toString() ??
                                                        '',
                                                );
                                            }}
                                            required
                                        >
                                            <option value="">
                                                Select item
                                            </option>

                                            {inventoryItemOptions.map(
                                                (item) => (
                                                    <option
                                                        key={item.id}
                                                        value={item.id}
                                                    >
                                                        {item.name} ({item.sku})
                                                    </option>
                                                ),
                                            )}
                                        </NativeSelect>
                                    </Field>

                                    <Field
                                        id="quantity"
                                        label="Adjustment quantity"
                                        helper="Positive quantities add stock, negative quantities remove stock."
                                        error={errors.quantity}
                                    >
                                        <Input
                                            name="quantity"
                                            type="number"
                                            step="0.000001"
                                            placeholder="e.g. 2 or -3"
                                            value={quantity}
                                            onChange={(event) =>
                                                setQuantity(event.target.value)
                                            }
                                            className="tabular-nums"
                                            required
                                        />
                                    </Field>

                                    <Field
                                        id="unit_id"
                                        label="Quantity unit"
                                        error={errors.unit_id}
                                    >
                                        <NativeSelect
                                            name="unit_id"
                                            value={unitId}
                                            onChange={(event) =>
                                                setUnitId(event.target.value)
                                            }
                                            required
                                        >
                                            <option value="">
                                                Select unit
                                            </option>

                                            {unitOptions.map((unit) => (
                                                <option
                                                    key={unit.id}
                                                    value={unit.id}
                                                >
                                                    {unit.name} ({unit.symbol})
                                                </option>
                                            ))}
                                        </NativeSelect>
                                    </Field>

                                    <Field
                                        id="occurred_at"
                                        label="Adjustment date"
                                        helper={`Recorded in the organization timezone (${timezone}).`}
                                        error={errors.occurred_at}
                                    >
                                        <Input
                                            name="occurred_at"
                                            type="datetime-local"
                                            value={occurredAt}
                                            onChange={(event) =>
                                                setOccurredAt(
                                                    event.target.value,
                                                )
                                            }
                                            required
                                        />
                                    </Field>
                                </div>

                                <div className="rounded-lg border border-border bg-muted/20 p-4">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className="text-sm font-medium">
                                            Movement direction
                                        </span>
                                        <StatusBadge
                                            label={direction.label}
                                            variant={direction.variant}
                                        />
                                    </div>
                                </div>

                                <Field
                                    id="reason"
                                    label="Reason"
                                    error={errors.reason}
                                >
                                    <textarea
                                        name="reason"
                                        rows={4}
                                        maxLength={2000}
                                        className={textareaClassName}
                                        placeholder="Explain why this correction is necessary"
                                        value={reason}
                                        onChange={(event) =>
                                            setReason(event.target.value)
                                        }
                                        required
                                    />
                                </Field>

                                {!hasRequiredOptions && (
                                    <p className="text-sm text-destructive">
                                        Active locations, storage locations,
                                        inventory items, and units are required
                                        before an adjustment can be recorded.
                                    </p>
                                )}

                                <div className="flex gap-2">
                                    <Dialog
                                        open={confirmOpen}
                                        onOpenChange={setConfirmOpen}
                                    >
                                        <DialogTrigger asChild>
                                            <Button
                                                type="button"
                                                disabled={
                                                    processing || !canReview
                                                }
                                            >
                                                Review adjustment
                                            </Button>
                                        </DialogTrigger>

                                        <DialogContent>
                                            <DialogHeader>
                                                <DialogTitle>
                                                    Post this adjustment?
                                                </DialogTitle>
                                                <DialogDescription>
                                                    This posts an audited
                                                    movement to the immutable
                                                    stock ledger. It cannot be
                                                    edited afterward.
                                                </DialogDescription>
                                            </DialogHeader>

                                            <div className="rounded-lg border border-border bg-muted/30 p-4 text-sm">
                                                <div className="flex items-center justify-between gap-3">
                                                    <span className="font-medium">
                                                        {selectedItem?.name ??
                                                            'Item'}
                                                    </span>
                                                    <StatusBadge
                                                        label={direction.label}
                                                        variant={
                                                            direction.variant
                                                        }
                                                    />
                                                </div>

                                                <dl className="mt-3 grid gap-1.5">
                                                    <div className="flex justify-between gap-4">
                                                        <dt className="text-muted-foreground">
                                                            Location
                                                        </dt>
                                                        <dd className="text-right">
                                                            {
                                                                selectedLocation?.name
                                                            }{' '}
                                                            /{' '}
                                                            {
                                                                selectedStorageLocation?.name
                                                            }
                                                        </dd>
                                                    </div>
                                                    <div className="flex justify-between gap-4">
                                                        <dt className="text-muted-foreground">
                                                            Quantity
                                                        </dt>
                                                        <dd className="text-right tabular-nums">
                                                            {quantity}{' '}
                                                            {
                                                                selectedUnit?.symbol
                                                            }
                                                        </dd>
                                                    </div>
                                                    <div className="flex justify-between gap-4">
                                                        <dt className="text-muted-foreground">
                                                            Effective time
                                                        </dt>
                                                        <dd className="text-right">
                                                            {formatEffectiveTime(
                                                                occurredAt,
                                                                timezone,
                                                            )}
                                                        </dd>
                                                    </div>
                                                </dl>

                                                <div className="mt-3 border-t border-border pt-3">
                                                    <div className="text-xs text-muted-foreground">
                                                        Reason
                                                    </div>
                                                    <p className="mt-1">
                                                        {reason}
                                                    </p>
                                                </div>
                                            </div>

                                            <DialogFooter>
                                                <DialogClose asChild>
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        disabled={processing}
                                                    >
                                                        Keep editing
                                                    </Button>
                                                </DialogClose>

                                                <Button
                                                    type="submit"
                                                    form="inventory-adjustment-form"
                                                    disabled={processing}
                                                >
                                                    {processing
                                                        ? 'Recording...'
                                                        : 'Confirm and record'}
                                                </Button>
                                            </DialogFooter>
                                        </DialogContent>
                                    </Dialog>

                                    <PreviousPageButton
                                        variant="outline"
                                        fallback={
                                            InventoryItemController.index().url
                                        }
                                        disabled={processing}
                                    >
                                        Cancel
                                    </PreviousPageButton>
                                </div>
                            </div>
                        )}
                    </Form>
                </div>
            </div>
        </>
    );
}

InventoryAdjustmentCreate.layout = {
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
            title: 'Manual adjustment',
            href: InventoryAdjustmentController.create(),
        },
    ],
};
