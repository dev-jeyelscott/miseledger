import { Form, Head } from '@inertiajs/react';
import InventoryItemController from '@/actions/App/Http/Controllers/Inventory/InventoryItemController';
import InventoryItemUnitController from '@/actions/App/Http/Controllers/Inventory/InventoryItemUnitController';
import { PreviousPageButton } from '@/components/navigation/previous-page-button';
import { Button } from '@/components/ui/button';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
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

                <div className="max-w-xl rounded-xl border border-border bg-card p-5">
                    <Form
                        {...InventoryItemUnitController.update.form([
                            item.id,
                            conversion.id,
                        ])}
                        className="space-y-5"
                    >
                        {({ processing, errors }) => (
                            <>
                                <Field
                                    id="quantity_in_base_unit"
                                    label="Quantity in base unit"
                                    error={errors.quantity_in_base_unit}
                                    helper={
                                        <>
                                            1 {conversion.unitOfMeasure.symbol}{' '}
                                            = quantity ×{' '}
                                            {item.baseUnitOfMeasure.symbol}
                                        </>
                                    }
                                >
                                    <Input
                                        name="quantity_in_base_unit"
                                        type="number"
                                        min="0.000001"
                                        step="0.000001"
                                        defaultValue={
                                            conversion.quantityInBaseUnit
                                        }
                                        required
                                    />
                                </Field>

                                <Field
                                    id="active"
                                    label="Status"
                                    error={errors.active}
                                >
                                    <NativeSelect
                                        name="active"
                                        defaultValue={
                                            conversion.active ? '1' : '0'
                                        }
                                    >
                                        <option value="1">Active</option>
                                        <option value="0">Inactive</option>
                                    </NativeSelect>
                                </Field>

                                <div className="flex flex-wrap gap-2">
                                    <Button type="submit" disabled={processing}>
                                        {processing
                                            ? 'Saving…'
                                            : 'Save conversion'}
                                    </Button>

                                    <PreviousPageButton
                                        variant="outline"
                                        fallback={
                                            InventoryItemController.edit(
                                                item.id,
                                            ).url
                                        }
                                        disabled={processing}
                                    >
                                        Back
                                    </PreviousPageButton>
                                </div>
                            </>
                        )}
                    </Form>
                </div>
            </div>
        </>
    );
}

EditInventoryItemUnit.layout = (page: Props) => ({
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        {
            title: 'Inventory items',
            href: InventoryItemController.index(),
        },
        {
            title: page.item.name,
            href: InventoryItemController.edit(page.item.id),
        },
        {
            title: 'Edit unit conversion',
            href: InventoryItemUnitController.edit([
                page.item.id,
                page.conversion.id,
            ]),
        },
    ],
});
