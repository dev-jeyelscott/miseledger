import { Form, Head, Link } from '@inertiajs/react';
import InventoryItemController from '@/actions/App/Http/Controllers/Inventory/InventoryItemController';
import InventoryItemUnitController from '@/actions/App/Http/Controllers/Inventory/InventoryItemUnitController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import type { InventoryItemUnitData, UnitOfMeasureData } from '@/types';

type Props = {
    item: {
        id: number;
        name: string;
        sku: string;
        baseUnitOfMeasure: UnitOfMeasureData;
    };
    conversion: InventoryItemUnitData;
};

export default function EditInventoryItemUnit({ item, conversion }: Props) {
    return (
        <>
            <Head title={`${item.name} - ${conversion.unitOfMeasure.symbol}`} />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div>
                    <h1 className="text-2xl font-semibold">
                        Edit unit conversion
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {item.name}: 1 {conversion.unitOfMeasure.symbol} to{' '}
                        {item.baseUnitOfMeasure.symbol}
                    </p>
                </div>

                <div className="max-w-xl rounded-xl border border-sidebar-border/70 p-5 dark:border-sidebar-border">
                    <Form
                        {...InventoryItemUnitController.update.form([
                            item.id,
                            conversion.id,
                        ])}
                        className="space-y-5"
                    >
                        {({ processing, errors }) => (
                            <>
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
                                        defaultValue={
                                            conversion.quantityInBaseUnit
                                        }
                                        required
                                    />

                                    <p className="text-xs text-muted-foreground">
                                        1 {conversion.unitOfMeasure.symbol} ={' '}
                                        quantity ×{' '}
                                        {item.baseUnitOfMeasure.symbol}
                                    </p>

                                    <InputError
                                        message={errors.quantity_in_base_unit}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="active">Status</Label>

                                    <select
                                        id="active"
                                        name="active"
                                        defaultValue={
                                            conversion.active ? '1' : '0'
                                        }
                                        className="h-9 rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                    >
                                        <option value="1">Active</option>
                                        <option value="0">Inactive</option>
                                    </select>

                                    <InputError message={errors.active} />
                                </div>

                                <div className="flex gap-2">
                                    <Button type="submit" disabled={processing}>
                                        Save conversion
                                    </Button>

                                    <Button variant="outline" asChild>
                                        <Link
                                            href={InventoryItemController.edit(
                                                item.id,
                                            )}
                                        >
                                            Back
                                        </Link>
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                </div>
            </div>
        </>
    );
}

EditInventoryItemUnit.layout = {
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
