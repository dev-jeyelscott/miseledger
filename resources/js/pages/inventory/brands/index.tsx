import { Form, Head } from '@inertiajs/react';
import { Pencil, Plus, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import type { ReactNode } from 'react';
import InventoryBrandController from '@/actions/App/Http/Controllers/Inventory/InventoryBrandController';
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

                                <div className="grid gap-2">
                                    <Label htmlFor="create-brand-name">
                                        Name
                                    </Label>
                                    <Input
                                        id="create-brand-name"
                                        name="name"
                                        required
                                        autoFocus
                                        placeholder="e.g., Acme Foods"
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="create-brand-active">
                                        Status
                                    </Label>
                                    <select
                                        id="create-brand-active"
                                        name="active"
                                        defaultValue="1"
                                        aria-describedby="create-brand-status-help"
                                        className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                    >
                                        <option value="1">Active</option>
                                        <option value="0">Inactive</option>
                                    </select>
                                    <p
                                        id="create-brand-status-help"
                                        className="text-xs text-muted-foreground"
                                    >
                                        Inactive brands remain available for
                                        existing records but are excluded from
                                        new item brand choices.
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

    const statusHelpId = `brand-status-help-${brand.id}`;

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

                                <div className="grid gap-2">
                                    <Label htmlFor={`brand-name-${brand.id}`}>
                                        Name
                                    </Label>
                                    <Input
                                        id={`brand-name-${brand.id}`}
                                        name="name"
                                        defaultValue={brand.name}
                                        required
                                        autoFocus
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor={`brand-active-${brand.id}`}>
                                        Status
                                    </Label>
                                    <select
                                        id={`brand-active-${brand.id}`}
                                        name="active"
                                        defaultValue={brand.active ? '1' : '0'}
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
                                        Inactive brands remain available for
                                        existing records but are excluded from
                                        new item brand choices.
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
                                            : 'Save brand'}
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
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Inventory brands
                        </h1>
                        <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
                            Maintain organization-specific brands to keep
                            inventory master records easy to find and report on.
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2 sm:justify-end">
                        {canManage && (
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
                        )}
                    </div>
                </div>

                <section
                    aria-label="Inventory brands"
                    className="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border"
                >
                    <div className="grid gap-3 border-b border-sidebar-border/70 p-4 md:grid-cols-[minmax(0,1fr)_12rem_auto] md:items-center dark:border-sidebar-border">
                        <div className="relative">
                            <label htmlFor="brand-search" className="sr-only">
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
                            <select
                                id="brand-status-filter"
                                value={statusFilter}
                                onChange={(event) =>
                                    setStatusFilter(
                                        event.target.value as BrandStatusFilter,
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

                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[560px] text-sm">
                            <thead className="bg-muted/40 text-left text-xs text-muted-foreground">
                                <tr className="border-b border-sidebar-border/70 dark:border-sidebar-border">
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
                                            className="border-b border-sidebar-border/70 transition-colors last:border-b-0 hover:bg-muted/30 dark:border-sidebar-border"
                                        >
                                            <td className="px-4 py-3">
                                                <span className="font-medium">
                                                    {brand.name}
                                                </span>
                                            </td>

                                            <td className="px-4 py-3">
                                                <Badge
                                                    variant={
                                                        brand.active
                                                            ? 'secondary'
                                                            : 'outline'
                                                    }
                                                >
                                                    {brand.active
                                                        ? 'Active'
                                                        : 'Inactive'}
                                                </Badge>
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
