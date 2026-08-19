import { Form, Head } from '@inertiajs/react';
import InventoryItemController from '@/actions/App/Http/Controllers/Inventory/InventoryItemController';
import UnitOfMeasureController from '@/actions/App/Http/Controllers/Inventory/UnitOfMeasureController';
import InputError from '@/components/input-error';
import { PreviousPageButton } from '@/components/navigation/previous-page-button';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
                <div>
                    <h1 className="text-2xl font-semibold">
                        Edit unit of measure
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {unit.name} ({unit.symbol})
                    </p>
                </div>

                <div className="max-w-xl rounded-xl border border-sidebar-border/70 p-5 dark:border-sidebar-border">
                    <Form
                        {...UnitOfMeasureController.update.form(unit.id)}
                        className="space-y-5"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Name</Label>
                                    <Input
                                        id="name"
                                        name="name"
                                        defaultValue={unit.name}
                                        required
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="symbol">Symbol</Label>
                                    <Input
                                        id="symbol"
                                        name="symbol"
                                        defaultValue={unit.symbol}
                                        required
                                    />
                                    <InputError message={errors.symbol} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="dimension">Dimension</Label>

                                    <select
                                        id="dimension"
                                        name="dimension"
                                        defaultValue={unit.dimension}
                                        className="h-9 rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                    >
                                        <option value="weight">Weight</option>
                                        <option value="volume">Volume</option>
                                        <option value="count">Count</option>
                                    </select>

                                    <InputError message={errors.dimension} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="active">Status</Label>

                                    <select
                                        id="active"
                                        name="active"
                                        defaultValue={unit.active ? '1' : '0'}
                                        className="h-9 rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                    >
                                        <option value="1">Active</option>
                                        <option value="0">Inactive</option>
                                    </select>

                                    <InputError message={errors.active} />
                                </div>

                                <div className="flex gap-2">
                                    <Button type="submit" disabled={processing}>
                                        Save unit
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
