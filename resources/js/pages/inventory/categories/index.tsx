import { Form, Head } from '@inertiajs/react';
import { Pencil, Plus, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import type { ReactNode } from 'react';
import InventoryCategoryController from '@/actions/App/Http/Controllers/Inventory/InventoryCategoryController';
import InventoryItemController from '@/actions/App/Http/Controllers/Inventory/InventoryItemController';
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
import type { InventoryCategoryData } from '@/types';

type Props = {
    categories: InventoryCategoryData[];
    canManage: boolean;
};

type CategoryStatusFilter = 'all' | 'active' | 'inactive';

type CreateInventoryCategoryDialogProps = {
    trigger: ReactNode;
};

type EditInventoryCategoryDialogProps = {
    category: InventoryCategoryData;
    trigger: ReactNode;
};

/** Format a category count with the correct singular or plural label. */
function formatCategoryCount(count: number): string {
    return `${count.toLocaleString()} ${count === 1 ? 'category' : 'categories'}`;
}

/** Create a lightweight category without leaving the category index. */
function CreateInventoryCategoryDialog({
    trigger,
}: CreateInventoryCategoryDialogProps) {
    const dialog = useGuardedDialog(
        'Discard the new inventory category you entered?',
    );

    return (
        <Dialog open={dialog.open} onOpenChange={dialog.onOpenChange}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>

            <DialogContent className="max-h-[calc(100vh-2rem)] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Create inventory category</DialogTitle>
                    <DialogDescription>
                        Add a category for organizing inventory master records.
                    </DialogDescription>
                </DialogHeader>

                <div onChange={dialog.markDirty}>
                    <Form
                        {...InventoryCategoryController.store.form()}
                        errorBag="createInventoryCategory"
                        className="space-y-5"
                        resetOnSuccess
                        onSuccess={dialog.closeAfterSuccess}
                    >
                        {({ processing, errors }) => (
                            <>
                                <input type="hidden" name="_modal" value="1" />

                                <div className="grid gap-2">
                                    <Label htmlFor="create-category-name">
                                        Name
                                    </Label>
                                    <Input
                                        id="create-category-name"
                                        name="name"
                                        required
                                        autoFocus
                                        placeholder="e.g., Dry Goods"
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="create-category-active">
                                        Status
                                    </Label>
                                    <select
                                        id="create-category-active"
                                        name="active"
                                        defaultValue="1"
                                        aria-describedby="create-category-status-help"
                                        className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                    >
                                        <option value="1">Active</option>
                                        <option value="0">Inactive</option>
                                    </select>
                                    <p
                                        id="create-category-status-help"
                                        className="text-xs text-muted-foreground"
                                    >
                                        Inactive categories remain available for
                                        existing records but are excluded from
                                        new item category choices.
                                    </p>
                                    <InputError message={errors.active} />
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
                                        <Plus
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        {processing
                                            ? 'Creating...'
                                            : 'Create category'}
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

/** Edit a lightweight category record without leaving the category index. */
function EditInventoryCategoryDialog({
    category,
    trigger,
}: EditInventoryCategoryDialogProps) {
    const dialog = useGuardedDialog(
        'Discard the inventory category changes you entered?',
    );

    const statusHelpId = `category-status-help-${category.id}`;

    return (
        <Dialog open={dialog.open} onOpenChange={dialog.onOpenChange}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>

            <DialogContent className="max-h-[calc(100vh-2rem)] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Edit inventory category</DialogTitle>
                    <DialogDescription>
                        Update the category name or whether it is available for
                        new inventory item assignments.
                    </DialogDescription>
                </DialogHeader>

                <div onChange={dialog.markDirty}>
                    <Form
                        {...InventoryCategoryController.update.form(
                            category.id,
                        )}
                        errorBag={`editInventoryCategory${category.id}`}
                        className="space-y-5"
                        onSuccess={dialog.closeAfterSuccess}
                    >
                        {({ processing, errors }) => (
                            <>
                                <input type="hidden" name="_modal" value="1" />

                                <div className="grid gap-2">
                                    <Label
                                        htmlFor={`category-name-${category.id}`}
                                    >
                                        Name
                                    </Label>
                                    <Input
                                        id={`category-name-${category.id}`}
                                        name="name"
                                        defaultValue={category.name}
                                        required
                                        autoFocus
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label
                                        htmlFor={`category-active-${category.id}`}
                                    >
                                        Status
                                    </Label>
                                    <select
                                        id={`category-active-${category.id}`}
                                        name="active"
                                        defaultValue={
                                            category.active ? '1' : '0'
                                        }
                                        aria-describedby={statusHelpId}
                                        className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                    >
                                        <option value="1">Active</option>
                                        <option value="0">Inactive</option>
                                    </select>
                                    <p
                                        id={statusHelpId}
                                        className="text-xs text-muted-foreground"
                                    >
                                        Inactive categories remain available for
                                        existing records but are excluded from
                                        new item category choices.
                                    </p>
                                    <InputError message={errors.active} />
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
                                            ? 'Saving...'
                                            : 'Save category'}
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

/** Render the organization category master list with lightweight discovery controls. */
export default function InventoryCategoriesIndex({
    categories,
    canManage,
}: Props) {
    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] =
        useState<CategoryStatusFilter>('all');

    const filteredCategories = useMemo(() => {
        const normalizedSearch = search.trim().toLowerCase();

        return categories.filter((category) => {
            const matchesSearch =
                normalizedSearch === '' ||
                category.name.toLowerCase().includes(normalizedSearch);

            const matchesStatus =
                statusFilter === 'all' ||
                (statusFilter === 'active' && category.active) ||
                (statusFilter === 'inactive' && !category.active);

            return matchesSearch && matchesStatus;
        });
    }, [categories, search, statusFilter]);

    const hasFilters = search.trim() !== '' || statusFilter !== 'all';

    const categoryCount =
        filteredCategories.length === categories.length
            ? formatCategoryCount(categories.length)
            : `${formatCategoryCount(filteredCategories.length)} of ${formatCategoryCount(
                  categories.length,
              )}`;

    return (
        <>
            <Head title="Inventory categories" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Inventory categories
                        </h1>
                        <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
                            Organize inventory with flat, organization-specific
                            categories to keep items easy to find and report on.
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2 sm:justify-end">
                        {canManage && (
                            <CreateInventoryCategoryDialog
                                trigger={
                                    <Button>
                                        <Plus
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        Create category
                                    </Button>
                                }
                            />
                        )}
                    </div>
                </div>

                <section
                    aria-label="Inventory categories"
                    className="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border"
                >
                    <div className="grid gap-3 border-b border-sidebar-border/70 p-4 md:grid-cols-[minmax(0,1fr)_12rem_auto] md:items-center dark:border-sidebar-border">
                        <div className="relative">
                            <label
                                htmlFor="category-search"
                                className="sr-only"
                            >
                                Search categories
                            </label>
                            <Search
                                className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <Input
                                id="category-search"
                                type="search"
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder="Search categories..."
                                className="pl-9"
                            />
                        </div>

                        <div>
                            <label
                                htmlFor="category-status-filter"
                                className="sr-only"
                            >
                                Filter by status
                            </label>
                            <select
                                id="category-status-filter"
                                value={statusFilter}
                                onChange={(event) =>
                                    setStatusFilter(
                                        event.target
                                            .value as CategoryStatusFilter,
                                    )
                                }
                                className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            >
                                <option value="all">All statuses</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>

                        <div className="flex items-center gap-2 md:justify-end">
                            <p
                                aria-live="polite"
                                className="text-sm whitespace-nowrap text-muted-foreground"
                            >
                                {categoryCount}
                            </p>

                            {hasFilters && (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => {
                                        setSearch('');
                                        setStatusFilter('all');
                                    }}
                                >
                                    Reset
                                </Button>
                            )}
                        </div>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[560px] text-sm">
                            <thead className="bg-muted/40 text-left text-xs text-muted-foreground">
                                <tr className="border-b border-sidebar-border/70 dark:border-sidebar-border">
                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-medium"
                                    >
                                        Category name
                                    </th>
                                    <th
                                        scope="col"
                                        className="w-36 px-4 py-3 font-medium"
                                    >
                                        Status
                                    </th>

                                    {canManage && (
                                        <th
                                            scope="col"
                                            className="w-32 px-4 py-3 text-right font-medium"
                                        >
                                            Actions
                                        </th>
                                    )}
                                </tr>
                            </thead>

                            <tbody>
                                {filteredCategories.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={canManage ? 3 : 2}
                                            className="px-4 py-12 text-center"
                                        >
                                            <div className="mx-auto max-w-sm">
                                                <p className="font-medium">
                                                    {hasFilters
                                                        ? 'No categories match these filters.'
                                                        : 'No inventory categories have been created.'}
                                                </p>
                                                <p className="mt-1 text-sm text-muted-foreground">
                                                    {hasFilters
                                                        ? 'Adjust or reset the filters to see more categories.'
                                                        : canManage
                                                          ? 'Create a category to start organizing inventory items.'
                                                          : 'Categories will appear here when they are available.'}
                                                </p>
                                            </div>
                                        </td>
                                    </tr>
                                ) : (
                                    filteredCategories.map((category) => (
                                        <tr
                                            key={category.id}
                                            className="border-b border-sidebar-border/70 transition-colors last:border-b-0 hover:bg-muted/30 dark:border-sidebar-border"
                                        >
                                            <td className="px-4 py-3">
                                                <span className="font-medium">
                                                    {category.name}
                                                </span>
                                            </td>

                                            <td className="px-4 py-3">
                                                <Badge
                                                    variant={
                                                        category.active
                                                            ? 'secondary'
                                                            : 'outline'
                                                    }
                                                >
                                                    {category.active
                                                        ? 'Active'
                                                        : 'Inactive'}
                                                </Badge>
                                            </td>

                                            {canManage && (
                                                <td className="px-4 py-2 text-right">
                                                    <EditInventoryCategoryDialog
                                                        category={category}
                                                        trigger={
                                                            <Button
                                                                variant="outline"
                                                                size="sm"
                                                                aria-label={`Edit ${category.name}`}
                                                            >
                                                                <Pencil
                                                                    className="size-3.5"
                                                                    aria-hidden="true"
                                                                />
                                                                Edit
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

InventoryCategoriesIndex.layout = {
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
