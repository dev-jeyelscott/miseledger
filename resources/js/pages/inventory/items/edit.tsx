import { Form, Head, Link } from '@inertiajs/react';
import InventoryItemController from '@/actions/App/Http/Controllers/Inventory/InventoryItemController';
import InventoryItemUnitController from '@/actions/App/Http/Controllers/Inventory/InventoryItemUnitController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import type { InventoryItemDetail, UnitOfMeasureData } from '@/types';

type Props = {
    item: InventoryItemDetail;
    units: UnitOfMeasureData[];
    availableConversionUnits: UnitOfMeasureData[];
};

export default function EditInventoryItem({
    item,
    units,
    availableConversionUnits,
}: Props) {
    return (
        <>
            <Head title={item.name} />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div>
                    <h1 className="text-2xl font-semibold">{item.name}</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {item.sku}
                    </p>
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <div className="rounded-xl border border-sidebar-border/70 p-5 dark:border-sidebar-border">
                        <h2 className="mb-5 font-medium">Item master</h2>

                        <Form
                            {...InventoryItemController.update.form(item.id)}
                            className="space-y-5"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="name">Name</Label>
                                        <Input
                                            id="name"
                                            name="name"
                                            defaultValue={item.name}
                                            required
                                        />
                                        <InputError message={errors.name} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="sku">SKU</Label>
                                        <Input
                                            id="sku"
                                            name="sku"
                                            defaultValue={item.sku}
                                            required
                                        />
                                        <InputError message={errors.sku} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="base_unit_of_measure_id">
                                            Base unit
                                        </Label>

                                        <select
                                            id="base_unit_of_measure_id"
                                            name="base_unit_of_measure_id"
                                            defaultValue={
                                                item.baseUnitOfMeasure.id
                                            }
                                            className="h-9 rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                        >
                                            {units.map((unit) => (
                                                <option
                                                    key={unit.id}
                                                    value={unit.id}
                                                >
                                                    {unit.name} ({unit.symbol})
                                                </option>
                                            ))}
                                        </select>

                                        <InputError
                                            message={
                                                errors.base_unit_of_measure_id
                                            }
                                        />

                                        {item.unitConversions.length > 0 && (
                                            <p className="text-xs text-muted-foreground">
                                                The base unit is locked once an
                                                alternate unit has been
                                                configured.
                                            </p>
                                        )}
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="active">Status</Label>

                                        <select
                                            id="active"
                                            name="active"
                                            defaultValue={
                                                item.active ? '1' : '0'
                                            }
                                            className="h-9 rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                        >
                                            <option value="1">Active</option>
                                            <option value="0">Inactive</option>
                                        </select>

                                        <InputError message={errors.active} />
                                    </div>

                                    <Button type="submit" disabled={processing}>
                                        Save item
                                    </Button>
                                </>
                            )}
                        </Form>
                    </div>

                    <div className="space-y-6">
                        <div className="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                            <div className="border-b border-sidebar-border/70 px-5 py-4 dark:border-sidebar-border">
                                <h2 className="font-medium">Alternate units</h2>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Base: {item.baseUnitOfMeasure.symbol}
                                </p>
                            </div>

                            {item.unitConversions.length === 0 ? (
                                <div className="px-5 py-8 text-sm text-muted-foreground">
                                    No alternate units configured.
                                </div>
                            ) : (
                                <div className="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                                    {item.unitConversions.map((conversion) => (
                                        <div
                                            key={conversion.id}
                                            className="flex items-center justify-between gap-4 px-5 py-4"
                                        >
                                            <div>
                                                <p className="font-medium">
                                                    1{' '}
                                                    {
                                                        conversion.unitOfMeasure
                                                            .symbol
                                                    }{' '}
                                                    ={' '}
                                                    {
                                                        conversion.quantityInBaseUnit
                                                    }{' '}
                                                    {
                                                        item.baseUnitOfMeasure
                                                            .symbol
                                                    }
                                                </p>

                                                <p className="mt-1 text-sm text-muted-foreground">
                                                    {conversion.active
                                                        ? 'Active'
                                                        : 'Inactive'}
                                                </p>
                                            </div>

                                            <Button
                                                variant="outline"
                                                size="sm"
                                                asChild
                                            >
                                                <Link
                                                    href={InventoryItemUnitController.edit(
                                                        [
                                                            item.id,
                                                            conversion.id,
                                                        ],
                                                    )}
                                                >
                                                    Edit
                                                </Link>
                                            </Button>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>

                        {availableConversionUnits.length > 0 && (
                            <div className="rounded-xl border border-sidebar-border/70 p-5 dark:border-sidebar-border">
                                <h2 className="mb-5 font-medium">
                                    Add alternate unit
                                </h2>

                                <Form
                                    {...InventoryItemUnitController.store.form(
                                        item.id,
                                    )}
                                    className="space-y-5"
                                    resetOnSuccess
                                >
                                    {({ processing, errors }) => (
                                        <>
                                            <input
                                                type="hidden"
                                                name="active"
                                                value="1"
                                            />

                                            <div className="grid gap-2">
                                                <Label htmlFor="unit_of_measure_id">
                                                    Unit
                                                </Label>

                                                <select
                                                    id="unit_of_measure_id"
                                                    name="unit_of_measure_id"
                                                    defaultValue=""
                                                    required
                                                    className="h-9 rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                                >
                                                    <option value="" disabled>
                                                        Select unit
                                                    </option>

                                                    {availableConversionUnits.map(
                                                        (unit) => (
                                                            <option
                                                                key={unit.id}
                                                                value={unit.id}
                                                            >
                                                                {unit.name} (
                                                                {unit.symbol})
                                                            </option>
                                                        ),
                                                    )}
                                                </select>

                                                <InputError
                                                    message={
                                                        errors.unit_of_measure_id
                                                    }
                                                />
                                            </div>

                                            <div className="grid gap-2">
                                                <Label htmlFor="quantity_in_base_unit">
                                                    Quantity in base unit
                                                </Label>

                                                <Input
                                                    id="quantity_in_base_unit"
                                                    name="quantity_in_base_unit"
                                                    type="number"
                                                    min="0.000001"
                                                    step="0.000001"
                                                    required
                                                    placeholder="1000"
                                                />

                                                <p className="text-xs text-muted-foreground">
                                                    Number of{' '}
                                                    {
                                                        item.baseUnitOfMeasure
                                                            .symbol
                                                    }{' '}
                                                    contained in one selected
                                                    unit.
                                                </p>

                                                <InputError
                                                    message={
                                                        errors.quantity_in_base_unit
                                                    }
                                                />
                                            </div>

                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            >
                                                Add conversion
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}

EditInventoryItem.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
        {
            title: 'Inventory',
            href: InventoryItemController.index(),
        },
    ],
};
