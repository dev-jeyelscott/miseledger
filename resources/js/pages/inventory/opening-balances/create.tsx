import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import InventoryItemController from '@/actions/App/Http/Controllers/Inventory/InventoryItemController';
import OpeningBalanceController from '@/actions/App/Http/Controllers/Inventory/OpeningBalanceController';
import InputError from '@/components/input-error';
import { PreviousPageButton } from '@/components/navigation/previous-page-button';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
    currency: string;
    locationOptions: Option[];
    storageLocationOptions: StorageLocationOption[];
    inventoryItemOptions: InventoryItemOption[];
    unitOptions: UnitOption[];
};

export default function OpeningBalanceCreate({
    operationId,
    defaultOccurredAt,
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

    const selectedItem = inventoryItemOptions.find(
        (item) => item.id.toString() === inventoryItemId,
    );

    const availableStorageLocations = storageLocationOptions.filter(
        (storageLocation) =>
            storageLocation.locationId.toString() === locationId,
    );

    const hasRequiredOptions =
        locationOptions.length > 0 &&
        storageLocationOptions.length > 0 &&
        inventoryItemOptions.length > 0 &&
        unitOptions.length > 0;

    return (
        <>
            <Head title="Opening balance" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div>
                    <h1 className="text-2xl font-semibold">
                        Record opening balance
                    </h1>

                    <p className="mt-1 text-sm text-muted-foreground">
                        Establish initial stock quantity and its starting
                        weighted-average cost.
                    </p>
                </div>

                <div className="rounded-xl border border-sidebar-border/70 p-5 dark:border-sidebar-border">
                    <div className="mb-6 rounded-lg border bg-muted/30 p-4 text-sm">
                        <p className="font-medium">Initial inventory only</p>

                        <p className="mt-1 text-muted-foreground">
                            An opening balance adds stock and establishes its
                            starting cost. Do not use this form to correct an
                            existing balance. Later quantity changes should use
                            receiving, stock-count, waste, transfer, or
                            adjustment workflows.
                        </p>
                    </div>

                    <Form
                        action={OpeningBalanceController.store().url}
                        method="post"
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
                                    <div className="grid gap-2">
                                        <Label htmlFor="location_id">
                                            Location
                                        </Label>

                                        <select
                                            id="location_id"
                                            name="location_id"
                                            value={locationId}
                                            onChange={(event) => {
                                                setLocationId(
                                                    event.target.value,
                                                );
                                                setStorageLocationId('');
                                            }}
                                            className="h-9 rounded-md border bg-background px-3 text-sm"
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
                                        </select>

                                        <InputError
                                            message={errors.location_id}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="storage_location_id">
                                            Storage location
                                        </Label>

                                        <select
                                            id="storage_location_id"
                                            name="storage_location_id"
                                            value={storageLocationId}
                                            onChange={(event) =>
                                                setStorageLocationId(
                                                    event.target.value,
                                                )
                                            }
                                            className="h-9 rounded-md border bg-background px-3 text-sm"
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
                                        </select>

                                        <InputError
                                            message={errors.storage_location_id}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="inventory_item_id">
                                            Inventory item
                                        </Label>

                                        <select
                                            id="inventory_item_id"
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
                                            className="h-9 rounded-md border bg-background px-3 text-sm"
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
                                        </select>

                                        <InputError
                                            message={errors.inventory_item_id}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="quantity">
                                            Opening quantity
                                        </Label>

                                        <Input
                                            id="quantity"
                                            name="quantity"
                                            type="number"
                                            min="0.000001"
                                            step="0.000001"
                                            required
                                        />

                                        <InputError message={errors.quantity} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="unit_id">
                                            Quantity unit
                                        </Label>

                                        <select
                                            id="unit_id"
                                            name="unit_id"
                                            value={unitId}
                                            onChange={(event) =>
                                                setUnitId(event.target.value)
                                            }
                                            className="h-9 rounded-md border bg-background px-3 text-sm"
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
                                        </select>

                                        <InputError message={errors.unit_id} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="base_unit_cost">
                                            Cost per base unit
                                            {selectedItem
                                                ? ` (${currency} / ${selectedItem.baseUnitSymbol})`
                                                : ''}
                                        </Label>

                                        <Input
                                            id="base_unit_cost"
                                            name="base_unit_cost"
                                            type="number"
                                            min="0"
                                            step="0.0001"
                                            required
                                        />

                                        <p className="text-xs text-muted-foreground">
                                            {selectedItem
                                                ? `Enter the cost of one ${selectedItem.baseUnitSymbol}, even if the opening quantity is entered using another unit.`
                                                : 'Select an inventory item to see its authoritative base unit.'}
                                        </p>

                                        <InputError
                                            message={errors.base_unit_cost}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="occurred_at">
                                            Opening date
                                        </Label>

                                        <Input
                                            id="occurred_at"
                                            name="occurred_at"
                                            type="datetime-local"
                                            defaultValue={defaultOccurredAt}
                                            required
                                        />

                                        <InputError
                                            message={errors.occurred_at}
                                        />
                                    </div>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="notes">Notes</Label>

                                    <textarea
                                        id="notes"
                                        name="notes"
                                        rows={4}
                                        maxLength={2000}
                                        className="rounded-md border bg-background px-3 py-2 text-sm"
                                        placeholder="Optional onboarding or migration reference"
                                    />

                                    <InputError message={errors.notes} />
                                </div>

                                {!hasRequiredOptions && (
                                    <p className="text-sm text-destructive">
                                        Active locations, storage locations,
                                        inventory items, and units are required
                                        before an opening balance can be
                                        recorded.
                                    </p>
                                )}

                                <div className="flex gap-2">
                                    <Button
                                        type="submit"
                                        disabled={
                                            processing || !hasRequiredOptions
                                        }
                                    >
                                        {processing
                                            ? 'Recording...'
                                            : 'Record opening balance'}
                                    </Button>

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
