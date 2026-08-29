import { Form, Head, Link } from '@inertiajs/react';
import { Info, Pencil, Plus, Search } from 'lucide-react';
import InventoryItemController from '@/actions/App/Http/Controllers/Inventory/InventoryItemController';
import UnitOfMeasureController from '@/actions/App/Http/Controllers/Inventory/UnitOfMeasureController';
import { FilterToolbar } from '@/components/filter-toolbar';
import { PreviousPageButton } from '@/components/navigation/previous-page-button';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
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

/** Render active and inactive states using canonical semantic status tokens. */
function UnitOfMeasureStatus({ active }: { active: boolean }) {
    return (
        <StatusBadge
            label={active ? 'Active' : 'Inactive'}
            variant={active ? 'success' : 'neutral'}
        />
    );
}

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

                                <Field
                                    id="create-unit-name"
                                    label="Name"
                                    error={errors.name}
                                >
                                    <Input
                                        name="name"
                                        required
                                        autoFocus
                                        autoComplete="off"
                                        placeholder="Kilogram"
                                    />
                                </Field>

                                <Field
                                    id="create-unit-symbol"
                                    label="Symbol"
                                    error={errors.symbol}
                                >
                                    <Input
                                        name="symbol"
                                        required
                                        autoComplete="off"
                                        placeholder="kg"
                                    />
                                </Field>

                                <Field
                                    id="create-unit-dimension"
                                    label="Dimension"
                                    error={errors.dimension}
                                >
                                    <NativeSelect
                                        name="dimension"
                                        defaultValue="count"
                                        required
                                    >
                                        {dimensionOptions.map((dimension) => (
                                            <option
                                                key={dimension}
                                                value={dimension}
                                            >
                                                {dimensionLabels[dimension]}
                                            </option>
                                        ))}
                                    </NativeSelect>
                                </Field>

                                <Field
                                    id="create-unit-status"
                                    label="Status"
                                    error={errors.active}
                                >
                                    <NativeSelect
                                        name="active"
                                        defaultValue="1"
                                        required
                                    >
                                        <option value="1">Active</option>
                                        <option value="0">Inactive</option>
                                    </NativeSelect>
                                </Field>

                                <div className="flex gap-3 rounded-lg border border-border bg-muted/30 p-3 text-sm text-muted-foreground">
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
                                            ? 'Creating…'
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

                                <Field
                                    id={`unit-name-${unit.id}`}
                                    label="Name"
                                    error={errors.name}
                                >
                                    <Input
                                        name="name"
                                        defaultValue={unit.name}
                                        required
                                        autoFocus
                                    />
                                </Field>

                                <Field
                                    id={`unit-symbol-${unit.id}`}
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
                                    id={`unit-dimension-${unit.id}`}
                                    label="Dimension"
                                    error={errors.dimension}
                                >
                                    <NativeSelect
                                        name="dimension"
                                        defaultValue={unit.dimension}
                                        required
                                    >
                                        {dimensionOptions.map((dimension) => (
                                            <option
                                                key={dimension}
                                                value={dimension}
                                            >
                                                {dimensionLabels[dimension]}
                                            </option>
                                        ))}
                                    </NativeSelect>
                                </Field>

                                <Field
                                    id={`unit-active-${unit.id}`}
                                    label="Status"
                                    error={errors.active}
                                >
                                    <NativeSelect
                                        name="active"
                                        defaultValue={unit.active ? '1' : '0'}
                                        required
                                    >
                                        <option value="1">Active</option>
                                        <option value="0">Inactive</option>
                                    </NativeSelect>
                                </Field>

                                <div className="flex gap-3 rounded-lg border border-border bg-muted/30 p-3 text-xs text-muted-foreground">
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
                                        {processing ? 'Saving…' : 'Save unit'}
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
                <PageHeader
                    title="Units of measure"
                    description="Manage the units available to inventory items across weight, volume, and count."
                    actions={
                        canManage ? (
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
                        ) : undefined
                    }
                />

                <p className="flex flex-wrap items-center gap-x-2 text-sm text-muted-foreground">
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

                <section className="overflow-hidden rounded-xl border border-border bg-card">
                    <Form
                        action={UnitOfMeasureController.index().url}
                        method="get"
                    >
                        {({ processing }) => (
                            <FilterToolbar className="rounded-b-none border-x-0 border-t-0">
                                <div className="grid gap-3 md:grid-cols-2 lg:grid-cols-[minmax(16rem,1fr)_minmax(10rem,14rem)_minmax(10rem,13rem)_auto]">
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
                                            placeholder="Search by name or symbol…"
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

                                        <NativeSelect
                                            id="units-dimension"
                                            name="dimension"
                                            defaultValue={
                                                filters.dimension ?? ''
                                            }
                                        >
                                            <option value="">
                                                All dimensions
                                            </option>

                                            {dimensionOptions.map(
                                                (dimension) => (
                                                    <option
                                                        key={dimension}
                                                        value={dimension}
                                                    >
                                                        {
                                                            dimensionLabels[
                                                                dimension
                                                            ]
                                                        }
                                                    </option>
                                                ),
                                            )}
                                        </NativeSelect>
                                    </div>

                                    <div>
                                        <label
                                            htmlFor="units-status"
                                            className="sr-only"
                                        >
                                            Status
                                        </label>

                                        <NativeSelect
                                            id="units-status"
                                            name="status"
                                            defaultValue={filters.status ?? ''}
                                        >
                                            <option value="">
                                                All statuses
                                            </option>
                                            <option value="active">
                                                Active
                                            </option>
                                            <option value="inactive">
                                                Inactive
                                            </option>
                                        </NativeSelect>
                                    </div>

                                    <div className="flex flex-wrap items-center gap-2 md:col-span-2 lg:col-span-1 lg:justify-end">
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            {processing
                                                ? 'Applying…'
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
                            </FilterToolbar>
                        )}
                    </Form>

                    {units.length === 0 ? (
                        <div className="px-4 py-12 md:hidden">
                            <div className="mx-auto max-w-sm text-center">
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
                        </div>
                    ) : (
                        <div className="divide-y divide-border md:hidden">
                            {units.map((unit) => (
                                <article
                                    key={unit.id}
                                    className="space-y-4 p-4"
                                    aria-labelledby={`unit-${unit.id}-name`}
                                >
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="min-w-0">
                                            <h2
                                                id={`unit-${unit.id}-name`}
                                                className="font-medium"
                                            >
                                                {unit.name}
                                            </h2>
                                            <p className="mt-1 font-mono text-xs text-muted-foreground">
                                                {unit.symbol}
                                            </p>
                                        </div>

                                        <UnitOfMeasureStatus
                                            active={unit.active}
                                        />
                                    </div>

                                    <dl className="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                                        <div>
                                            <dt className="text-xs text-muted-foreground">
                                                Dimension
                                            </dt>
                                            <dd className="mt-0.5">
                                                {
                                                    dimensionLabels[
                                                        unit.dimension
                                                    ]
                                                }
                                            </dd>
                                        </div>

                                        <div>
                                            <dt className="text-xs text-muted-foreground">
                                                Used by
                                            </dt>
                                            <dd className="mt-0.5 tabular-nums">
                                                {unit.usageCount.toLocaleString()}{' '}
                                                {unit.usageCount === 1
                                                    ? 'item'
                                                    : 'items'}
                                            </dd>
                                        </div>

                                        <div>
                                            <dt className="text-xs text-muted-foreground">
                                                Updated
                                            </dt>
                                            <dd className="mt-0.5">
                                                {unit.updatedOn ?? '—'}
                                            </dd>
                                        </div>
                                    </dl>

                                    {canManage && (
                                        <div className="flex justify-end border-t border-border pt-3">
                                            <EditUnitOfMeasureDialog
                                                unit={unit}
                                                trigger={
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                    >
                                                        <Pencil
                                                            className="size-4"
                                                            aria-hidden="true"
                                                        />
                                                        Edit
                                                    </Button>
                                                }
                                            />
                                        </div>
                                    )}
                                </article>
                            ))}
                        </div>
                    )}

                    <div className="hidden overflow-x-auto md:block">
                        <table className="w-full min-w-[800px] text-sm">
                            <thead className="bg-muted/40 text-left text-xs text-muted-foreground">
                                <tr className="border-b border-border">
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
                                            className="border-b border-border transition-colors last:border-b-0 hover:bg-muted/30"
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
                                                <UnitOfMeasureStatus
                                                    active={unit.active}
                                                />
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

                    <div className="border-t border-border px-4 py-3 text-sm text-muted-foreground">
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
        {
            title: 'Units of measure',
            href: UnitOfMeasureController.index(),
        },
    ],
};
