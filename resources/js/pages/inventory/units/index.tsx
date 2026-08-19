import { Form, Head } from '@inertiajs/react';
import InventoryItemController from '@/actions/App/Http/Controllers/Inventory/InventoryItemController';
import UnitOfMeasureController from '@/actions/App/Http/Controllers/Inventory/UnitOfMeasureController';
import InputError from '@/components/input-error';
import { PreviousPageButton } from '@/components/navigation/previous-page-button';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useGuardedDialog } from '@/hooks/use-guarded-dialog';
import { dashboard } from '@/routes';
import type { UnitOfMeasureMasterData } from '@/types';

type Props = {
    units: UnitOfMeasureMasterData[];
    canManage: boolean;
};

type EditUnitOfMeasureDialogProps = {
    trigger: React.ReactNode;
    unit: UnitOfMeasureMasterData;
};

/** Edit one UOM without leaving the master-data list context. */
function EditUnitOfMeasureDialog({
    trigger,
    unit,
}: EditUnitOfMeasureDialogProps) {
    const dialog = useGuardedDialog(
        'Discard the unit of measure changes you entered?',
    );

    return (
        <Dialog open={dialog.open} onOpenChange={dialog.onOpenChange}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>

            <DialogContent className="max-h-[calc(100vh-2rem)] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Edit unit of measure</DialogTitle>
                    <DialogDescription>
                        {unit.name} ({unit.symbol})
                    </DialogDescription>
                </DialogHeader>

                <div onChange={dialog.markDirty}>
                    <Form
                        {...UnitOfMeasureController.update.form(unit.id)}
                        errorBag={`editUnitOfMeasure${unit.id}`}
                        className="space-y-5"
                        onSuccess={dialog.closeAfterSuccess}
                    >
                        {({ processing, errors }) => (
                            <>
                                <input type="hidden" name="_modal" value="1" />

                                <div className="grid gap-2">
                                    <Label htmlFor={`unit-name-${unit.id}`}>
                                        Name
                                    </Label>
                                    <Input
                                        id={`unit-name-${unit.id}`}
                                        name="name"
                                        defaultValue={unit.name}
                                        required
                                        autoFocus
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor={`unit-symbol-${unit.id}`}>
                                        Symbol
                                    </Label>
                                    <Input
                                        id={`unit-symbol-${unit.id}`}
                                        name="symbol"
                                        defaultValue={unit.symbol}
                                        required
                                    />
                                    <InputError message={errors.symbol} />
                                </div>

                                <div className="grid gap-2">
                                    <Label
                                        htmlFor={`unit-dimension-${unit.id}`}
                                    >
                                        Dimension
                                    </Label>

                                    <select
                                        id={`unit-dimension-${unit.id}`}
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
                                    <Label htmlFor={`unit-active-${unit.id}`}>
                                        Status
                                    </Label>

                                    <select
                                        id={`unit-active-${unit.id}`}
                                        name="active"
                                        defaultValue={unit.active ? '1' : '0'}
                                        className="h-9 rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                    >
                                        <option value="1">Active</option>
                                        <option value="0">Inactive</option>
                                    </select>

                                    <InputError message={errors.active} />
                                </div>

                                <p className="text-xs text-muted-foreground">
                                    Referenced units keep their symbol,
                                    dimension, and active-state protections from
                                    the existing inventory rules.
                                </p>

                                <div className="flex flex-wrap gap-2">
                                    <Button type="submit" disabled={processing}>
                                        {processing ? 'Saving...' : 'Save unit'}
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={processing}
                                        onClick={() =>
                                            dialog.onOpenChange(false)
                                        }
                                    >
                                        Cancel
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                </div>
            </DialogContent>
        </Dialog>
    );
}

export default function UnitsOfMeasureIndex({ units, canManage }: Props) {
    return (
        <>
            <Head title="Units of measure" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div>
                    <h1 className="text-2xl font-semibold">Units of measure</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Define the units available to inventory items.
                    </p>
                </div>

                <div
                    className={
                        canManage
                            ? 'grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px]'
                            : ''
                    }
                >
                    <div className="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                        <div className="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                            {units.length === 0 ? (
                                <div className="px-5 py-10 text-center text-sm text-muted-foreground">
                                    No units of measure have been created.
                                </div>
                            ) : (
                                units.map((unit) => (
                                    <div
                                        key={unit.id}
                                        className="flex items-center justify-between gap-4 px-5 py-4"
                                    >
                                        <div>
                                            <p className="font-medium">
                                                {unit.name}
                                            </p>
                                            <p className="mt-1 text-sm text-muted-foreground">
                                                {unit.symbol} · {unit.dimension}{' '}
                                                ·{' '}
                                                {unit.active
                                                    ? 'Active'
                                                    : 'Inactive'}
                                            </p>
                                        </div>

                                        {canManage && (
                                            <EditUnitOfMeasureDialog
                                                unit={unit}
                                                trigger={
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                    >
                                                        Edit
                                                    </Button>
                                                }
                                            />
                                        )}
                                    </div>
                                ))
                            )}
                        </div>
                    </div>

                    {canManage && (
                        <div className="h-fit rounded-xl border border-sidebar-border/70 p-5 dark:border-sidebar-border">
                            <h2 className="mb-5 font-medium">Create unit</h2>

                            <Form
                                {...UnitOfMeasureController.store.form()}
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
                                            <Label htmlFor="name">Name</Label>
                                            <Input
                                                id="name"
                                                name="name"
                                                required
                                                placeholder="Kilogram"
                                            />
                                            <InputError message={errors.name} />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="symbol">
                                                Symbol
                                            </Label>
                                            <Input
                                                id="symbol"
                                                name="symbol"
                                                required
                                                placeholder="kg"
                                            />
                                            <InputError
                                                message={errors.symbol}
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="dimension">
                                                Dimension
                                            </Label>

                                            <select
                                                id="dimension"
                                                name="dimension"
                                                defaultValue="count"
                                                className="h-9 rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                            >
                                                <option value="weight">
                                                    Weight
                                                </option>
                                                <option value="volume">
                                                    Volume
                                                </option>
                                                <option value="count">
                                                    Count
                                                </option>
                                            </select>

                                            <InputError
                                                message={errors.dimension}
                                            />
                                        </div>

                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            Create unit
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </div>
                    )}
                </div>

                <div>
                    <PreviousPageButton
                        variant="outline"
                        fallback={InventoryItemController.index().url}
                    >
                        Back to inventory
                    </PreviousPageButton>
                </div>
            </div>
        </>
    );
}

UnitsOfMeasureIndex.layout = {
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
