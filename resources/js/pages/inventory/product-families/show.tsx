import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import { Pencil, Plus, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import type { ReactNode } from 'react';
import InventoryItemController from '@/actions/App/Http/Controllers/Inventory/InventoryItemController';
import InventoryProductController from '@/actions/App/Http/Controllers/Inventory/InventoryProductController';
import InventoryProductOptionController from '@/actions/App/Http/Controllers/Inventory/InventoryProductOptionController';
import InventoryProductOptionValueController from '@/actions/App/Http/Controllers/Inventory/InventoryProductOptionValueController';
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

type OptionValue = {
    id: number;
    value: string;
    active: boolean;
};

type ProductOption = {
    id: number;
    name: string;
    active: boolean;
    values: OptionValue[];
};

type Variant = {
    id: number;
    name: string;
    description: string;
    sku: string;
    barcode: string | null;
    baseUnitOfMeasure: {
        id: number;
        name: string;
        symbol: string;
    };
    brand: {
        id: number;
        name: string;
    } | null;
    active: boolean;
};

type ProductFamily = {
    id: number;
    name: string;
    active: boolean;
    options: ProductOption[];
    variants: Variant[];
};

type Props = {
    productFamily: ProductFamily;
    canManage: boolean;
};

type CreateOptionDialogProps = {
    productFamilyId: number;
    trigger: ReactNode;
};

type EditOptionDialogProps = {
    productFamilyId: number;
    option: ProductOption;
    trigger: ReactNode;
};

type CreateOptionValueDialogProps = {
    productFamilyId: number;
    option: ProductOption;
    trigger: ReactNode;
};

type EditOptionValueDialogProps = {
    productFamilyId: number;
    option: ProductOption;
    value: OptionValue;
    trigger: ReactNode;
};

/** Render the canonical active or inactive semantic status. */
function ActiveStatus({ active }: { active: boolean }) {
    return (
        <StatusBadge
            label={active ? 'Active' : 'Inactive'}
            variant={active ? 'success' : 'neutral'}
        />
    );
}

/** Create a controlled option without exposing an always-editable form. */
function CreateOptionDialog({
    productFamilyId,
    trigger,
}: CreateOptionDialogProps) {
    const dialog = useGuardedDialog(
        'Discard the new product option you entered?',
    );

    return (
        <Dialog open={dialog.open} onOpenChange={dialog.onOpenChange}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>

            <DialogContent className="max-h-[calc(100vh-2rem)] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Add option</DialogTitle>
                    <DialogDescription>
                        Add a controlled dimension such as size, color, voltage,
                        or material.
                    </DialogDescription>
                </DialogHeader>

                <div onChange={dialog.markDirty}>
                    <Form
                        {...InventoryProductOptionController.store.form(
                            productFamilyId,
                        )}
                        errorBag="createProductOption"
                        resetOnSuccess
                        className="space-y-5"
                        onSuccess={dialog.closeAfterSuccess}
                    >
                        {({ processing, errors }) => (
                            <>
                                <Field
                                    id="create-product-option-name"
                                    label="Option name"
                                    error={errors.name}
                                >
                                    <Input
                                        name="name"
                                        required
                                        autoFocus
                                        placeholder="e.g., Size"
                                    />
                                </Field>

                                <input type="hidden" name="active" value="1" />

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
                                        {processing ? 'Adding…' : 'Add option'}
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

/** Edit one controlled option in an explicit guarded dialog. */
function EditOptionDialog({
    productFamilyId,
    option,
    trigger,
}: EditOptionDialogProps) {
    const dialog = useGuardedDialog(
        `Discard changes to the ${option.name} option?`,
    );

    return (
        <Dialog open={dialog.open} onOpenChange={dialog.onOpenChange}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>

            <DialogContent className="max-h-[calc(100vh-2rem)] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Edit option</DialogTitle>
                    <DialogDescription>
                        Update this controlled option without changing its
                        existing values or variant assignments.
                    </DialogDescription>
                </DialogHeader>

                <div onChange={dialog.markDirty}>
                    <Form
                        {...InventoryProductOptionController.update.form([
                            productFamilyId,
                            option.id,
                        ])}
                        errorBag={`editProductOption${option.id}`}
                        className="space-y-5"
                        onSuccess={dialog.closeAfterSuccess}
                    >
                        {({ processing, errors }) => (
                            <>
                                <Field
                                    id={`edit-product-option-name-${option.id}`}
                                    label="Option name"
                                    error={errors.name}
                                >
                                    <Input
                                        name="name"
                                        defaultValue={option.name}
                                        required
                                        autoFocus
                                    />
                                </Field>

                                <Field
                                    id={`edit-product-option-status-${option.id}`}
                                    label="Status"
                                    error={errors.active}
                                    helper="Inactive options remain part of existing product-family data but are not presented as active choices."
                                >
                                    <NativeSelect
                                        name="active"
                                        defaultValue={option.active ? '1' : '0'}
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
                                        {processing ? 'Saving…' : 'Save option'}
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

/** Add one value to an existing controlled product option. */
function CreateOptionValueDialog({
    productFamilyId,
    option,
    trigger,
}: CreateOptionValueDialogProps) {
    const dialog = useGuardedDialog(
        `Discard the new ${option.name} value you entered?`,
    );

    return (
        <Dialog open={dialog.open} onOpenChange={dialog.onOpenChange}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>

            <DialogContent className="max-h-[calc(100vh-2rem)] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Add {option.name} value</DialogTitle>
                    <DialogDescription>
                        Add an allowed value for the {option.name} option.
                    </DialogDescription>
                </DialogHeader>

                <div onChange={dialog.markDirty}>
                    <Form
                        {...InventoryProductOptionValueController.store.form([
                            productFamilyId,
                            option.id,
                        ])}
                        errorBag={`createProductOptionValue${option.id}`}
                        resetOnSuccess
                        className="space-y-5"
                        onSuccess={dialog.closeAfterSuccess}
                    >
                        {({ processing, errors }) => (
                            <>
                                <Field
                                    id={`create-product-option-value-${option.id}`}
                                    label="Value"
                                    error={errors.value}
                                >
                                    <Input
                                        name="value"
                                        required
                                        autoFocus
                                        placeholder="e.g., Small"
                                    />
                                </Field>

                                <input type="hidden" name="active" value="1" />

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
                                        {processing ? 'Adding…' : 'Add value'}
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

/** Edit one controlled option value in a guarded dialog. */
function EditOptionValueDialog({
    productFamilyId,
    option,
    value,
    trigger,
}: EditOptionValueDialogProps) {
    const dialog = useGuardedDialog(
        `Discard changes to the ${value.value} value?`,
    );

    return (
        <Dialog open={dialog.open} onOpenChange={dialog.onOpenChange}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>

            <DialogContent className="max-h-[calc(100vh-2rem)] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Edit {option.name} value</DialogTitle>
                    <DialogDescription>
                        Update this controlled value without changing product
                        family or variant containment.
                    </DialogDescription>
                </DialogHeader>

                <div onChange={dialog.markDirty}>
                    <Form
                        {...InventoryProductOptionValueController.update.form([
                            productFamilyId,
                            option.id,
                            value.id,
                        ])}
                        errorBag={`editProductOptionValue${value.id}`}
                        className="space-y-5"
                        onSuccess={dialog.closeAfterSuccess}
                    >
                        {({ processing, errors }) => (
                            <>
                                <Field
                                    id={`edit-product-option-value-${value.id}`}
                                    label="Value"
                                    error={errors.value}
                                >
                                    <Input
                                        name="value"
                                        defaultValue={value.value}
                                        required
                                        autoFocus
                                    />
                                </Field>

                                <Field
                                    id={`edit-product-option-value-status-${value.id}`}
                                    label="Status"
                                    error={errors.active}
                                    helper="Inactive values remain available to existing saved associations."
                                >
                                    <NativeSelect
                                        name="active"
                                        defaultValue={value.active ? '1' : '0'}
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
                                        {processing ? 'Saving…' : 'Save value'}
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

/** Render a product family, controlled options, and its inventory variants. */
export default function ProductFamilyShow({ productFamily, canManage }: Props) {
    const [variantSearch, setVariantSearch] = useState('');
    const normalizedVariantSearch = variantSearch.trim().toLowerCase();

    const filteredVariants = useMemo(() => {
        if (normalizedVariantSearch === '') {
            return productFamily.variants;
        }

        return productFamily.variants.filter((variant) =>
            [
                variant.description,
                variant.name,
                variant.sku,
                variant.barcode ?? '',
                variant.brand?.name ?? '',
                variant.baseUnitOfMeasure.name,
                variant.baseUnitOfMeasure.symbol,
            ].some((value) =>
                value.toLowerCase().includes(normalizedVariantSearch),
            ),
        );
    }, [normalizedVariantSearch, productFamily.variants]);

    setLayoutProps({
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            {
                title: 'Inventory',
                href: InventoryItemController.index(),
            },
            {
                title: 'Product families',
                href: InventoryProductController.index(),
            },
            {
                title: productFamily.name,
                href: InventoryProductController.show(productFamily.id),
            },
        ],
    });

    return (
        <>
            <Head title={productFamily.name} />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={productFamily.name}
                    description="Review this family’s controlled options and inventory variants."
                    actions={
                        <Button asChild variant="outline">
                            <Link href={InventoryProductController.index()}>
                                All product families
                            </Link>
                        </Button>
                    }
                />

                <div className="flex flex-wrap items-center gap-2 text-sm">
                    <span className="text-muted-foreground">Family status</span>
                    <ActiveStatus active={productFamily.active} />
                </div>

                {canManage && (
                    <section
                        aria-labelledby="family-details-heading"
                        className="rounded-xl border border-border bg-card p-4 md:p-6"
                    >
                        <h2
                            id="family-details-heading"
                            className="font-semibold"
                        >
                            Family details
                        </h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Update the family name or whether it is available
                            for active inventory configuration.
                        </p>

                        <Form
                            {...InventoryProductController.update.form(
                                productFamily.id,
                            )}
                            className="mt-5 grid gap-5 md:grid-cols-[minmax(0,1fr)_12rem_auto] md:items-end"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <Field
                                        id="product-family-name"
                                        label="Name"
                                        error={errors.name}
                                    >
                                        <Input
                                            name="name"
                                            defaultValue={productFamily.name}
                                            required
                                        />
                                    </Field>

                                    <Field
                                        id="product-family-active"
                                        label="Status"
                                        error={errors.active}
                                        helper="Inactive families remain visible on existing inventory records."
                                    >
                                        <NativeSelect
                                            name="active"
                                            defaultValue={
                                                productFamily.active ? '1' : '0'
                                            }
                                        >
                                            <option value="1">Active</option>
                                            <option value="0">Inactive</option>
                                        </NativeSelect>
                                    </Field>

                                    <Button type="submit" disabled={processing}>
                                        {processing ? 'Saving…' : 'Save family'}
                                    </Button>
                                </>
                            )}
                        </Form>
                    </section>
                )}

                <section
                    aria-labelledby="controlled-options-heading"
                    className="overflow-hidden rounded-xl border border-border bg-card"
                >
                    <div className="flex flex-wrap items-start justify-between gap-3 border-b border-border px-4 py-4 md:px-6">
                        <div>
                            <h2
                                id="controlled-options-heading"
                                className="font-semibold"
                            >
                                Controlled options
                            </h2>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Define the allowed dimensions and values used to
                                describe variants in this family.
                            </p>
                        </div>

                        {canManage && (
                            <CreateOptionDialog
                                productFamilyId={productFamily.id}
                                trigger={
                                    <Button size="sm">
                                        <Plus
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        Add option
                                    </Button>
                                }
                            />
                        )}
                    </div>

                    {productFamily.options.length === 0 ? (
                        <div className="px-4 py-10 text-center md:px-6">
                            <p className="font-medium">No options yet</p>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {canManage
                                    ? 'Add a controlled option such as size or color.'
                                    : 'Controlled options will appear here when configured.'}
                            </p>
                        </div>
                    ) : (
                        <div className="grid gap-4 p-4 md:p-6">
                            {productFamily.options.map((option) => (
                                <article
                                    key={option.id}
                                    className="rounded-xl border border-border"
                                    aria-labelledby={`option-${option.id}-heading`}
                                >
                                    <div className="flex flex-wrap items-start justify-between gap-3 border-b border-border p-4">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <h3
                                                id={`option-${option.id}-heading`}
                                                className="font-semibold"
                                            >
                                                {option.name}
                                            </h3>
                                            <ActiveStatus
                                                active={option.active}
                                            />
                                        </div>

                                        {canManage && (
                                            <EditOptionDialog
                                                productFamilyId={
                                                    productFamily.id
                                                }
                                                option={option}
                                                trigger={
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        aria-label={`Edit ${option.name} option`}
                                                    >
                                                        <Pencil
                                                            className="size-3.5"
                                                            aria-hidden="true"
                                                        />
                                                        Edit option
                                                    </Button>
                                                }
                                            />
                                        )}
                                    </div>

                                    <div className="p-4">
                                        <div className="flex flex-wrap items-center justify-between gap-3">
                                            <h4 className="text-sm font-medium">
                                                Allowed values
                                            </h4>

                                            {canManage && (
                                                <CreateOptionValueDialog
                                                    productFamilyId={
                                                        productFamily.id
                                                    }
                                                    option={option}
                                                    trigger={
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                        >
                                                            <Plus
                                                                className="size-3.5"
                                                                aria-hidden="true"
                                                            />
                                                            Add value
                                                        </Button>
                                                    }
                                                />
                                            )}
                                        </div>

                                        {option.values.length === 0 ? (
                                            <p className="mt-4 text-sm text-muted-foreground">
                                                No values configured for this
                                                option.
                                            </p>
                                        ) : (
                                            <ul className="mt-4 divide-y divide-border rounded-lg border border-border">
                                                {option.values.map((value) => (
                                                    <li
                                                        key={value.id}
                                                        className="flex flex-wrap items-center justify-between gap-3 px-3 py-3"
                                                    >
                                                        <div className="flex flex-wrap items-center gap-2">
                                                            <span className="text-sm font-medium">
                                                                {value.value}
                                                            </span>
                                                            <ActiveStatus
                                                                active={
                                                                    value.active
                                                                }
                                                            />
                                                        </div>

                                                        {canManage && (
                                                            <EditOptionValueDialog
                                                                productFamilyId={
                                                                    productFamily.id
                                                                }
                                                                option={option}
                                                                value={value}
                                                                trigger={
                                                                    <Button
                                                                        variant="ghost"
                                                                        size="sm"
                                                                        aria-label={`Edit ${value.value} value`}
                                                                    >
                                                                        <Pencil
                                                                            className="size-3.5"
                                                                            aria-hidden="true"
                                                                        />
                                                                        Edit
                                                                    </Button>
                                                                }
                                                            />
                                                        )}
                                                    </li>
                                                ))}
                                            </ul>
                                        )}
                                    </div>
                                </article>
                            ))}
                        </div>
                    )}
                </section>

                <section
                    aria-labelledby="variants-heading"
                    className="overflow-hidden rounded-xl border border-border bg-card"
                >
                    <div className="border-b border-border px-4 py-4 md:px-6">
                        <h2 id="variants-heading" className="font-semibold">
                            Variants
                        </h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Inventory details, SKU, barcode, units, and stock
                            remain owned by each inventory item.
                        </p>
                    </div>

                    <FilterToolbar className="rounded-none border-x-0 border-t-0">
                        <div className="relative">
                            <label htmlFor="variant-search" className="sr-only">
                                Search variants
                            </label>
                            <Search
                                className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <Input
                                id="variant-search"
                                type="search"
                                value={variantSearch}
                                onChange={(event) =>
                                    setVariantSearch(event.target.value)
                                }
                                placeholder="Search variants by name, SKU, barcode, brand, or unit..."
                                className="pl-9"
                            />
                        </div>
                    </FilterToolbar>

                    {filteredVariants.length === 0 ? (
                        <div className="px-4 py-10 text-center md:hidden">
                            <p className="font-medium">
                                {productFamily.variants.length === 0
                                    ? 'No variants yet'
                                    : 'No variants match this search.'}
                            </p>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {productFamily.variants.length === 0
                                    ? 'Associate an inventory item with this product family from its item form.'
                                    : 'Try a different variant name, SKU, barcode, brand, or unit.'}
                            </p>
                        </div>
                    ) : (
                        <div className="divide-y divide-border md:hidden">
                            {filteredVariants.map((variant) => (
                                <article
                                    key={variant.id}
                                    className="space-y-4 p-4"
                                    aria-labelledby={`variant-${variant.id}-name`}
                                >
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="min-w-0">
                                            <Link
                                                id={`variant-${variant.id}-name`}
                                                href={InventoryItemController.show(
                                                    variant.id,
                                                )}
                                                className="font-medium focus-visible:rounded-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                            >
                                                {variant.description}
                                            </Link>
                                            <p className="mt-1 text-sm text-muted-foreground">
                                                {variant.name}
                                            </p>
                                        </div>

                                        <ActiveStatus active={variant.active} />
                                    </div>

                                    <dl className="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                                        <div>
                                            <dt className="text-muted-foreground">
                                                Brand
                                            </dt>
                                            <dd className="mt-1">
                                                {variant.brand?.name ??
                                                    'Unbranded'}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt className="text-muted-foreground">
                                                Base unit
                                            </dt>
                                            <dd className="mt-1">
                                                {variant.baseUnitOfMeasure.name}{' '}
                                                (
                                                {
                                                    variant.baseUnitOfMeasure
                                                        .symbol
                                                }
                                                )
                                            </dd>
                                        </div>
                                        <div>
                                            <dt className="text-muted-foreground">
                                                SKU
                                            </dt>
                                            <dd className="mt-1 font-mono text-xs break-all">
                                                {variant.sku}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt className="text-muted-foreground">
                                                Barcode
                                            </dt>
                                            <dd className="mt-1 font-mono text-xs break-all">
                                                {variant.barcode ?? 'Not set'}
                                            </dd>
                                        </div>
                                    </dl>

                                    {canManage && (
                                        <div className="flex justify-end border-t border-border pt-3">
                                            <Button
                                                asChild
                                                variant="outline"
                                                size="sm"
                                            >
                                                <Link
                                                    href={InventoryItemController.edit(
                                                        variant.id,
                                                    )}
                                                >
                                                    <Pencil
                                                        className="size-3.5"
                                                        aria-hidden="true"
                                                    />
                                                    Edit item
                                                </Link>
                                            </Button>
                                        </div>
                                    )}
                                </article>
                            ))}
                        </div>
                    )}

                    <div className="hidden overflow-x-auto md:block">
                        <table className="w-full min-w-[56rem] text-sm">
                            <caption className="sr-only">
                                Inventory item variants for {productFamily.name}
                            </caption>
                            <thead className="bg-muted/40 text-left text-muted-foreground">
                                <tr>
                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-medium"
                                    >
                                        Variant description
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-medium"
                                    >
                                        Brand
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-medium"
                                    >
                                        SKU
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-medium"
                                    >
                                        Barcode
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-medium"
                                    >
                                        Base unit
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-medium"
                                    >
                                        Status
                                    </th>
                                    {canManage && (
                                        <th
                                            scope="col"
                                            className="px-4 py-3 text-right font-medium"
                                        >
                                            Actions
                                        </th>
                                    )}
                                </tr>
                            </thead>
                            <tbody>
                                {filteredVariants.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={canManage ? 7 : 6}
                                            className="px-4 py-10 text-center"
                                        >
                                            <p className="font-medium">
                                                {productFamily.variants
                                                    .length === 0
                                                    ? 'No variants yet'
                                                    : 'No variants match this search.'}
                                            </p>
                                            <p className="mt-1 text-sm text-muted-foreground">
                                                {productFamily.variants
                                                    .length === 0
                                                    ? 'Associate an inventory item with this product family from its item form.'
                                                    : 'Try a different variant name, SKU, barcode, brand, or unit.'}
                                            </p>
                                        </td>
                                    </tr>
                                ) : (
                                    filteredVariants.map((variant) => (
                                        <tr
                                            key={variant.id}
                                            className="border-t border-border transition-colors hover:bg-muted/30"
                                        >
                                            <td className="px-4 py-3">
                                                <Link
                                                    href={InventoryItemController.show(
                                                        variant.id,
                                                    )}
                                                    className="font-medium focus-visible:rounded-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                                >
                                                    {variant.description}
                                                </Link>
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    {variant.name}
                                                </p>
                                            </td>
                                            <td className="px-4 py-3">
                                                {variant.brand?.name ??
                                                    'Unbranded'}
                                            </td>
                                            <td className="px-4 py-3 font-mono text-xs">
                                                {variant.sku}
                                            </td>
                                            <td className="px-4 py-3 font-mono text-xs">
                                                {variant.barcode ?? 'Not set'}
                                            </td>
                                            <td className="px-4 py-3">
                                                {variant.baseUnitOfMeasure.name}{' '}
                                                (
                                                {
                                                    variant.baseUnitOfMeasure
                                                        .symbol
                                                }
                                                )
                                            </td>
                                            <td className="px-4 py-3">
                                                <ActiveStatus
                                                    active={variant.active}
                                                />
                                            </td>
                                            {canManage && (
                                                <td className="px-4 py-3 text-right">
                                                    <Button
                                                        asChild
                                                        variant="outline"
                                                        size="sm"
                                                    >
                                                        <Link
                                                            href={InventoryItemController.edit(
                                                                variant.id,
                                                            )}
                                                        >
                                                            <Pencil
                                                                className="size-3.5"
                                                                aria-hidden="true"
                                                            />
                                                            Edit
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
                </section>
            </div>
        </>
    );
}
