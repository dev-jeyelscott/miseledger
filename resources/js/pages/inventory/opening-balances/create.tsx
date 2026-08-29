import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import InventoryItemController from '@/actions/App/Http/Controllers/Inventory/InventoryItemController';
import OpeningBalanceController from '@/actions/App/Http/Controllers/Inventory/OpeningBalanceController';
import InputError from '@/components/input-error';
import { PreviousPageButton } from '@/components/navigation/previous-page-button';
import { PageHeader } from '@/components/page-header';
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
    currency: string;
    locationOptions: Option[];
    storageLocationOptions: StorageLocationOption[];
    inventoryItemOptions: InventoryItemOption[];
    unitOptions: UnitOption[];
};

const textareaClassName =
    'border-input bg-background min-h-24 w-full resize-y rounded-md border px-3 py-2 text-sm shadow-xs outline-none transition-[color,box-shadow] placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive aria-invalid:ring-[3px] aria-invalid:ring-destructive/20 disabled:cursor-not-allowed disabled:opacity-50';

/** Format an organization-local datetime-local value for confirmation review. */
function formatEffectiveTime(value: string, timezone: string): string {
    if (value === '') {
        return 'Not set';
    }

    const parsed = new Date(value);

    if (Number.isNaN(parsed.getTime())) {
        return value;
    }

    return `${new Intl.DateTimeFormat('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    }).format(parsed)} (${timezone})`;
}

export default function OpeningBalanceCreate({
    operationId,
    defaultOccurredAt,
    timezone,
    currency,
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
    const [baseUnitCost, setBaseUnitCost] = useState('');
    const [occurredAt, setOccurredAt] = useState(defaultOccurredAt);
    const [confirmOpen, setConfirmOpen] = useState(false);

    const selectedItem = inventoryItemOptions.find(
        (item) => item.id.toString() === inventoryItemId,
    );

    const availableStorageLocations = storageLocationOptions.filter(
        (storageLocation) =>
            storageLocation.locationId.toString() === locationId,
    );

    const selectedLocation = locationOptions.find(
        (location) => location.id.toString() === locationId,
    );
    const selectedStorageLocation = availableStorageLocations.find(
        (storageLocation) =>
            storageLocation.id.toString() === storageLocationId,
    );
    const selectedUnit = unitOptions.find(
        (unit) => unit.id.toString() === unitId,
    );

    const hasRequiredOptions =
        locationOptions.length > 0 &&
        storageLocationOptions.length > 0 &&
        inventoryItemOptions.length > 0 &&
        unitOptions.length > 0;

    const canReview =
        hasRequiredOptions &&
        locationId !== '' &&
        storageLocationId !== '' &&
        inventoryItemId !== '' &&
        unitId !== '' &&
        quantity.trim() !== '' &&
        baseUnitCost.trim() !== '';

    const estimatedTotalCost =
        selectedItem !== undefined &&
        quantity.trim() !== '' &&
        baseUnitCost.trim() !== '' &&
        !Number.isNaN(Number(quantity)) &&
        !Number.isNaN(Number(baseUnitCost))
            ? (Number(quantity) * Number(baseUnitCost)).toFixed(2)
            : null;

    return (
        <>
            <Head title="Opening balance" />

            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <PageHeader
                    title="Record opening balance"
                    description="Establish initial stock quantity and its starting weighted-average cost."
                />

                <div className="rounded-xl border border-border bg-card p-5 shadow-sm">
                    <div className="mb-6 rounded-lg border border-border bg-muted/30 p-4 text-sm">
                        <p className="font-medium">Initial inventory only</p>

                        <p className="mt-1 text-muted-foreground">
                            An opening balance adds stock and establishes its
                            starting weighted-average cost. Do not use this form
                            to correct an existing balance. Later quantity
                            changes must use receiving, stock-count, waste,
                            transfer, or adjustment workflows.
                        </p>
                    </div>

                    <Form
                        id="opening-balance-form"
                        action={OpeningBalanceController.store().url}
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
                                        label="Opening quantity"
                                        error={errors.quantity}
                                    >
                                        <Input
                                            name="quantity"
                                            type="number"
                                            min="0.000001"
                                            step="0.000001"
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
                                        id="base_unit_cost"
                                        label={`Cost per base unit${
                                            selectedItem
                                                ? ` (${currency} / ${selectedItem.baseUnitSymbol})`
                                                : ''
                                        }`}
                                        helper={
                                            selectedItem
                                                ? `Enter the cost of one ${selectedItem.baseUnitSymbol}, even if the opening quantity is entered using another unit. This establishes the starting weighted-average cost.`
                                                : 'Select an inventory item to see its authoritative base unit.'
                                        }
                                        error={errors.base_unit_cost}
                                    >
                                        <Input
                                            name="base_unit_cost"
                                            type="number"
                                            min="0"
                                            step="0.0001"
                                            value={baseUnitCost}
                                            onChange={(event) =>
                                                setBaseUnitCost(
                                                    event.target.value,
                                                )
                                            }
                                            className="tabular-nums"
                                            required
                                        />
                                    </Field>

                                    <Field
                                        id="occurred_at"
                                        label="Opening date"
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

                                <Field
                                    id="notes"
                                    label="Notes"
                                    error={errors.notes}
                                >
                                    <textarea
                                        name="notes"
                                        rows={4}
                                        maxLength={2000}
                                        className={textareaClassName}
                                        placeholder="Optional onboarding or migration reference"
                                    />
                                </Field>

                                {!hasRequiredOptions && (
                                    <p className="text-sm text-destructive">
                                        Active locations, storage locations,
                                        inventory items, and units are required
                                        before an opening balance can be
                                        recorded.
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
                                                Review opening balance
                                            </Button>
                                        </DialogTrigger>

                                        <DialogContent>
                                            <DialogHeader>
                                                <DialogTitle>
                                                    Create this initial stock?
                                                </DialogTitle>
                                                <DialogDescription>
                                                    This establishes initial
                                                    inventory and the starting
                                                    weighted-average cost for
                                                    this item at this storage
                                                    location. Later changes must
                                                    use normal inventory
                                                    workflows, not this form.
                                                </DialogDescription>
                                            </DialogHeader>

                                            <div className="rounded-lg border border-border bg-muted/30 p-4 text-sm">
                                                <div className="font-medium">
                                                    {selectedItem?.name ??
                                                        'Item'}
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
                                                            Opening quantity
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
                                                            Cost per{' '}
                                                            {
                                                                selectedItem?.baseUnitSymbol
                                                            }
                                                        </dt>
                                                        <dd className="text-right tabular-nums">
                                                            {currency}{' '}
                                                            {baseUnitCost}
                                                        </dd>
                                                    </div>
                                                    {estimatedTotalCost !==
                                                        null && (
                                                        <div className="flex justify-between gap-4 border-t border-border pt-1.5">
                                                            <dt className="text-muted-foreground">
                                                                Estimated total
                                                                cost
                                                            </dt>
                                                            <dd className="text-right font-medium tabular-nums">
                                                                {currency}{' '}
                                                                {
                                                                    estimatedTotalCost
                                                                }
                                                            </dd>
                                                        </div>
                                                    )}
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
                                                    form="opening-balance-form"
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

OpeningBalanceCreate.layout = {
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
            title: 'Opening balance',
            href: OpeningBalanceController.create(),
        },
    ],
};
