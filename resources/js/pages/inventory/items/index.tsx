import { Form, Head, Link } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    Award,
    Boxes,
    CheckCircle2,
    Pencil,
    Plus,
    Ruler,
    Scale,
    Search,
    SlidersHorizontal,
    Tags,
} from 'lucide-react';
import InventoryAdjustmentController from '@/actions/App/Http/Controllers/Inventory/InventoryAdjustmentController';
import InventoryBrandController from '@/actions/App/Http/Controllers/Inventory/InventoryBrandController';
import InventoryCategoryController from '@/actions/App/Http/Controllers/Inventory/InventoryCategoryController';
import InventoryItemController from '@/actions/App/Http/Controllers/Inventory/InventoryItemController';
import OpeningBalanceController from '@/actions/App/Http/Controllers/Inventory/OpeningBalanceController';
import UnitOfMeasureController from '@/actions/App/Http/Controllers/Inventory/UnitOfMeasureController';
import InputError from '@/components/input-error';
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
import type {
    InventoryItemListItem,
    InventoryItemType,
    UnitOfMeasureData,
} from '@/types';

type ItemStatus = 'active' | 'inactive';

type SortKey = 'name' | 'sku' | 'type' | 'status';

type SortDirection = 'asc' | 'desc';

type InventoryItemsPagination = {
    current_page: number;
    from: number | null;
    last_page: number;
    next_page_url: string | null;
    per_page: number;
    prev_page_url: string | null;
    to: number | null;
    total: number;
};

type CategoryOption = {
    id: number;
    name: string;
    active: boolean;
};

type Filters = {
    search: string;
    categoryId: number | null;
    type: InventoryItemType | null;
    status: ItemStatus | null;
    sort: SortKey | null;
    direction: SortDirection;
};

type Props = {
    items: InventoryItemListItem[];
    pagination: InventoryItemsPagination;
    summary: {
        total: number;
        active: number;
    };
    categoryOptions: CategoryOption[];
    createUnitOptions: UnitOfMeasureData[];
    filters: Filters;
    canManage: boolean;
};

type SortableHeadingProps = {
    active: boolean;
    children: React.ReactNode;
    direction: SortDirection;
    href: string;
};

type CreateInventoryItemDialogProps = {
    categories: CategoryOption[];
    trigger: React.ReactNode;
    units: UnitOfMeasureData[];
};

const itemTypeLabels: Record<InventoryItemType, string> = {
    ingredient: 'Ingredient',
    finished_item: 'Finished item',
    prepared_item: 'Prepared item',
    packaging: 'Packaging',
    consumable: 'Consumable',
};

const itemTypeOptions: InventoryItemType[] = [
    'ingredient',
    'finished_item',
    'prepared_item',
    'packaging',
    'consumable',
];

/**
 * Render a stable sortable table heading without recreating components per render.
 */
function SortableHeading({
    active,
    children,
    direction,
    href,
}: SortableHeadingProps) {
    return (
        <Link
            href={href}
            preserveScroll
            className="inline-flex items-center gap-1.5 font-medium text-foreground transition-colors hover:text-foreground/70 focus-visible:rounded-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
        >
            {children}

            {active ? (
                direction === 'asc' ? (
                    <ArrowUp className="size-3.5" aria-hidden="true" />
                ) : (
                    <ArrowDown className="size-3.5" aria-hidden="true" />
                )
            ) : (
                <ArrowUpDown
                    className="size-3.5 text-muted-foreground"
                    aria-hidden="true"
                />
            )}
        </Link>
    );
}

