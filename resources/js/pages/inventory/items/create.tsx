import { Form, Head, Link } from '@inertiajs/react';
import InventoryItemController from '@/actions/App/Http/Controllers/Inventory/InventoryItemController';
import UnitOfMeasureController from '@/actions/App/Http/Controllers/Inventory/UnitOfMeasureController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import type { UnitOfMeasureData } from '@/types';

type Props = {
    units: UnitOfMeasureData[];
};

export default function CreateInventoryItem({ units }: Props) {
    return (
        <>
            <Head title="Create inventory item" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div>
                    <h1 className="text-2xl font-semibold">
                        Create inventory item
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Select the unit in which stock will be stored.
                    </p>
                </div>

                {units.length === 0 ? (
                    <div className="max-w-xl rounded-xl border border-sidebar-border/70 p-5 dark:border-sidebar-border">
                        <p className="text-sm text-muted-foreground">
                            Create at least one active unit of measure before
                            creating an inventory item.
                        </p>

                        <Button className="mt-4" asChild>
                            <Link href={UnitOfMeasureController.index()}>
                                Manage units
                            </Link>
                        </Button>
                    </div>
                ) : (
                    <div className="max-w-xl rounded-xl border border-sidebar-border/70 p-5 dark:border-sidebar-border">
                        <Form
                            {...InventoryItemController.store.form()}
                            className="space-y-5"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <input
                                        type="hidden"
                                        name="active"
                                        value="1"
                                    />

                                    <div className="grid gap-2">
                                        <Label htmlFor="name">Name</Label>
                                        <Input
                                            id="name"
                                            name="name"
                                            required
                                            autoFocus
                                            placeholder="All-purpose flour"
                                        />
                                        <InputError message={errors.name} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="sku">SKU</Label>
                                        <Input
                                            id="sku"
                                            name="sku"
                                            required
                                            autoComplete="off"
                                            placeholder="FLOUR-001"
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
                                            required
                                            defaultValue=""
                                            className="h-9 rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                        >
                                            <option value="" disabled>
                                                Select unit
                                            </option>

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
                                    </div>

                                    <div className="flex gap-2">
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            Create item
                                        </Button>

                                        <Button variant="outline" asChild>
                                            <Link
                                                href={InventoryItemController.index()}
                                            >
                                                Cancel
                                            </Link>
                                        </Button>
                                    </div>
                                </>
                            )}
                        </Form>
                    </div>
                )}
            </div>
        </>
    );
}

CreateInventoryItem.layout = {
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
