import { Form, Head, Link } from '@inertiajs/react';
import { Info, Pencil, Plus, Search } from 'lucide-react';
import InventoryItemController from '@/actions/App/Http/Controllers/Inventory/InventoryItemController';
import UnitOfMeasureController from '@/actions/App/Http/Controllers/Inventory/UnitOfMeasureController';
import InputError from '@/components/input-error';
import { PreviousPageButton } from '@/components/navigation/previous-page-button';
import { Badge } from '@/components/ui/badge';
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
import type { UnitDimension, UnitOfMeasureMasterData } from '@/types';

type UnitStatus = 'active' | 'inactive';

type UnitOfMeasureListItem = UnitOfMeasureMasterData & {
    usageCount: number;
    updatedOn: string | null;
};

type Filters = {
    search: string;
    dimension: UnitDimension | null;
    status: UnitStatus | null;
};

type Props = {
    units: UnitOfMeasureListItem[];
    summary: {
        total: number;
        active: number;
        dimensions: number;
    };
    filters: Filters;
    canManage: boolean;
};

type CreateUnitOfMeasureDialogProps = {
    trigger: React.ReactNode;
};

type EditUnitOfMeasureDialogProps = {
    trigger: React.ReactNode;
    unit: UnitOfMeasureListItem;
};

const dimensionOptions: UnitDimension[] = ['weight', 'volume', 'count'];

const dimensionLabels: Record<UnitDimension, string> = {
    weight: 'Weight',
    volume: 'Volume',
    count: 'Count',
};

const selectClassName =
    'h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs outline-none transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50';

