import { Form, Head, Link } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    Award,
    Boxes,
    CheckCircle2,
    ChevronDown,
    Pencil,
    Plus,
    Ruler,
    Scale,
    Search,
    SlidersHorizontal,
    Tags,
} from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import InventoryAdjustmentController from '@/actions/App/Http/Controllers/Inventory/InventoryAdjustmentController';
import InventoryBrandController from '@/actions/App/Http/Controllers/Inventory/InventoryBrandController';
import InventoryCategoryController from '@/actions/App/Http/Controllers/Inventory/InventoryCategoryController';
import InventoryItemController from '@/actions/App/Http/Controllers/Inventory/InventoryItemController';
import InventoryProductController from '@/actions/App/Http/Controllers/Inventory/InventoryProductController';
import OpeningBalanceController from '@/actions/App/Http/Controllers/Inventory/OpeningBalanceController';
import UnitOfMeasureController from '@/actions/App/Http/Controllers/Inventory/UnitOfMeasureController';
import { FilterToolbar } from '@/components/filter-toolbar';
import InputError from '@/components/input-error';
import { PaginationControls } from '@/components/pagination-controls';
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
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
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

type BrandOption = {
    id: number;
    name: string;
    active: boolean;
};

type Filters = {
    search: string;
    categoryId: number | null;
    brandId: number | null;
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
    brandOptions: BrandOption[];
    createUnitOptions: UnitOfMeasureData[];
    filters: Filters;
    canManage: boolean;
};

type SortableHeadingProps = {
    active: boolean;
    children: ReactNode;
    direction: SortDirection;
    href: string;
};

type CreateInventoryItemDialogProps = {
    categories: CategoryOption[];
    trigger: ReactNode;
    units: UnitOfMeasureData[];
};

