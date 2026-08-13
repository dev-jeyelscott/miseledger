import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import StockCountController from '@/actions/App/Http/Controllers/Inventory/StockCountController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
};

type UnitOption = {
    id: number;
    name: string;
    symbol: string;
};

type StockCountLine = {
    id: number;
    inventoryItemId: number;
    itemName: string;
    itemSku: string;
    expectedBaseQuantity: string;
    countedQuantity: string;
    countUnitId: number;
    countUnitSymbol: string;
    countedBaseQuantity: string;
    baseUnitSymbol: string;
    varianceBaseQuantity: string;
    varianceUnitCost: string | null;
    varianceTotalCost: string | null;
    notes: string | null;
    movementId: number | null;
};

type StockCount = {
    id: number;
    number: string;
    status: string;
    locationId: number;
    locationName: string;
    storageLocationId: number;
    storageLocationName: string;
    countedAt: string | null;
    createdBy: string | null;
    submittedBy: string | null;
    finalizedBy: string | null;
    finalizedAt: string | null;
    lines: StockCountLine[];
};

type LineState = {
    inventoryItemId: string;
    countedQuantity: string;
    countUnitId: string;
    notes: string;
};

type Props = {
    stockCount: StockCount | null;
    locationOptions: LocationOption[];
    storageLocationOptions: StorageLocationOption[];
    inventoryItemOptions: InventoryItemOption[];
    unitOptions: UnitOption[];
    currency: string;
    canCreate: boolean;
    canFinalize: boolean;
    canViewCosts: boolean;
};

