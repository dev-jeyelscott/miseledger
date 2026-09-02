import { Form, Head, Link } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';
import type { ReactNode } from 'react';
import InventoryItemController from '@/actions/App/Http/Controllers/Inventory/InventoryItemController';
import InventoryProductController from '@/actions/App/Http/Controllers/Inventory/InventoryProductController';
import { FilterToolbar } from '@/components/filter-toolbar';
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

type ProductFamily = {
    id: number;
    name: string;
    active: boolean;
    variantCount: number;
};

type ProductFamilyStatus = 'active' | 'inactive';

type Filters = {
    search: string;
    status: ProductFamilyStatus | null;
};

type Props = {
    productFamilies: ProductFamily[];
    filters: Filters;
    canManage: boolean;
};

type CreateProductFamilyDialogProps = {
    trigger: ReactNode;
};

/** Create a product family without leaving the product-family index. */
function CreateProductFamilyDialog({
    trigger,
}: CreateProductFamilyDialogProps) {
    const dialog = useGuardedDialog(
        'Discard the new product family you entered?',
    );

    return (
        <Dialog open={dialog.open} onOpenChange={dialog.onOpenChange}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>

            <DialogContent className="max-h-[calc(100vh-2rem)] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Create product family</DialogTitle>
                    <DialogDescription>
                        Group related inventory variants and define the
                        controlled options they use.
                    </DialogDescription>
                </DialogHeader>

                <div onChange={dialog.markDirty}>
                    <Form
                        {...InventoryProductController.store.form()}
                        className="space-y-5"
                        resetOnSuccess
                        onSuccess={dialog.closeAfterSuccess}
                    >
                        {({ processing, errors }) => (
                            <>
                                <Field
                                    id="create-product-family-name"
                                    label="Name"
                                    error={errors.name}
                                >
                                    <Input
                                        name="name"
                                        required
                                        autoFocus
                                        placeholder="e.g., Cordless drills"
                                    />
                                </Field>

                                <Field
                                    id="create-product-family-active"
                                    label="Status"
                                    error={errors.active}
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
                                        {processing ? 'Creating…' : 'Create'}
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

/** Render product families with the canonical server-backed master-data composition. */
export default function ProductFamiliesIndex({
    productFamilies,
    filters,
    canManage,
}: Props) {
    const hasQueryState = filters.search !== '' || filters.status !== null;

    return (
        <>
            <Head title="Product families" />

            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <PageHeader
                    title="Product families"
                    description="Group related inventory variants and define the controlled options they use."
                    actions={
                        canManage ? (
                            <CreateProductFamilyDialog
                                trigger={
                                    <Button>
                                        <Plus
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        Create product family
                                    </Button>
                                }
                            />
                        ) : undefined
                    }
                />

                <section className="overflow-hidden rounded-xl border border-border bg-card">
                    <div className="border-b border-border px-4 py-3">
                        <h2 className="text-sm font-semibold">
                            Available families
                        </h2>
                    </div>

                    <Form
                        action={InventoryProductController.index().url}
                        method="get"
                    >
                        {({ processing }) => (
                            <FilterToolbar className="rounded-none border-x-0 border-t-0">
                                <div className="grid gap-3 md:grid-cols-[minmax(0,1fr)_12rem_auto] md:items-center">
                                    <div className="relative">
                                        <label
                                            htmlFor="product-family-search"
                                            className="sr-only"
                                        >
                                            Search product families
                                        </label>
                                        <Search
                                            className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                            aria-hidden="true"
                                        />
                                        <Input
                                            id="product-family-search"
                                            name="search"
                                            type="search"
                                            defaultValue={filters.search}
                                            placeholder="Search product families..."
                                            className="pl-9"
                                        />
                                    </div>

                                    <div>
                                        <label
                                            htmlFor="product-family-status-filter"
                                            className="sr-only"
                                        >
                                            Filter by status
                                        </label>
                                        <NativeSelect
                                            id="product-family-status-filter"
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
                                                    href={InventoryProductController.index()}
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

                    {productFamilies.length === 0 ? (
                        <div className="px-4 py-10 text-center md:hidden">
                            <p className="font-medium">
                                {hasQueryState
                                    ? 'No product families match these filters.'
                                    : 'No product families yet'}
                            </p>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {hasQueryState
                                    ? 'Adjust or reset the filters to see more product families.'
                                    : canManage
                                      ? 'Create a family to organize related item variants.'
                                      : 'Product families will appear here when available.'}
                            </p>
                        </div>
                    ) : (
                        <div className="divide-y divide-border md:hidden">
                            {productFamilies.map((productFamily) => (
                                <article
                                    key={productFamily.id}
                                    className="space-y-3 p-4"
                                    aria-labelledby={`product-family-${productFamily.id}-name`}
                                >
                                    <div className="flex items-start justify-between gap-3">
                                        <Link
                                            id={`product-family-${productFamily.id}-name`}
                                            href={InventoryProductController.show(
                                                productFamily.id,
                                            )}
                                            className="font-medium focus-visible:rounded-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                        >
                                            {productFamily.name}
                                        </Link>

                                        <StatusBadge
                                            label={
                                                productFamily.active
                                                    ? 'Active'
                                                    : 'Inactive'
                                            }
                                            variant={
                                                productFamily.active
                                                    ? 'success'
                                                    : 'neutral'
                                            }
                                        />
                                    </div>

                                    <p className="text-sm text-muted-foreground">
                                        {productFamily.variantCount} variants
                                    </p>
                                </article>
                            ))}
                        </div>
                    )}

                    <div className="hidden overflow-x-auto md:block">
                        <table className="w-full min-w-[38rem] text-sm">
                            <caption className="sr-only">
                                Product families available to this organization
                            </caption>
                            <thead className="bg-muted/40 text-left text-muted-foreground">
                                <tr>
                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-medium"
                                    >
                                        Product family
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-medium"
                                    >
                                        Variants
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-medium"
                                    >
                                        Status
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {productFamilies.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={3}
                                            className="px-4 py-10 text-center"
                                        >
                                            <p className="font-medium">
                                                {hasQueryState
                                                    ? 'No product families match these filters.'
                                                    : 'No product families yet'}
                                            </p>
                                            <p className="mt-1 text-sm text-muted-foreground">
                                                {hasQueryState
                                                    ? 'Adjust or reset the filters to see more product families.'
                                                    : canManage
                                                      ? 'Create a family to organize related item variants.'
                                                      : 'Product families will appear here when available.'}
                                            </p>
                                        </td>
                                    </tr>
                                ) : (
                                    productFamilies.map((productFamily) => (
                                        <tr
                                            key={productFamily.id}
                                            className="border-t border-border hover:bg-muted/30"
                                        >
                                            <td className="px-4 py-3 font-medium">
                                                <Link
                                                    href={InventoryProductController.show(
                                                        productFamily.id,
                                                    )}
                                                    className="focus-visible:rounded-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                                >
                                                    {productFamily.name}
                                                </Link>
                                            </td>
                                            <td className="px-4 py-3 tabular-nums">
                                                {productFamily.variantCount}
                                            </td>
                                            <td className="px-4 py-3">
                                                <StatusBadge
                                                    label={
                                                        productFamily.active
                                                            ? 'Active'
                                                            : 'Inactive'
                                                    }
                                                    variant={
                                                        productFamily.active
                                                            ? 'success'
                                                            : 'neutral'
                                                    }
                                                />
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </>
    );
}

ProductFamiliesIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Inventory', href: InventoryItemController.index() },
        { title: 'Product families', href: InventoryProductController.index() },
    ],
};