type InventoryItemDetailsDialogProps = {
    canManage: boolean;
    item: InventoryItemListItem | null;
    onOpenChange: (open: boolean) => void;
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

/** Render a stable sortable table heading without recreating route behavior. */
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

/** Render active and inactive states using canonical semantic status tokens. */
function InventoryItemStatus({ active }: { active: boolean }) {
    return (
        <StatusBadge
            label={active ? 'Active' : 'Inactive'}
            variant={active ? 'success' : 'neutral'}
        />
    );
}

/** Render the appropriate empty-state copy for an empty inventory result. */
function InventoryEmptyState({
    action,
    hasQueryState,
}: {
    action?: ReactNode;
    hasQueryState: boolean;
}) {
    return (
        <div className="mx-auto max-w-sm text-center">
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
            {action ? <div className="mt-4">{action}</div> : null}
        </div>
    );
}

/** Show an inventory-view-safe read-only summary without exposing mutation controls. */
function InventoryItemDetailsDialog({
    canManage,
    item,
    onOpenChange,
}: InventoryItemDetailsDialogProps) {
    if (item === null) {
        return null;
    }

    return (
        <Dialog open onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>{item.name}</DialogTitle>
                    <DialogDescription>
                        Read-only inventory item details.
                    </DialogDescription>
                </DialogHeader>

                <dl className="grid gap-4 sm:grid-cols-2">
                    <div>
                        <dt className="text-xs font-medium text-muted-foreground">
                            SKU
                        </dt>
                        <dd className="mt-1 font-mono text-sm">{item.sku}</dd>
                    </div>

                    <div>
                        <dt className="text-xs font-medium text-muted-foreground">
                            Status
                        </dt>
                        <dd className="mt-1">
                            <InventoryItemStatus active={item.active} />
                        </dd>
                    </div>

                    <div>
                        <dt className="text-xs font-medium text-muted-foreground">
                            Item type
                        </dt>
                        <dd className="mt-1 text-sm">
                            {itemTypeLabels[item.type]}
                        </dd>
                    </div>

                    <div>
                        <dt className="text-xs font-medium text-muted-foreground">
                            Category
                        </dt>
                        <dd className="mt-1 text-sm">
                            {item.inventoryCategory?.name ?? 'Uncategorized'}
                        </dd>
                    </div>

                    <div>
                        <dt className="text-xs font-medium text-muted-foreground">
                            Brand
                        </dt>
                        <dd className="mt-1 text-sm">
                            {item.inventoryBrand?.name ?? 'Unbranded'}
                        </dd>
                    </div>

                    <div>
                        <dt className="text-xs font-medium text-muted-foreground">
                            Base unit
                        </dt>
                        <dd className="mt-1 text-sm">
                            <span className="font-medium">
                                {item.baseUnitOfMeasure.symbol}
                            </span>{' '}
                            <span className="text-muted-foreground">
                                {item.baseUnitOfMeasure.name}
                            </span>
                        </dd>
                    </div>

                    <div>
                        <dt className="text-xs font-medium text-muted-foreground">
                            Yield
                        </dt>
                        <dd className="mt-1 text-sm tabular-nums">
                            {item.yieldPercentage}%
                        </dd>
                    </div>

                    <div>
                        <dt className="text-xs font-medium text-muted-foreground">
                            Unit conversions
                        </dt>
                        <dd className="mt-1 text-sm tabular-nums">
                            {item.conversionCount}
                        </dd>
                    </div>
                </dl>

                {canManage && (
                    <div className="flex justify-end border-t border-border pt-4">
                        <Button asChild>
                            <Link href={InventoryItemController.edit(item.id)}>
                                <Pencil className="size-4" aria-hidden="true" />
                                Edit item
                            </Link>
                        </Button>
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}

/** Create only the essential inventory master fields without leaving index context. */
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
                    <DialogTitle>Quick add inventory item</DialogTitle>
                    <DialogDescription>
                        Add the essential inventory fields without leaving this
                        page. Use Create with full details when you also need
                        brand, product-family, model, part-number, or
                        description metadata.
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
                                            aria-invalid={
                                                errors.name ? true : undefined
                                            }
                                            aria-describedby={
                                                errors.name
                                                    ? 'modal-item-name-error'
                                                    : undefined
                                            }
                                        />
                                        <InputError
                                            id="modal-item-name-error"
                                            message={errors.name}
                                        />
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
                                            aria-invalid={
                                                errors.sku ? true : undefined
                                            }
                                            aria-describedby={
                                                errors.sku
                                                    ? 'modal-item-sku-error'
                                                    : undefined
                                            }
                                        />
                                        <InputError
                                            id="modal-item-sku-error"
                                            message={errors.sku}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="modal-item-type">
                                            Item type
                                        </Label>
                                        <NativeSelect
                                            id="modal-item-type"
                                            name="type"
                                            defaultValue="ingredient"
                                            required
                                            aria-invalid={
                                                errors.type ? true : undefined
                                            }
                                            aria-describedby={
                                                errors.type
                                                    ? 'modal-item-type-error'
                                                    : undefined
                                            }
                                        >
                                            {itemTypeOptions.map((type) => (
                                                <option key={type} value={type}>
                                                    {itemTypeLabels[type]}
                                                </option>
                                            ))}
                                        </NativeSelect>
                                        <InputError
                                            id="modal-item-type-error"
                                            message={errors.type}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="modal-item-category">
                                            Category
                                        </Label>
                                        <NativeSelect
                                            id="modal-item-category"
                                            name="inventory_category_id"
                                            defaultValue=""
                                            aria-invalid={
                                                errors.inventory_category_id
                                                    ? true
                                                    : undefined
                                            }
                                            aria-describedby={
                                                errors.inventory_category_id
                                                    ? 'modal-item-category-error'
                                                    : undefined
                                            }
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
                                        </NativeSelect>
                                        <InputError
                                            id="modal-item-category-error"
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
                                            aria-invalid={
                                                errors.yield_percentage
                                                    ? true
                                                    : undefined
                                            }
                                            aria-describedby={
                                                errors.yield_percentage
                                                    ? 'modal-item-yield-error'
                                                    : undefined
                                            }
                                        />
                                        <InputError
                                            id="modal-item-yield-error"
                                            message={errors.yield_percentage}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="modal-item-base-unit">
                                            Base unit
                                        </Label>
                                        <NativeSelect
                                            id="modal-item-base-unit"
                                            name="base_unit_of_measure_id"
                                            required
                                            defaultValue=""
                                            aria-invalid={
                                                errors.base_unit_of_measure_id
                                                    ? true
                                                    : undefined
                                            }
                                            aria-describedby={
                                                errors.base_unit_of_measure_id
                                                    ? 'modal-item-base-unit-error'
                                                    : undefined
                                            }
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
                                        </NativeSelect>
                                        <InputError
                                            id="modal-item-base-unit-error"
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
                                                ? 'Creating…'
                                                : 'Quick add item'}
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

/** Render the organization-scoped searchable inventory item directory. */
export default function InventoryItemsIndex({
    items,
    pagination,
    summary,
    categoryOptions,
    brandOptions,
    createUnitOptions,
    filters,
    canManage,
}: Props) {
    const [selectedItem, setSelectedItem] =
        useState<InventoryItemListItem | null>(null);

    const hasQueryState =
        filters.search !== '' ||
        filters.categoryId !== null ||
        filters.brandId !== null ||
        filters.type !== null ||
        filters.status !== null ||
        filters.sort !== null;

    /** Preserve active filters while changing only the requested sort. */
    const sortUrl = (sort: SortKey): string => {
        const direction: SortDirection =
            filters.sort === sort && filters.direction === 'asc'
                ? 'desc'
                : 'asc';

        return InventoryItemController.index({
            query: {
                search: filters.search || undefined,
                category: filters.categoryId ?? undefined,
                brand: filters.brandId ?? undefined,
                type: filters.type ?? undefined,
                status: filters.status ?? undefined,
                sort,
                direction,
            },
        }).url;
    };

    const emptyStateAction = canManage ? (
        <div className="flex flex-wrap justify-center gap-2">
            <CreateInventoryItemDialog
                categories={categoryOptions}
                units={createUnitOptions}
                trigger={
                    <Button>
                        <Plus className="size-4" aria-hidden="true" />
                        Quick add
                    </Button>
                }
            />
            <Button variant="outline" asChild>
                <Link href={InventoryItemController.create()}>
                    Create with full details
                </Link>
            </Button>
        </div>
    ) : undefined;

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

                    {canManage && (
                        <div className="flex flex-wrap gap-2">
                            <Button variant="outline" asChild>
                                <Link href={InventoryItemController.create()}>
                                    Create with full details
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
                                        Quick add
                                    </Button>
                                }
                            />
                        </div>
                    )}
                </div>

                <nav
                    aria-label="Related inventory actions"
                    className="rounded-xl border border-border bg-card p-3"
                >
                    <div className="flex flex-wrap items-center gap-2">
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button variant="outline" size="sm">
                                    Master data
                                    <ChevronDown
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="start">
                                <DropdownMenuLabel>
                                    Inventory master data
                                </DropdownMenuLabel>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem asChild>
                                    <Link
                                        href={UnitOfMeasureController.index()}
                                    >
                                        <Ruler
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        Units of measure
                                    </Link>
                                </DropdownMenuItem>
                                <DropdownMenuItem asChild>
                                    <Link
                                        href={InventoryCategoryController.index()}
                                    >
                                        <Tags
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        Categories
                                    </Link>
                                </DropdownMenuItem>
                                <DropdownMenuItem asChild>
                                    <Link
                                        href={InventoryBrandController.index()}
                                    >
                                        <Award
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        Brands
                                    </Link>
                                </DropdownMenuItem>
                                <DropdownMenuItem asChild>
                                    <Link
                                        href={InventoryProductController.index()}
                                    >
                                        <Boxes
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        Product families
                                    </Link>
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>

                        {canManage && (
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button variant="outline" size="sm">
                                        Stock actions
                                        <ChevronDown
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="start">
                                    <DropdownMenuLabel>
                                        Inventory stock actions
                                    </DropdownMenuLabel>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem asChild>
                                        <Link
                                            href={OpeningBalanceController.create()}
                                        >
                                            <Scale
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                            Opening balance
                                        </Link>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem asChild>
                                        <Link
                                            href={InventoryAdjustmentController.create()}
                                        >
                                            <SlidersHorizontal
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                            Adjust inventory
                                        </Link>
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        )}
                    </div>
                </nav>

                <section
                    aria-label="Inventory summary"
                    className="grid gap-3 sm:grid-cols-2 lg:max-w-2xl"
                >
                    <div className="flex items-center justify-between gap-4 rounded-xl border border-border bg-card p-4">
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

                    <div className="flex items-center justify-between gap-4 rounded-xl border border-border bg-card p-4">
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

                <section className="overflow-hidden rounded-xl border border-border bg-card">
                    <Form
                        action={InventoryItemController.index().url}
                        method="get"
                    >
                        {({ processing }) => (
                            <FilterToolbar className="rounded-b-none border-x-0 border-t-0">
                                {filters.sort !== null && (
                                    <>
                                        <input
                                            type="hidden"
                                            name="sort"
                                            value={filters.sort}
                                        />
                                        <input
                                            type="hidden"
                                            name="direction"
                                            value={filters.direction}
                                        />
                                    </>
                                )}
                                <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-[minmax(18rem,1fr)_minmax(10rem,14rem)_minmax(10rem,13rem)_minmax(10rem,13rem)_minmax(9rem,11rem)_auto]">
                                    <div className="relative md:col-span-2 xl:col-span-1">
                                        <label
                                            htmlFor="inventory-search"
                                            className="sr-only"
                                        >
                                            Search inventory items by name, SKU,
                                            barcode, model number, or
                                            manufacturer part number
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
                                            placeholder="Search or scan items…"
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
                                        <NativeSelect
                                            id="inventory-category"
                                            name="category"
                                            defaultValue={
                                                filters.categoryId?.toString() ??
                                                ''
                                            }
                                        >
                                            <option value="">
                                                All categories
                                            </option>

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
                                        </NativeSelect>
                                    </div>

                                    <div>
                                        <label
                                            htmlFor="inventory-brand"
                                            className="sr-only"
                                        >
                                            Brand
                                        </label>
                                        <NativeSelect
                                            id="inventory-brand"
                                            name="brand"
                                            defaultValue={
                                                filters.brandId?.toString() ??
                                                ''
                                            }
                                        >
                                            <option value="">All brands</option>

                                            {brandOptions.map((brand) => (
                                                <option
                                                    key={brand.id}
                                                    value={brand.id}
                                                >
                                                    {brand.name}
                                                    {brand.active
                                                        ? ''
                                                        : ' (inactive)'}
                                                </option>
                                            ))}
                                        </NativeSelect>
                                    </div>

                                    <div>
                                        <label
                                            htmlFor="inventory-type"
                                            className="sr-only"
                                        >
                                            Type
                                        </label>
                                        <NativeSelect
                                            id="inventory-type"
                                            name="type"
                                            defaultValue={filters.type ?? ''}
                                        >
                                            <option value="">All types</option>

                                            {itemTypeOptions.map((type) => (
                                                <option key={type} value={type}>
                                                    {itemTypeLabels[type]}
                                                </option>
                                            ))}
                                        </NativeSelect>
                                    </div>

                                    <div>
                                        <label
                                            htmlFor="inventory-status"
                                            className="sr-only"
                                        >
                                            Status
                                        </label>
                                        <NativeSelect
                                            id="inventory-status"
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

                                    <div className="flex flex-wrap items-center gap-2 md:col-span-2 xl:col-span-1 xl:justify-end">
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
                                                    href={InventoryItemController.index()}
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

                    <Form
                        action={InventoryItemController.index().url}
                        method="get"
                        className="border-b border-border p-3 md:hidden"
                    >
                        <input
                            type="hidden"
                            name="search"
                            value={filters.search}
                        />
                        <input
                            type="hidden"
                            name="category"
                            value={filters.categoryId ?? ''}
                        />
                        <input
                            type="hidden"
                            name="brand"
                            value={filters.brandId ?? ''}
                        />
                        <input
                            type="hidden"
                            name="type"
                            value={filters.type ?? ''}
                        />
                        <input
                            type="hidden"
                            name="status"
                            value={filters.status ?? ''}
                        />
                        <label
                            htmlFor="inventory-mobile-sort"
                            className="sr-only"
                        >
                            Sort inventory items
                        </label>
                        <NativeSelect
                            id="inventory-mobile-sort"
                            name="sort"
                            defaultValue={filters.sort ?? 'name'}
                            onChange={(event) =>
                                event.currentTarget.form?.requestSubmit()
                            }
                        >
                            <option value="name">Sort by name</option>
                            <option value="sku">Sort by SKU</option>
                            <option value="type">Sort by type</option>
                            <option value="status">Sort by status</option>
                        </NativeSelect>
                        <input
                            type="hidden"
                            name="direction"
                            value={filters.direction}
                        />
                    </Form>

                    {items.length === 0 ? (
                        <div className="px-4 py-12 md:hidden">
                            <InventoryEmptyState
                                action={emptyStateAction}
                                hasQueryState={hasQueryState}
                            />
                        </div>
                    ) : (
                        <div className="divide-y divide-border md:hidden">
                            {items.map((item) => (
                                <article
                                    key={item.id}
                                    className="space-y-4 p-4"
                                    aria-labelledby={`inventory-item-${item.id}-name`}
                                >
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="min-w-0">
                                            <h2
                                                id={`inventory-item-${item.id}-name`}
                                                className="font-medium"
                                            >
                                                {item.name}
                                            </h2>
                                            <p className="mt-1 font-mono text-xs text-muted-foreground">
                                                {item.sku}
                                            </p>
                                        </div>

                                        <InventoryItemStatus
                                            active={item.active}
                                        />
                                    </div>

                                    <dl className="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                                        <div>
                                            <dt className="text-xs text-muted-foreground">
                                                Category
                                            </dt>
                                            <dd className="mt-0.5">
                                                {item.inventoryCategory?.name ??
                                                    'Uncategorized'}
                                            </dd>
                                        </div>

                                        <div>
                                            <dt className="text-xs text-muted-foreground">
                                                Brand
                                            </dt>
                                            <dd className="mt-0.5">
                                                {item.inventoryBrand?.name ??
                                                    'Unbranded'}
                                            </dd>
                                        </div>

                                        <div>
                                            <dt className="text-xs text-muted-foreground">
                                                Type
                                            </dt>
                                            <dd className="mt-0.5">
                                                {itemTypeLabels[item.type]}
                                            </dd>
                                        </div>

                                        <div>
                                            <dt className="text-xs text-muted-foreground">
                                                Base UOM
                                            </dt>
                                            <dd className="mt-0.5">
                                                <span className="font-medium">
                                                    {
                                                        item.baseUnitOfMeasure
                                                            .symbol
                                                    }
                                                </span>{' '}
                                                <span className="text-muted-foreground">
                                                    {
                                                        item.baseUnitOfMeasure
                                                            .name
                                                    }
                                                </span>
                                            </dd>
                                        </div>

                                        <div>
                                            <dt className="text-xs text-muted-foreground">
                                                Conversions
                                            </dt>
                                            <dd className="mt-0.5 tabular-nums">
                                                {item.conversionCount}
                                            </dd>
                                        </div>
                                    </dl>

                                    <div className="flex flex-wrap items-center justify-between gap-2 border-t border-border pt-3">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={() =>
                                                setSelectedItem(item)
                                            }
                                            aria-label={`View ${item.name} details`}
                                        >
                                            View details
                                        </Button>

                                        {canManage && (
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                asChild
                                            >
                                                <Link
                                                    href={InventoryItemController.edit(
                                                        item.id,
                                                    )}
                                                >
                                                    <Pencil
                                                        className="size-4"
                                                        aria-hidden="true"
                                                    />
                                                    Edit
                                                </Link>
                                            </Button>
                                        )}
                                    </div>
                                </article>
                            ))}
                        </div>
                    )}

                    <div className="hidden overflow-x-auto md:block">
                        <table className="w-full min-w-[880px] text-sm">
                            <thead className="bg-muted/40 text-left text-xs text-muted-foreground">
                                <tr className="border-b border-border">
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

                                    <th scope="col" className="px-4 py-3">
                                        Brand
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
                                            colSpan={canManage ? 9 : 8}
                                            className="px-4 py-12"
                                        >
                                            <InventoryEmptyState
                                                action={emptyStateAction}
                                                hasQueryState={hasQueryState}
                                            />
                                        </td>
                                    </tr>
                                ) : (
                                    items.map((item) => (
                                        <tr
                                            key={item.id}
                                            className="border-b border-border transition-colors last:border-b-0 hover:bg-muted/30"
                                        >
                                            <td className="px-4 py-3">
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        setSelectedItem(item)
                                                    }
                                                    aria-label={`View ${item.name} details`}
                                                    className="font-medium underline-offset-4 hover:underline focus-visible:rounded-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                                >
                                                    {item.name}
                                                </button>
                                            </td>

                                            <td className="px-4 py-3 font-mono text-xs text-muted-foreground">
                                                {item.sku}
                                            </td>

                                            <td className="px-4 py-3">
                                                {item.inventoryCategory?.name ??
                                                    'Uncategorized'}
                                            </td>

                                            <td className="px-4 py-3">
                                                {item.inventoryBrand?.name ??
                                                    'Unbranded'}
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
                                                <InventoryItemStatus
                                                    active={item.active}
                                                />
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
                        <PaginationControls
                            currentPage={pagination.current_page}
                            from={pagination.from}
                            itemLabel="items"
                            lastPage={pagination.last_page}
                            nextPageUrl={pagination.next_page_url}
                            previousPageUrl={pagination.prev_page_url}
                            preserveScroll
                            preserveState
                            to={pagination.to}
                            total={pagination.total}
                        />
                    )}
                </section>
            </div>

            <InventoryItemDetailsDialog
                item={selectedItem}
                canManage={canManage}
                onOpenChange={(open) => {
                    if (!open) {
                        setSelectedItem(null);
                    }
                }}
            />
        </>
    );
}

InventoryItemsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
        {
            title: 'Inventory items',
            href: InventoryItemController.index(),
        },
    ],
};
