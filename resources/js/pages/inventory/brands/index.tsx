import { Form, Head } from '@inertiajs/react';
import { Pencil, Plus, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import type { ReactNode } from 'react';
import InventoryBrandController from '@/actions/App/Http/Controllers/Inventory/InventoryBrandController';
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
import type { InventoryBrandData } from '@/types';

type Props = {
    brands: InventoryBrandData[];
    canManage: boolean;
};

type BrandStatusFilter = 'all' | 'active' | 'inactive';

type CreateInventoryBrandDialogProps = {
    trigger: ReactNode;
};

type EditInventoryBrandDialogProps = {
    brand: InventoryBrandData;
    trigger: ReactNode;
};

/** Format a brand count with the correct singular or plural label. */
function formatBrandCount(count: number): string {
    return `${count.toLocaleString()} ${count === 1 ? 'brand' : 'brands'}`;
}

/** Render active and inactive states using canonical semantic status tokens. */
function InventoryBrandStatus({ active }: { active: boolean }) {
    return (
        <StatusBadge
            label={active ? 'Active' : 'Inactive'}
            variant={active ? 'success' : 'neutral'}
        />
    );
}

/** Create a lightweight brand without leaving the brand index. */
function CreateInventoryBrandDialog({
    trigger,
}: CreateInventoryBrandDialogProps) {
    const dialog = useGuardedDialog(
        'Discard the new inventory brand you entered?',
    );

    return (
        <Dialog open={dialog.open} onOpenChange={dialog.onOpenChange}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>

            <DialogContent className="max-h-[calc(100vh-2rem)] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Create inventory brand</DialogTitle>
                    <DialogDescription>
                        Add a brand for organizing inventory master records.
                    </DialogDescription>
                </DialogHeader>

                <div onChange={dialog.markDirty}>
                    <Form
                        {...InventoryBrandController.store.form()}
                        errorBag="createInventoryBrand"
                        className="space-y-5"
                        resetOnSuccess
                        onSuccess={dialog.closeAfterSuccess}
                    >
                        {({ processing, errors }) => (
                            <>
                                <input type="hidden" name="_modal" value="1" />

                                <Field
                                    id="create-brand-name"
                                    label="Name"
                                    error={errors.name}
                                >
                                    <Input
                                        name="name"
                                        required
                                        autoFocus
                                        placeholder="e.g., Acme Foods"
                                    />
                                </Field>

                                <Field
                                    id="create-brand-active"
                                    label="Status"
                                    error={errors.active}
                                    helper="Inactive brands remain available for existing records but are excluded from new item brand choices."
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
                                            : 'Create brand'}
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

/** Edit a lightweight brand record without leaving the brand index. */
function EditInventoryBrandDialog({
    brand,
    trigger,
}: EditInventoryBrandDialogProps) {
    const dialog = useGuardedDialog(
        'Discard the inventory brand changes you entered?',
    );

    return (
        <Dialog open={dialog.open} onOpenChange={dialog.onOpenChange}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>

            <DialogContent className="max-h-[calc(100vh-2rem)] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Edit inventory brand</DialogTitle>
                    <DialogDescription>
                        Update the brand name or whether it is available for new
                        inventory item assignments.
                    </DialogDescription>
                </DialogHeader>

                <div onChange={dialog.markDirty}>
                    <Form
                        {...InventoryBrandController.update.form(brand.id)}
                        errorBag={`editInventoryBrand${brand.id}`}
                        className="space-y-5"
                        onSuccess={dialog.closeAfterSuccess}
                    >
                        {({ processing, errors }) => (
                            <>
                                <input type="hidden" name="_modal" value="1" />

                                <Field
                                    id={`brand-name-${brand.id}`}
                                    label="Name"
                                    error={errors.name}
                                >
                                    <Input
                                        name="name"
                                        defaultValue={brand.name}
                                        required
                                        autoFocus
                                    />
                                </Field>

                                <Field
                                    id={`brand-active-${brand.id}`}
                                    label="Status"
                                    error={errors.active}
                                    helper="Inactive brands remain available for existing records but are excluded from new item brand choices."
                                >
                                    <NativeSelect
                                        name="active"
                                        defaultValue={brand.active ? '1' : '0'}
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
                                        {processing ? 'Saving…' : 'Save brand'}
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

/** Render the organization brand master list with lightweight discovery controls. */
export default function InventoryBrandsIndex({ brands, canManage }: Props) {
    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState<BrandStatusFilter>('all');

    const filteredBrands = useMemo(() => {
        const normalizedSearch = search.trim().toLowerCase();

        return brands.filter((brand) => {
            const matchesSearch =
                normalizedSearch === '' ||
                brand.name.toLowerCase().includes(normalizedSearch);

            const matchesStatus =
                statusFilter === 'all' ||
                (statusFilter === 'active' && brand.active) ||
                (statusFilter === 'inactive' && !brand.active);

            return matchesSearch && matchesStatus;
        });
    }, [brands, search, statusFilter]);

    const hasFilters = search.trim() !== '' || statusFilter !== 'all';

    const brandCount =
        filteredBrands.length === brands.length
            ? formatBrandCount(brands.length)
            : `${formatBrandCount(filteredBrands.length)} of ${formatBrandCount(
                  brands.length,
              )}`;

    return (
        <>
            <Head title="Inventory brands" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Inventory brands"
                    description="Maintain organization-specific brands to keep inventory master records easy to find and report on."
                    actions={
                        canManage ? (
                            <CreateInventoryBrandDialog
                                trigger={
                                    <Button>
                                        <Plus
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        Create brand
                                    </Button>
                                }
                            />
                        ) : undefined
                    }
                />

                <section
                    aria-label="Inventory brands"
                    className="overflow-hidden rounded-xl border border-border bg-card"
                >
                    <FilterToolbar className="rounded-b-none border-x-0 border-t-0">
                        <div className="grid gap-3 md:grid-cols-[minmax(0,1fr)_12rem_auto] md:items-center">
                            <div className="relative">
                                <label
                                    htmlFor="brand-search"
                                    className="sr-only"
                                >
                                    Search brands
                                </label>
                                <Search
                                    className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                <Input
                                    id="brand-search"
                                    type="search"
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    placeholder="Search brands..."
                                    className="pl-9"
                                />
                            </div>

                            <div>
                                <label
                                    htmlFor="brand-status-filter"
                                    className="sr-only"
                                >
                                    Filter by status
                                </label>
                                <NativeSelect
                                    id="brand-status-filter"
                                    value={statusFilter}
                                    onChange={(event) =>
                                        setStatusFilter(
                                            event.target
                                                .value as BrandStatusFilter,
                                        )
                                    }
                                >
                                    <option value="all">All statuses</option>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </NativeSelect>
                            </div>

                            <div className="flex items-center gap-2 md:justify-end">
                                <p
                                    aria-live="polite"
                                    className="text-sm whitespace-nowrap text-muted-foreground"
                                >
                                    {brandCount}
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
                    </FilterToolbar>

                    {filteredBrands.length === 0 ? (
                        <div className="px-4 py-12 md:hidden">
                            <div className="mx-auto max-w-sm text-center">
                                <p className="font-medium">
                                    {hasFilters
                                        ? 'No brands match these filters.'
                                        : 'No inventory brands have been created.'}
                                </p>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {hasFilters
                                        ? 'Adjust or reset the filters to see more brands.'
                                        : canManage
                                          ? 'Create a brand to start organizing inventory items.'
                                          : 'Brands will appear here when they are available.'}
                                </p>
                            </div>
                        </div>
                    ) : (
                        <div className="divide-y divide-border md:hidden">
                            {filteredBrands.map((brand) => (
                                <article
                                    key={brand.id}
                                    className="space-y-4 p-4"
                                    aria-labelledby={`brand-${brand.id}-name`}
                                >
                                    <div className="flex items-start justify-between gap-3">
                                        <h2
                                            id={`brand-${brand.id}-name`}
                                            className="font-medium"
                                        >
                                            {brand.name}
                                        </h2>

                                        <InventoryBrandStatus
                                            active={brand.active}
                                        />
                                    </div>

                                    {canManage && (
                                        <div className="flex justify-end border-t border-border pt-3">
                                            <EditInventoryBrandDialog
                                                brand={brand}
                                                trigger={
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        aria-label={`Edit ${brand.name}`}
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
                        <table className="w-full min-w-[560px] text-sm">
                            <thead className="bg-muted/40 text-left text-xs text-muted-foreground">
                                <tr className="border-b border-border">
                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-medium"
                                    >
                                        Brand name
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
                                {filteredBrands.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={canManage ? 3 : 2}
                                            className="px-4 py-12 text-center"
                                        >
                                            <div className="mx-auto max-w-sm">
                                                <p className="font-medium">
                                                    {hasFilters
                                                        ? 'No brands match these filters.'
                                                        : 'No inventory brands have been created.'}
                                                </p>
                                                <p className="mt-1 text-sm text-muted-foreground">
                                                    {hasFilters
                                                        ? 'Adjust or reset the filters to see more brands.'
                                                        : canManage
                                                          ? 'Create a brand to start organizing inventory items.'
                                                          : 'Brands will appear here when they are available.'}
                                                </p>
                                            </div>
                                        </td>
                                    </tr>
                                ) : (
                                    filteredBrands.map((brand) => (
                                        <tr
                                            key={brand.id}
                                            className="border-b border-border transition-colors last:border-b-0 hover:bg-muted/30"
                                        >
                                            <td className="px-4 py-3">
                                                <span className="font-medium">
                                                    {brand.name}
                                                </span>
                                            </td>

                                            <td className="px-4 py-3">
                                                <InventoryBrandStatus
                                                    active={brand.active}
                                                />
                                            </td>

                                            {canManage && (
                                                <td className="px-4 py-2 text-right">
                                                    <EditInventoryBrandDialog
                                                        brand={brand}
                                                        trigger={
                                                            <Button
                                                                variant="outline"
                                                                size="sm"
                                                                aria-label={`Edit ${brand.name}`}
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

InventoryBrandsIndex.layout = {
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