/** Create a UOM without leaving the current master-data context. */
function CreateUnitOfMeasureDialog({
    trigger,
}: CreateUnitOfMeasureDialogProps) {
    const dialog = useGuardedDialog(
        'Discard the unit of measure details you entered?',
    );

    return (
        <Dialog open={dialog.open} onOpenChange={dialog.onOpenChange}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>

            <DialogContent className="max-h-[calc(100vh-2rem)] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Create unit</DialogTitle>
                    <DialogDescription>
                        Add a new unit of measure to the active organization.
                    </DialogDescription>
                </DialogHeader>

                <div onChange={dialog.markDirty}>
                    <Form
                        {...UnitOfMeasureController.store.form()}
                        errorBag="createUnitOfMeasure"
                        className="space-y-5"
                        resetOnSuccess
                        onSuccess={dialog.closeAfterSuccess}
                    >
                        {({ processing, errors }) => (
                            <>
                                <input type="hidden" name="_modal" value="1" />

                                <div className="grid gap-2">
                                    <Label htmlFor="create-unit-name">
                                        Name
                                    </Label>
                                    <Input
                                        id="create-unit-name"
                                        name="name"
                                        required
                                        autoFocus
                                        autoComplete="off"
                                        aria-invalid={Boolean(errors.name)}
                                        placeholder="Kilogram"
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="create-unit-symbol">
                                        Symbol
                                    </Label>
                                    <Input
                                        id="create-unit-symbol"
                                        name="symbol"
                                        required
                                        autoComplete="off"
                                        aria-invalid={Boolean(errors.symbol)}
                                        placeholder="kg"
                                    />
                                    <InputError message={errors.symbol} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="create-unit-dimension">
                                        Dimension
                                    </Label>

                                    <select
                                        id="create-unit-dimension"
                                        name="dimension"
                                        defaultValue="count"
                                        required
                                        aria-invalid={Boolean(errors.dimension)}
                                        className={selectClassName}
                                    >
                                        {dimensionOptions.map((dimension) => (
                                            <option
                                                key={dimension}
                                                value={dimension}
                                            >
                                                {dimensionLabels[dimension]}
                                            </option>
                                        ))}
                                    </select>

                                    <InputError message={errors.dimension} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="create-unit-status">
                                        Status
                                    </Label>

                                    <select
                                        id="create-unit-status"
                                        name="active"
                                        defaultValue="1"
                                        required
                                        aria-invalid={Boolean(errors.active)}
                                        className={selectClassName}
                                    >
                                        <option value="1">Active</option>
                                        <option value="0">Inactive</option>
                                    </select>

                                    <InputError message={errors.active} />
                                </div>

                                <div className="flex gap-3 rounded-lg border border-sidebar-border/70 bg-muted/30 p-3 text-sm text-muted-foreground dark:border-sidebar-border">
                                    <Info
                                        className="mt-0.5 size-4 shrink-0"
                                        aria-hidden="true"
                                    />

                                    <p>
                                        Dimensions organize units as weight,
                                        volume, or count. Pack and
                                        cross-dimension relationships remain
                                        item-specific.
                                    </p>
                                </div>

                                <div className="flex flex-wrap justify-end gap-2">
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

                                    <Button type="submit" disabled={processing}>
                                        {processing
                                            ? 'Creating...'
                                            : 'Create unit'}
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
                                        aria-invalid={Boolean(errors.name)}
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
                                        aria-invalid={Boolean(errors.symbol)}
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
                                        required
                                        aria-invalid={Boolean(errors.dimension)}
                                        className={selectClassName}
                                    >
                                        {dimensionOptions.map((dimension) => (
                                            <option
                                                key={dimension}
                                                value={dimension}
                                            >
                                                {dimensionLabels[dimension]}
                                            </option>
                                        ))}
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
                                        required
                                        aria-invalid={Boolean(errors.active)}
                                        className={selectClassName}
                                    >
                                        <option value="1">Active</option>
                                        <option value="0">Inactive</option>
                                    </select>

                                    <InputError message={errors.active} />
                                </div>

                                <div className="flex gap-3 rounded-lg border border-sidebar-border/70 bg-muted/30 p-3 text-xs text-muted-foreground dark:border-sidebar-border">
                                    <Info
                                        className="mt-0.5 size-4 shrink-0"
                                        aria-hidden="true"
                                    />

                                    <p>
                                        {unit.usageCount > 0
                                            ? `This unit is referenced by ${unit.usageCount.toLocaleString()} item configuration${unit.usageCount === 1 ? '' : 's'}. Existing inventory rules protect referenced symbols, dimensions, and active state.`
                                            : 'Changes remain subject to standard unit and inventory-integrity validation.'}
                                    </p>
                                </div>

                                <div className="flex flex-wrap justify-end gap-2">
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

                                    <Button type="submit" disabled={processing}>
                                        {processing ? 'Saving...' : 'Save unit'}
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

export default function UnitsOfMeasureIndex({
    units,
    summary,
    filters,
    canManage,
}: Props) {
    const hasQueryState =
        filters.search !== '' ||
        filters.dimension !== null ||
        filters.status !== null;

    return (
        <>
            <Head title="Units of measure" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col justify-between gap-4 lg:flex-row lg:items-start">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Units of measure
                        </h1>

                        <p className="mt-1 text-sm text-muted-foreground">
                            Manage the units available to inventory items across
                            weight, volume, and count.
                        </p>

                        <p className="mt-3 flex flex-wrap items-center gap-x-2 text-sm text-muted-foreground">
                            <span>
                                <span className="font-medium text-foreground">
                                    {summary.total.toLocaleString()}
                                </span>{' '}
                                units
                            </span>

                            <span aria-hidden="true">·</span>

                            <span>
                                <span className="font-medium text-foreground">
                                    {summary.dimensions.toLocaleString()}
                                </span>{' '}
                                dimensions
                            </span>

                            <span aria-hidden="true">·</span>

                            <span>
                                <span className="font-medium text-foreground">
                                    {summary.active.toLocaleString()}
                                </span>{' '}
                                active
                            </span>
                        </p>
                    </div>

                    {canManage && (
                        <CreateUnitOfMeasureDialog
                            trigger={
                                <Button>
                                    <Plus
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    New unit
                                </Button>
                            }
                        />
                    )}
                </div>

                <section className="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border">
                    <Form
                        action={UnitOfMeasureController.index().url}
                        method="get"
                    >
                        {({ processing }) => (
                            <div className="grid gap-3 border-b border-sidebar-border/70 p-4 md:grid-cols-2 lg:grid-cols-[minmax(16rem,1fr)_minmax(10rem,14rem)_minmax(10rem,13rem)_auto] dark:border-sidebar-border">
                                <div className="relative md:col-span-2 lg:col-span-1">
                                    <label
                                        htmlFor="units-search"
                                        className="sr-only"
                                    >
                                        Search units of measure
                                    </label>

                                    <Search
                                        className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                        aria-hidden="true"
                                    />

                                    <Input
                                        id="units-search"
                                        name="search"
                                        type="search"
                                        defaultValue={filters.search}
                                        placeholder="Search by name or symbol..."
                                        className="pl-9"
                                    />
                                </div>

                                <div>
                                    <label
                                        htmlFor="units-dimension"
                                        className="sr-only"
                                    >
                                        Dimension
                                    </label>

                                    <select
                                        id="units-dimension"
                                        name="dimension"
                                        defaultValue={filters.dimension ?? ''}
                                        className={selectClassName}
                                    >
                                        <option value="">All dimensions</option>

                                        {dimensionOptions.map((dimension) => (
                                            <option
                                                key={dimension}
                                                value={dimension}
                                            >
                                                {dimensionLabels[dimension]}
                                            </option>
                                        ))}
                                    </select>
                                </div>

                                <div>
                                    <label
                                        htmlFor="units-status"
                                        className="sr-only"
                                    >
                                        Status
                                    </label>

                                    <select
                                        id="units-status"
                                        name="status"
                                        defaultValue={filters.status ?? ''}
                                        className={selectClassName}
                                    >
                                        <option value="">All statuses</option>
                                        <option value="active">Active</option>
                                        <option value="inactive">
                                            Inactive
                                        </option>
                                    </select>
                                </div>

                                <div className="flex flex-wrap items-center gap-2 md:col-span-2 lg:col-span-1 lg:justify-end">
                                    <Button type="submit" disabled={processing}>
                                        {processing
                                            ? 'Applying...'
                                            : 'Apply filters'}
                                    </Button>

                                    {hasQueryState && (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            asChild
                                        >
                                            <Link
                                                href={UnitOfMeasureController.index()}
                                            >
                                                Reset
                                            </Link>
                                        </Button>
                                    )}
                                </div>
                            </div>
                        )}
                    </Form>

                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[800px] text-sm">
                            <thead className="bg-muted/40 text-left text-xs text-muted-foreground">
                                <tr className="border-b border-sidebar-border/70 dark:border-sidebar-border">
                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-medium"
                                    >
                                        Name
                                    </th>

                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-medium"
                                    >
                                        Symbol
                                    </th>

                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-medium"
                                    >
                                        Dimension
                                    </th>

                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-medium"
                                    >
                                        Status
                                    </th>

                                    <th
                                        scope="col"
                                        className="px-4 py-3 text-right font-medium"
                                    >
                                        Used by
                                    </th>

                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-medium"
                                    >
                                        Updated
                                    </th>

                                    {canManage && (
                                        <th
                                            scope="col"
                                            className="w-20 px-4 py-3 text-right"
                                        >
                                            <span className="sr-only">
                                                Actions
                                            </span>
                                        </th>
                                    )}
                                </tr>
                            </thead>

                            <tbody>
                                {units.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={canManage ? 7 : 6}
                                            className="px-4 py-12 text-center"
                                        >
                                            <div className="mx-auto max-w-sm">
                                                <p className="font-medium">
                                                    {hasQueryState
                                                        ? 'No units match these filters.'
                                                        : 'No units of measure have been configured.'}
                                                </p>

                                                <p className="mt-1 text-sm text-muted-foreground">
                                                    {hasQueryState
                                                        ? 'Adjust or reset the filters to see more units.'
                                                        : canManage
                                                          ? 'Create a unit to make it available to inventory configuration.'
                                                          : 'No units are currently available for this organization.'}
                                                </p>
                                            </div>
                                        </td>
                                    </tr>
                                ) : (
                                    units.map((unit) => (
                                        <tr
                                            key={unit.id}
                                            className="border-b border-sidebar-border/70 transition-colors last:border-b-0 hover:bg-muted/30 dark:border-sidebar-border"
                                        >
                                            <td className="px-4 py-3 font-medium">
                                                {unit.name}
                                            </td>

                                            <td className="px-4 py-3 font-mono text-xs text-muted-foreground">
                                                {unit.symbol}
                                            </td>

                                            <td className="px-4 py-3">
                                                {
                                                    dimensionLabels[
                                                        unit.dimension
                                                    ]
                                                }
                                            </td>

                                            <td className="px-4 py-3">
                                                <Badge
                                                    variant={
                                                        unit.active
                                                            ? 'secondary'
                                                            : 'outline'
                                                    }
                                                >
                                                    {unit.active
                                                        ? 'Active'
                                                        : 'Inactive'}
                                                </Badge>
                                            </td>

                                            <td className="px-4 py-3 text-right tabular-nums">
                                                {unit.usageCount.toLocaleString()}{' '}
                                                {unit.usageCount === 1
                                                    ? 'item'
                                                    : 'items'}
                                            </td>

                                            <td className="px-4 py-3 text-muted-foreground">
                                                {unit.updatedOn ?? '—'}
                                            </td>

                                            {canManage && (
                                                <td className="px-4 py-2 text-right">
                                                    <EditUnitOfMeasureDialog
                                                        unit={unit}
                                                        trigger={
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="icon"
                                                                aria-label={`Edit ${unit.name}`}
                                                            >
                                                                <Pencil
                                                                    className="size-4"
                                                                    aria-hidden="true"
                                                                />
                                                            </Button>
                                                        }
                                                    />
                                                </td>
                                            )}
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    <div className="border-t border-sidebar-border/70 px-4 py-3 text-sm text-muted-foreground dark:border-sidebar-border">
                        {hasQueryState ? (
                            <>
                                Showing {units.length.toLocaleString()} matching
                                units from {summary.total.toLocaleString()}{' '}
                                total
                            </>
                        ) : (
                            <>
                                Showing {units.length.toLocaleString()} of{' '}
                                {summary.total.toLocaleString()} units
                            </>
                        )}
                    </div>
                </section>

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