const emptyLine = (): LineState => ({
    inventoryItemId: '',
    countedQuantity: '0',
    countUnitId: '',
    notes: '',
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

export default function StockCountForm({
    stockCount,
    locationOptions,
    storageLocationOptions,
    inventoryItemOptions,
    unitOptions,
    currency,
    canCreate,
    canFinalize,
    canViewCosts,
}: Props) {
    const editable =
        canCreate && (stockCount === null || stockCount.status === 'draft');

    const finalized = stockCount?.status === 'finalized';

    const initialLocationId =
        stockCount?.locationId.toString() ??
        locationOptions[0]?.id.toString() ??
        '';

    const initialStorageLocationId =
        stockCount?.storageLocationId.toString() ??
        storageLocationOptions
            .find(
                (storageLocation) =>
                    storageLocation.locationId.toString() === initialLocationId,
            )
            ?.id.toString() ??
        '';

    const [locationId, setLocationId] = useState(initialLocationId);
    const [storageLocationId, setStorageLocationId] = useState(
        initialStorageLocationId,
    );

    const [lines, setLines] = useState<LineState[]>(
        stockCount?.lines.map((line) => ({
            inventoryItemId: line.inventoryItemId.toString(),
            countedQuantity: line.countedQuantity,
            countUnitId: line.countUnitId.toString(),
            notes: line.notes ?? '',
        })) ?? [emptyLine()],
    );

    const selectedStorageLocations = storageLocationOptions.filter(
        (storageLocation) =>
            storageLocation.locationId.toString() === locationId,
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

    const handleLocationChange = (value: string) => {
        setLocationId(value);

        const firstStorage = storageLocationOptions.find(
            (storageLocation) =>
                storageLocation.locationId.toString() === value,
        );

        setStorageLocationId(firstStorage?.id.toString() ?? '');
    };

    const handleItemChange = (index: number, value: string) => {
        const inventoryItem = inventoryItemOptions.find(
            (item) => item.id.toString() === value,
        );

        updateLine(index, {
            inventoryItemId: value,
            countUnitId: inventoryItem?.baseUnitId.toString() ?? '',
        });
    };

    const formAttributes =
        stockCount === null
            ? StockCountController.store.form()
            : StockCountController.update.form.put(stockCount.id);

    const title = stockCount === null ? 'New stock count' : stockCount.number;

    return (
        <>
            <Head title={title} />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div>
                    <h1 className="text-2xl font-semibold">{title}</h1>

                    {stockCount !== null && (
                        <p className="mt-1 text-sm text-muted-foreground">
                            {stockCount.locationName} →{' '}
                            {stockCount.storageLocationName} ·{' '}
                            <span className="capitalize">
                                {stockCount.status}
                            </span>
                        </p>
                    )}
                </div>

                {editable ? (
                    <Form {...formAttributes}>
                        {({ processing, errors }) => (
                            <div className="space-y-6">
                                <div className="grid gap-4 rounded-xl border border-sidebar-border/70 p-5 md:grid-cols-3 dark:border-sidebar-border">
                                    <div className="grid gap-2">
                                        <Label>Count number</Label>
                                        <Input
                                            name="number"
                                            defaultValue={
                                                stockCount?.number ?? ''
                                            }
                                            required
                                        />
                                        <InputError message={errors.number} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label>Location</Label>
                                        <select
                                            name="location_id"
                                            value={locationId}
                                            onChange={(event) =>
                                                handleLocationChange(
                                                    event.target.value,
                                                )
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
                                        <Label>Storage location</Label>
                                        <select
                                            name="storage_location_id"
                                            value={storageLocationId}
                                            onChange={(event) =>
                                                setStorageLocationId(
                                                    event.target.value,
                                                )
                                            }
                                            required
                                            className="h-9 rounded-md border bg-background px-3 text-sm"
                                        >
                                            <option value="">
                                                Select storage
                                            </option>

                                            {selectedStorageLocations.map(
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
                                </div>

                                <div className="space-y-4">
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <h2 className="font-semibold">
                                                Count lines
                                            </h2>
                                            <p className="text-sm text-muted-foreground">
                                                Enter the physical quantity in
                                                the practical unit used during
                                                counting.
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

                                    {lines.map((line, index) => {
                                        const selectedItem =
                                            inventoryItemOptions.find(
                                                (item) =>
                                                    item.id.toString() ===
                                                    line.inventoryItemId,
                                            );

                                        return (
                                            <div
                                                key={index}
                                                className="space-y-4 rounded-xl border border-sidebar-border/70 p-5 dark:border-sidebar-border"
                                            >
                                                <div className="grid gap-4 lg:grid-cols-4">
                                                    <div className="grid gap-2 lg:col-span-2">
                                                        <Label>
                                                            Inventory item
                                                        </Label>
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
                                                                (item) => {
                                                                    const used =
                                                                        lines.some(
                                                                            (
                                                                                otherLine,
                                                                                otherIndex,
                                                                            ) =>
                                                                                otherIndex !==
                                                                                    index &&
                                                                                otherLine.inventoryItemId ===
                                                                                    item.id.toString(),
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
                                                                                used
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
                                                                        </option>
                                                                    );
                                                                },
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
                                                        <Label>
                                                            Physical quantity
                                                        </Label>
                                                        <Input
                                                            name={`lines[${index}][counted_quantity]`}
                                                            type="number"
                                                            min="0"
                                                            max="999999999.999999"
                                                            step="0.000001"
                                                            value={
                                                                line.countedQuantity
                                                            }
                                                            onChange={(event) =>
                                                                updateLine(
                                                                    index,
                                                                    {
                                                                        countedQuantity:
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
                                                                    `lines.${index}.counted_quantity`
                                                                ]
                                                            }
                                                        />
                                                    </div>

                                                    <div className="grid gap-2">
                                                        <Label>
                                                            Count unit
                                                        </Label>
                                                        <select
                                                            name={`lines[${index}][count_unit_id]`}
                                                            value={
                                                                line.countUnitId
                                                            }
                                                            onChange={(event) =>
                                                                updateLine(
                                                                    index,
                                                                    {
                                                                        countUnitId:
                                                                            event
                                                                                .target
                                                                                .value,
                                                                    },
                                                                )
                                                            }
                                                            required
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
                                                                    `lines.${index}.count_unit_id`
                                                                ]
                                                            }
                                                        />
                                                    </div>
                                                </div>

                                                <div className="grid gap-4 md:grid-cols-[1fr_auto]">
                                                    <div className="grid gap-2">
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

                                                    <div className="flex items-end">
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

                                                {selectedItem && (
                                                    <p className="text-xs text-muted-foreground">
                                                        Base unit:{' '}
                                                        {
                                                            selectedItem.baseUnitSymbol
                                                        }
                                                        . Conversion is
                                                        validated and
                                                        snapshotted by the
                                                        server.
                                                    </p>
                                                )}

                                                {stockCount?.lines[index] && (
                                                    <p className="text-xs text-muted-foreground">
                                                        Last saved base
                                                        quantity:{' '}
                                                        {formatDecimal(
                                                            stockCount.lines[
                                                                index
                                                            ]
                                                                .countedBaseQuantity,
                                                        )}{' '}
                                                        {
                                                            stockCount.lines[
                                                                index
                                                            ].baseUnitSymbol
                                                        }
                                                    </p>
                                                )}
                                            </div>
                                        );
                                    })}

                                    <InputError message={errors.lines} />
                                </div>

                                <div className="flex gap-2">
                                    <Button type="submit" disabled={processing}>
                                        {stockCount === null
                                            ? 'Create draft'
                                            : 'Save draft'}
                                    </Button>

                                    <Button variant="outline" asChild>
                                        <Link
                                            href={StockCountController.index()}
                                        >
                                            Back
                                        </Link>
                                    </Button>
                                </div>
                            </div>
                        )}
                    </Form>
                ) : (
                    stockCount && (
                        <div className="space-y-5">
                            <div className="grid gap-4 rounded-xl border border-sidebar-border/70 p-5 md:grid-cols-4 dark:border-sidebar-border">
                                <div>
                                    <div className="text-sm text-muted-foreground">
                                        Status
                                    </div>
                                    <div className="font-medium capitalize">
                                        {stockCount.status}
                                    </div>
                                </div>

                                <div>
                                    <div className="text-sm text-muted-foreground">
                                        Counted at
                                    </div>
                                    <div className="font-medium">
                                        {formatDate(stockCount.countedAt)}
                                    </div>
                                </div>

                                <div>
                                    <div className="text-sm text-muted-foreground">
                                        Submitted by
                                    </div>
                                    <div className="font-medium">
                                        {stockCount.submittedBy ?? '—'}
                                    </div>
                                </div>

                                <div>
                                    <div className="text-sm text-muted-foreground">
                                        Finalized by
                                    </div>
                                    <div className="font-medium">
                                        {stockCount.finalizedBy ?? '—'}
                                    </div>
                                </div>
                            </div>

                            <div className="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                                <table className="w-full text-sm">
                                    <thead className="border-b text-left">
                                        <tr>
                                            <th className="px-4 py-3">Item</th>
                                            <th className="px-4 py-3">
                                                Counted
                                            </th>
                                            <th className="px-4 py-3">
                                                Counted base
                                            </th>

                                            {finalized && (
                                                <>
                                                    <th className="px-4 py-3">
                                                        Expected
                                                    </th>
                                                    <th className="px-4 py-3">
                                                        Variance
                                                    </th>
                                                </>
                                            )}

                                            {finalized && canViewCosts && (
                                                <th className="px-4 py-3 text-right">
                                                    Variance value
                                                </th>
                                            )}

                                            {finalized && (
                                                <th className="px-4 py-3">
                                                    Adjustment
                                                </th>
                                            )}
                                        </tr>
                                    </thead>

                                    <tbody>
                                        {stockCount.lines.map((line) => (
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
                                                        line.countedQuantity,
                                                    )}{' '}
                                                    {line.countUnitSymbol}
                                                </td>

                                                <td className="px-4 py-3">
                                                    {formatDecimal(
                                                        line.countedBaseQuantity,
                                                    )}{' '}
                                                    {line.baseUnitSymbol}
                                                </td>

                                                {finalized && (
                                                    <>
                                                        <td className="px-4 py-3">
                                                            {formatDecimal(
                                                                line.expectedBaseQuantity,
                                                            )}{' '}
                                                            {
                                                                line.baseUnitSymbol
                                                            }
                                                        </td>

                                                        <td className="px-4 py-3">
                                                            {formatDecimal(
                                                                line.varianceBaseQuantity,
                                                            )}{' '}
                                                            {
                                                                line.baseUnitSymbol
                                                            }
                                                        </td>
                                                    </>
                                                )}

                                                {finalized && canViewCosts && (
                                                    <td className="px-4 py-3 text-right">
                                                        {line.varianceTotalCost ===
                                                        null
                                                            ? '—'
                                                            : `${currency} ${formatDecimal(
                                                                  line.varianceTotalCost,
                                                              )}`}
                                                    </td>
                                                )}

                                                {finalized && (
                                                    <td className="px-4 py-3">
                                                        {line.movementId ===
                                                        null
                                                            ? 'No movement'
                                                            : `#${line.movementId}`}
                                                    </td>
                                                )}
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    )
                )}

                {stockCount?.status === 'draft' && canCreate && (
                    <div className="flex gap-2">
                        <Form
                            {...StockCountController.submit.form(stockCount.id)}
                        >
                            {({ processing }) => (
                                <Button type="submit" disabled={processing}>
                                    Submit count
                                </Button>
                            )}
                        </Form>

                        <Form
                            {...StockCountController.cancel.form(stockCount.id)}
                        >
                            {({ processing }) => (
                                <Button
                                    type="submit"
                                    variant="outline"
                                    disabled={processing}
                                >
                                    Cancel count
                                </Button>
                            )}
                        </Form>
                    </div>
                )}

                {stockCount?.status === 'submitted' && (
                    <div className="flex gap-2">
                        {canFinalize && (
                            <Form
                                {...StockCountController.finalize.form(
                                    stockCount.id,
                                )}
                            >
                                {({ processing }) => (
                                    <Button type="submit" disabled={processing}>
                                        Finalize count
                                    </Button>
                                )}
                            </Form>
                        )}

                        {canCreate && (
                            <Form
                                {...StockCountController.cancel.form(
                                    stockCount.id,
                                )}
                            >
                                {({ processing }) => (
                                    <Button
                                        type="submit"
                                        variant="outline"
                                        disabled={processing}
                                    >
                                        Cancel count
                                    </Button>
                                )}
                            </Form>
                        )}
                    </div>
                )}

                <Button variant="outline" asChild className="w-fit">
                    <Link href={StockCountController.index()}>
                        Back to stock counts
                    </Link>
                </Button>
            </div>
        </>
    );
}

StockCountForm.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
        {
            title: 'Stock counts',
            href: StockCountController.index(),
        },
    ],
};
