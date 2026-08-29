import { Form, Head } from '@inertiajs/react';
import InventoryItemController from '@/actions/App/Http/Controllers/Inventory/InventoryItemController';
import UnitOfMeasureController from '@/actions/App/Http/Controllers/Inventory/UnitOfMeasureController';
import { PreviousPageButton } from '@/components/navigation/previous-page-button';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { dashboard } from '@/routes';
import type { UnitOfMeasureMasterData } from '@/types';

type Props = {
    unit: UnitOfMeasureMasterData;
};

export default function EditUnitOfMeasure({ unit }: Props) {
    return (
        <>
            <Head title={`Edit ${unit.name}`} />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <PageHeader
                    title="Edit unit of measure"
                    description={
                        <>
                            {unit.name} ({unit.symbol})
                        </>
                    }
                />

                <div className="max-w-xl rounded-xl border border-border bg-card p-5">
                    <Form
                        {...UnitOfMeasureController.update.form(unit.id)}
                        className="space-y-5"
                    >
                        {({ processing, errors }) => (
                            <>
                                <Field
                                    id="name"
                                    label="Name"
                                    error={errors.name}
                                >
                                    <Input
                                        name="name"
                                        defaultValue={unit.name}
                                        required
                                    />
                                </Field>

                                <Field
                                    id="symbol"
                                    label="Symbol"
                                    error={errors.symbol}
                                >
                                    <Input
                                        name="symbol"
                                        defaultValue={unit.symbol}
                                        required
                                    />
                                </Field>

                                <Field
                                    id="dimension"
                                    label="Dimension"
                                    error={errors.dimension}
                                >
                                    <NativeSelect
                                        name="dimension"
                                        defaultValue={unit.dimension}
                                    >
                                        <option value="weight">Weight</option>
                                        <option value="volume">Volume</option>
                                        <option value="count">Count</option>
                                    </NativeSelect>
                                </Field>

                                <Field
                                    id="active"
                                    label="Status"
                                    error={errors.active}
                                >
                                    <NativeSelect
                                        name="active"
                                        defaultValue={unit.active ? '1' : '0'}
                                    >
                                        <option value="1">Active</option>
                                        <option value="0">Inactive</option>
                                    </NativeSelect>
                                </Field>

                                <div className="flex gap-2">
                                    <Button type="submit" disabled={processing}>
                                        {processing ? 'Saving…' : 'Save unit'}
                                    </Button>

                                    <PreviousPageButton
                                        variant="outline"
                                        fallback={
                                            UnitOfMeasureController.index().url
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

EditUnitOfMeasure.layout = {
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
            title: 'Units',
            href: UnitOfMeasureController.index(),
        },
    ],
};