/** Create a compact inventory master record without leaving index context. */
function CreateInventoryItemDialog({
    categories,
    trigger,
    units,
}: CreateInventoryItemDialogProps) {
    const dialog = useGuardedDialog(
        'Discard the inventory item details you entered?',
    );
    const activeCategories = categories.filter((category) => category.active);

    return (
        <Dialog open={dialog.open} onOpenChange={dialog.onOpenChange}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>

            <DialogContent className="max-h-[calc(100vh-2rem)] overflow-y-auto sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>Create inventory item</DialogTitle>
                    <DialogDescription>
                        Create the master record and select the base unit used
                        to store stock.
                    </DialogDescription>
                </DialogHeader>

                {units.length === 0 ? (
                    <div className="space-y-4">
                        <p className="text-sm text-muted-foreground">
                            Create at least one active unit of measure before
                            creating an inventory item.
                        </p>

                        <Button asChild>
                            <Link href={UnitOfMeasureController.index()}>
                                Manage units
                            </Link>
                        </Button>
                    </div>
                ) : (
                    <div onChange={dialog.markDirty}>
                        <Form
                            {...InventoryItemController.store.form()}
                            errorBag="createInventoryItem"
                            className="space-y-5"
                            resetOnSuccess
                            onSuccess={dialog.closeAfterSuccess}
                        >
                            {({ processing, errors }) => (
                                <>
                                    <input
                                        type="hidden"
                                        name="_modal"
                                        value="1"
                                    />
                                    <input
                                        type="hidden"
                                        name="active"
                                        value="1"
                                    />

                                    <div className="grid gap-2">
                                        <Label htmlFor="modal-item-name">
                                            Name
                                        </Label>
                                        <Input
                                            id="modal-item-name"
                                            name="name"
                                            required
                                            autoFocus
                                            placeholder="All-purpose flour"
                                        />
                                        <InputError message={errors.name} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="modal-item-sku">
                                            SKU
                                        </Label>
                                        <Input
                                            id="modal-item-sku"
                                            name="sku"
                                            required
                                            autoComplete="off"
                                            placeholder="FLOUR-001"
                                        />
                                        <InputError message={errors.sku} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="modal-item-type">
                                            Item type
                                        </Label>
                                        <select
                                            id="modal-item-type"
                                            name="type"
                                            defaultValue="ingredient"
                                            required
                                            className="h-9 rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                        >
                                            {itemTypeOptions.map((type) => (
                                                <option key={type} value={type}>
                                                    {itemTypeLabels[type]}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError message={errors.type} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="modal-item-category">
                                            Category
                                        </Label>
                                        <select
                                            id="modal-item-category"
                                            name="inventory_category_id"
                                            defaultValue=""
                                            className="h-9 rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                        >
                                            <option value="">
                                                Uncategorized
                                            </option>
                                            {activeCategories.map(
                                                (category) => (
                                                    <option
                                                        key={category.id}
                                                        value={category.id}
                                                    >
                                                        {category.name}
                                                    </option>
                                                ),
                                            )}
                                        </select>
                                        <InputError
                                            message={
                                                errors.inventory_category_id
                                            }
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="modal-item-yield">
                                            Yield (%)
                                        </Label>
                                        <Input
                                            id="modal-item-yield"
                                            name="yield_percentage"
                                            type="number"
                                            min="0"
                                            max="100"
                                            step="0.01"
                                            defaultValue="100.00"
                                            required
                                        />
                                        <InputError
                                            message={errors.yield_percentage}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="modal-item-base-unit">
                                            Base unit
                                        </Label>
                                        <select
                                            id="modal-item-base-unit"
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

                                    <div className="flex flex-wrap gap-2">
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            {processing
                                                ? 'Creating...'
                                                : 'Create item'}
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
                )}
            </DialogContent>
        </Dialog>
    );
}

export default function InventoryItemsIndex({
    items,
    pagination,
    summary,
    categoryOptions,
    createUnitOptions,
    filters,
    canManage,
}: Props) {
    const hasQueryState =
        filters.search !== '' ||
        filters.categoryId !== null ||
        filters.type !== null ||
        filters.status !== null ||
        filters.sort !== null;

    /**
     * Preserve active filters while changing only the requested sort.
     */
    const sortUrl = (sort: SortKey): string => {
        const direction: SortDirection =
            filters.sort === sort && filters.direction === 'asc'
                ? 'desc'
                : 'asc';

        return InventoryItemController.index({
            query: {
                search: filters.search || undefined,
                category: filters.categoryId ?? undefined,
                type: filters.type ?? undefined,
                status: filters.status ?? undefined,
                sort,
                direction,
            },
        }).url;
    };

    return (
        <>
            <Head title="Inventory items" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col justify-between gap-4 xl:flex-row xl:items-start">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Inventory items
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Manage inventory master records and their base
                            units.
                        </p>
                    </div>

                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" asChild>
                            <Link href={UnitOfMeasureController.index()}>
                                <Ruler className="size-4" aria-hidden="true" />
                                Units of measure
                            </Link>
                        </Button>

                        <Button variant="outline" asChild>
                            <Link href={InventoryCategoryController.index()}>
                                <Tags className="size-4" aria-hidden="true" />
                                Categories
                            </Link>
                        </Button>

                        <Button variant="outline" asChild>
                            <Link href={InventoryBrandController.index()}>
                                <Award className="size-4" aria-hidden="true" />
                                Brands
                            </Link>
                        </Button>

                        {canManage && (
                            <>
                                <Button variant="outline" asChild>
                                    <Link
                                        href={OpeningBalanceController.create()}
                                    >
                                        <Scale
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        Opening balance
                                    </Link>
                                </Button>

                                <Button variant="outline" asChild>
                                    <Link
                                        href={InventoryAdjustmentController.create()}
                                    >
                                        <SlidersHorizontal
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        Adjust inventory
                                    </Link>
                                </Button>

                                <CreateInventoryItemDialog
                                    categories={categoryOptions}
                                    units={createUnitOptions}
                                    trigger={
                                        <Button>
                                            <Plus
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                            New item
                                        </Button>
                                    }
                                />
                            </>
                        )}
                    </div>
                </div>

                <section
                    aria-label="Inventory summary"
                    className="grid gap-3 sm:grid-cols-2 lg:max-w-2xl"
                >
                    <div className="flex items-center justify-between gap-4 rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                        <div>
                            <p className="text-sm font-medium text-muted-foreground">
                                Total items
                            </p>
                            <p className="mt-1 text-2xl font-semibold tracking-tight">
                                {summary.total.toLocaleString()}
                            </p>
                            <p className="mt-1 text-xs text-muted-foreground">
                                All inventory master records
                            </p>
                        </div>

                        <div className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-muted">
                            <Boxes className="size-5" aria-hidden="true" />
                        </div>
                    </div>

                    <div className="flex items-center justify-between gap-4 rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                        <div>
                            <p className="text-sm font-medium text-muted-foreground">
                                Active items
                            </p>
                            <p className="mt-1 text-2xl font-semibold tracking-tight">
                                {summary.active.toLocaleString()}
                            </p>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Available for current operations
                            </p>
                        </div>

                        <div className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-muted">
                            <CheckCircle2
                                className="size-5"
                                aria-hidden="true"
                            />
                        </div>
                    </div>
                </section>

                <section className="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border">
                    <Form
                        action={InventoryItemController.index().url}
                        method="get"
                    >
                        {({ processing }) => (
                            <div className="grid gap-3 border-b border-sidebar-border/70 p-4 md:grid-cols-2 xl:grid-cols-[minmax(18rem,1fr)_minmax(10rem,14rem)_minmax(10rem,13rem)_minmax(9rem,11rem)_auto] dark:border-sidebar-border">
                                <div className="relative md:col-span-2 xl:col-span-1">
                                    <label
                                        htmlFor="inventory-search"
                                        className="sr-only"
                                    >
                                        Search inventory items by name, SKU, or
                                        barcode
                                    </label>
                                    <Search
                                        className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                    <Input
                                        id="inventory-search"
                                        name="search"
                                        type="search"
                                        defaultValue={filters.search}
                                        placeholder="Search or scan by item name, SKU, or barcode..."
                                        className="pl-9"
                                    />
                                </div>

                                <div>
                                    <label
                                        htmlFor="inventory-category"
                                        className="sr-only"
                                    >
                                        Category
                                    </label>
                                    <select
                                        id="inventory-category"
                                        name="category"
                                        defaultValue={
                                            filters.categoryId?.toString() ?? ''
                                        }
                                        className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                    >
                                        <option value="">All categories</option>

                                        {categoryOptions.map((category) => (
                                            <option
                                                key={category.id}
                                                value={category.id}
                                            >
                                                {category.name}
                                                {category.active
                                                    ? ''
                                                    : ' (inactive)'}
                                            </option>
                                        ))}
                                    </select>
                                </div>

                                <div>
                                    <label
                                        htmlFor="inventory-type"
                                        className="sr-only"
                                    >
                                        Type
                                    </label>
                                    <select
                                        id="inventory-type"
                                        name="type"
                                        defaultValue={filters.type ?? ''}
                                        className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                    >
                                        <option value="">All types</option>

                                        {itemTypeOptions.map((type) => (
                                            <option key={type} value={type}>
                                                {itemTypeLabels[type]}
                                            </option>
                                        ))}
                                    </select>
                                </div>

                                <div>
                                    <label
                                        htmlFor="inventory-status"
                                        className="sr-only"
                                    >
                                        Status
                                    </label>
                                    <select
                                        id="inventory-status"
                                        name="status"
                                        defaultValue={filters.status ?? ''}
                                        className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                    >
                                        <option value="">All statuses</option>
                                        <option value="active">Active</option>
                                        <option value="inactive">
                                            Inactive
                                        </option>
                                    </select>
                                </div>

                                <div className="flex flex-wrap items-center gap-2 md:col-span-2 xl:col-span-1 xl:justify-end">
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
                                                href={InventoryItemController.index()}
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
                        <table className="w-full min-w-[880px] text-sm">
                            <thead className="bg-muted/40 text-left text-xs text-muted-foreground">
                                <tr className="border-b border-sidebar-border/70 dark:border-sidebar-border">
                                    <th
                                        scope="col"
                                        className="px-4 py-3"
                                        aria-sort={
                                            filters.sort === 'name'
                                                ? filters.direction === 'asc'
                                                    ? 'ascending'
                                                    : 'descending'
                                                : 'none'
                                        }
                                    >
                                        <SortableHeading
                                            href={sortUrl('name')}
                                            active={filters.sort === 'name'}
                                            direction={filters.direction}
                                        >
                                            Item
                                        </SortableHeading>
                                    </th>

                                    <th
                                        scope="col"
                                        className="px-4 py-3"
                                        aria-sort={
                                            filters.sort === 'sku'
                                                ? filters.direction === 'asc'
                                                    ? 'ascending'
                                                    : 'descending'
                                                : 'none'
                                        }
                                    >
                                        <SortableHeading
                                            href={sortUrl('sku')}
                                            active={filters.sort === 'sku'}
                                            direction={filters.direction}
                                        >
                                            SKU
                                        </SortableHeading>
                                    </th>

                                    <th scope="col" className="px-4 py-3">
                                        Category
                                    </th>

                                    <th
                                        scope="col"
                                        className="px-4 py-3"
                                        aria-sort={
                                            filters.sort === 'type'
                                                ? filters.direction === 'asc'
                                                    ? 'ascending'
                                                    : 'descending'
                                                : 'none'
                                        }
                                    >
                                        <SortableHeading
                                            href={sortUrl('type')}
                                            active={filters.sort === 'type'}
                                            direction={filters.direction}
                                        >
                                            Type
                                        </SortableHeading>
                                    </th>

                                    <th scope="col" className="px-4 py-3">
                                        Base UOM
                                    </th>

                                    <th
                                        scope="col"
                                        className="px-4 py-3 text-right"
                                    >
                                        Conversions
                                    </th>

                                    <th
                                        scope="col"
                                        className="px-4 py-3"
                                        aria-sort={
                                            filters.sort === 'status'
                                                ? filters.direction === 'asc'
                                                    ? 'ascending'
                                                    : 'descending'
                                                : 'none'
                                        }
                                    >
                                        <SortableHeading
                                            href={sortUrl('status')}
                                            active={filters.sort === 'status'}
                                            direction={filters.direction}
                                        >
                                            Status
                                        </SortableHeading>
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
                                {items.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={canManage ? 8 : 7}
                                            className="px-4 py-12 text-center"
                                        >
                                            <div className="mx-auto max-w-sm">
                                                <p className="font-medium">
                                                    {hasQueryState
                                                        ? 'No inventory items match these filters.'
                                                        : 'No inventory items have been created.'}
                                                </p>
                                                <p className="mt-1 text-sm text-muted-foreground">
                                                    {hasQueryState
                                                        ? 'Adjust or reset the filters to see more items.'
                                                        : 'Create an inventory item to begin managing stock master data.'}
                                                </p>
                                            </div>
                                        </td>
                                    </tr>
                                ) : (
                                    items.map((item) => (
                                        <tr
                                            key={item.id}
                                            className="border-b border-sidebar-border/70 transition-colors last:border-b-0 hover:bg-muted/30 dark:border-sidebar-border"
                                        >
                                            <td className="px-4 py-3">
                                                {canManage ? (
                                                    <Link
                                                        href={InventoryItemController.edit(
                                                            item.id,
                                                        )}
                                                        className="font-medium underline-offset-4 hover:underline focus-visible:rounded-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                                    >
                                                        {item.name}
                                                    </Link>
                                                ) : (
                                                    <span className="font-medium">
                                                        {item.name}
                                                    </span>
                                                )}
                                            </td>

                                            <td className="px-4 py-3 font-mono text-xs text-muted-foreground">
                                                {item.sku}
                                            </td>

                                            <td className="px-4 py-3">
                                                {item.inventoryCategory?.name ??
                                                    'Uncategorized'}
                                            </td>

                                            <td className="px-4 py-3">
                                                {itemTypeLabels[item.type]}
                                            </td>

                                            <td className="px-4 py-3">
                                                <span className="font-medium">
                                                    {
                                                        item.baseUnitOfMeasure
                                                            .symbol
                                                    }
                                                </span>
                                                <span className="ml-1.5 text-xs text-muted-foreground">
                                                    {
                                                        item.baseUnitOfMeasure
                                                            .name
                                                    }
                                                </span>
                                            </td>

                                            <td className="px-4 py-3 text-right tabular-nums">
                                                {item.conversionCount}
                                            </td>

                                            <td className="px-4 py-3">
                                                <Badge
                                                    variant={
                                                        item.active
                                                            ? 'secondary'
                                                            : 'outline'
                                                    }
                                                >
                                                    {item.active
                                                        ? 'Active'
                                                        : 'Inactive'}
                                                </Badge>
                                            </td>

                                            {canManage && (
                                                <td className="px-4 py-2 text-right">
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        asChild
                                                    >
                                                        <Link
                                                            href={InventoryItemController.edit(
                                                                item.id,
                                                            )}
                                                            aria-label={`Edit ${item.name}`}
                                                        >
                                                            <Pencil
                                                                className="size-4"
                                                                aria-hidden="true"
                                                            />
                                                        </Link>
                                                    </Button>
                                                </td>
                                            )}
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    {pagination.total > 0 && (
                        <div className="flex flex-col gap-3 border-t border-sidebar-border/70 px-4 py-3 sm:flex-row sm:items-center sm:justify-between dark:border-sidebar-border">
                            <p className="text-sm text-muted-foreground">
                                Showing {pagination.from ?? 0} to{' '}
                                {pagination.to ?? 0} of{' '}
                                {pagination.total.toLocaleString()} items
                            </p>

                            {pagination.last_page > 1 && (
                                <div className="flex items-center gap-2">
                                    {pagination.prev_page_url !== null ? (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            asChild
                                        >
                                            <Link
                                                href={pagination.prev_page_url}
                                                preserveScroll
                                                preserveState
                                            >
                                                Previous
                                            </Link>
                                        </Button>
                                    ) : (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            disabled
                                        >
                                            Previous
                                        </Button>
                                    )}

                                    <span className="px-1 text-sm text-muted-foreground">
                                        Page {pagination.current_page} of{' '}
                                        {pagination.last_page}
                                    </span>

                                    {pagination.next_page_url !== null ? (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            asChild
                                        >
                                            <Link
                                                href={pagination.next_page_url}
                                                preserveScroll
                                                preserveState
                                            >
                                                Next
                                            </Link>
                                        </Button>
                                    ) : (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            disabled
                                        >
                                            Next
                                        </Button>
                                    )}
                                </div>
                            )}
                        </div>
                    )}
                </section>
            </div>
        </>
    );
}

InventoryItemsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
