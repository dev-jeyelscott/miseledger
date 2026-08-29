import { Form, Head, Link } from '@inertiajs/react';
import { Pencil, Plus, Search } from 'lucide-react';
import type { ReactNode } from 'react';
import InventoryCategoryController from '@/actions/App/Http/Controllers/Inventory/InventoryCategoryController';
import InventoryItemController from '@/actions/App/Http/Controllers/Inventory/InventoryItemController';
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
import type { InventoryCategoryListItem } from '@/types';

type CategoryStatus = 'active' | 'inactive';

type Filters = {
    search: string;
    status: CategoryStatus | null;
};

type Props = {
    categories: InventoryCategoryListItem[];
    filters: Filters;
    canManage: boolean;
};

type CreateInventoryCategoryDialogProps = {
    trigger: ReactNode;
};

type EditInventoryCategoryDialogProps = {
    category: InventoryCategoryListItem;
    trigger: ReactNode;
};

/** Render active and inactive states using canonical semantic status tokens. */
function InventoryCategoryStatus({ active }: { active: boolean }) {
    return (
        <StatusBadge
            label={active ? 'Active' : 'Inactive'}
            variant={active ? 'success' : 'neutral'}
        />
    );
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

                                <Field
                                    id="create-category-name"
                                    label="Name"
                                    error={errors.name}
                                >
                                    <Input
                                        name="name"
                                        required
                                        autoFocus
                                        placeholder="e.g., Dry Goods"
                                    />
                                </Field>

                                <Field
                                    id="create-category-active"
                                    label="Status"
                                    error={errors.active}
                                    helper="Inactive categories remain available for existing records but are excluded from new item category choices."
                                >
                                    <NativeSelect
                                        name="active"
                                        defaultValue="1"
                                    >
                                        <option value="1">Active</option>
                                        <option value="0">Inactive</option>
                                    </NativeSelect>
                                </Field>

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
                                            ? 'Creating…'
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

                                <Field
                                    id={`category-name-${category.id}`}
                                    label="Name"
                                    error={errors.name}
                                >
                                    <Input
                                        name="name"
                                        defaultValue={category.name}
                                        required
                                        autoFocus
                                    />
                                </Field>

                                <Field
                                    id={`category-active-${category.id}`}
                                    label="Status"
                                    error={errors.active}
                                    helper="Inactive categories remain available for existing records but are excluded from new item category choices."
                                >
                                    <NativeSelect
                                        name="active"
                                        defaultValue={
                                            category.active ? '1' : '0'
                                        }
                                    >
                                        <option value="1">Active</option>
                                        <option value="0">Inactive</option>
                                    </NativeSelect>
                                </Field>

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
                                            ? 'Saving…'
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

/** Render the organization category list with server-backed discovery controls. */
export default function InventoryCategoriesIndex({
    categories,
    filters,
    canManage,
}: Props) {
    const hasQueryState = filters.search !== '' || filters.status !== null;

    return (
        <>
            <Head title="Inventory categories" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Inventory categories"
                    description="Organize inventory with flat, organization-specific categories to keep items easy to find and report on."
                    actions={
                        canManage ? (
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
                        ) : undefined
                    }
                />

                <section
                    aria-label="Inventory categories"
                    className="overflow-hidden rounded-xl border border-border bg-card"
                >
                    <Form
                        action={InventoryCategoryController.index().url}
                        method="get"
                    >
                        {({ processing }) => (
                            <FilterToolbar className="rounded-b-none border-x-0 border-t-0">
                                <div className="grid gap-3 md:grid-cols-[minmax(0,1fr)_12rem_auto] md:items-center">
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
                                            name="search"
                                            type="search"
                                            defaultValue={filters.search}
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
                                        <NativeSelect
                                            id="category-status-filter"
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

                                    <div className="flex items-center gap-2 md:justify-end">
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
                                                    href={InventoryCategoryController.index()}
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

                    {categories.length === 0 ? (
                        <div className="px-4 py-12 md:hidden">
                            <div className="mx-auto max-w-sm text-center">
                                <p className="font-medium">
                                    {hasQueryState
                                        ? 'No categories match these filters.'
                                        : 'No inventory categories have been created.'}
                                </p>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {hasQueryState
                                        ? 'Adjust or reset the filters to see more categories.'
                                        : canManage
                                          ? 'Create a category to start organizing inventory items.'
                                          : 'Categories will appear here when they are available.'}
                                </p>
                            </div>
                        </div>
                    ) : (
                        <div className="divide-y divide-border md:hidden">
                            {categories.map((category) => (
                                <article
                                    key={category.id}
                                    className="space-y-4 p-4"
                                    aria-labelledby={`category-${category.id}-name`}
                                >
                                    <div className="flex items-start justify-between gap-3">
                                        <h2
                                            id={`category-${category.id}-name`}
                                            className="font-medium"
                                        >
                                            {category.name}
                                        </h2>

                                        <InventoryCategoryStatus
                                            active={category.active}
                                        />
                                    </div>

                                    <p className="text-sm text-muted-foreground">
                                        Used by{' '}
                                        {category.usageCount.toLocaleString()}{' '}
                                        {category.usageCount === 1
                                            ? 'item'
                                            : 'items'}
                                    </p>

                                    {canManage && (
                                        <div className="flex justify-end border-t border-border pt-3">
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
                                        </div>
                                    )}
                                </article>
                            ))}
                        </div>
                    )}

                    <div className="hidden overflow-x-auto md:block">
                        <table className="w-full min-w-[640px] text-sm">
                            <thead className="bg-muted/40 text-left text-xs text-muted-foreground">
                                <tr className="border-b border-border">
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
                                    <th
                                        scope="col"
                                        className="px-4 py-3 text-right font-medium"
                                    >
                                        Used by
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
                                {categories.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={canManage ? 4 : 3}
                                            className="px-4 py-12 text-center"
                                        >
                                            <div className="mx-auto max-w-sm">
                                                <p className="font-medium">
                                                    {hasQueryState
                                                        ? 'No categories match these filters.'
                                                        : 'No inventory categories have been created.'}
                                                </p>
                                                <p className="mt-1 text-sm text-muted-foreground">
                                                    {hasQueryState
                                                        ? 'Adjust or reset the filters to see more categories.'
                                                        : canManage
                                                          ? 'Create a category to start organizing inventory items.'
                                                          : 'Categories will appear here when they are available.'}
                                                </p>
                                            </div>
                                        </td>
                                    </tr>
                                ) : (
                                    categories.map((category) => (
                                        <tr
                                            key={category.id}
                                            className="border-b border-border transition-colors last:border-b-0 hover:bg-muted/30"
                                        >
                                            <td className="px-4 py-3">
                                                <span className="font-medium">
                                                    {category.name}
                                                </span>
                                            </td>

                                            <td className="px-4 py-3">
                                                <InventoryCategoryStatus
                                                    active={category.active}
                                                />
                                            </td>

                                            <td className="px-4 py-3 text-right tabular-nums">
                                                {category.usageCount.toLocaleString()}{' '}
                                                {category.usageCount === 1
                                                    ? 'item'
                                                    : 'items'}
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
        {
            title: 'Categories',
            href: InventoryCategoryController.index(),
        },
    ],
};
