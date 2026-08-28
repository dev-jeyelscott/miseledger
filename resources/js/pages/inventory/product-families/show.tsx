import { Form, Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import InventoryItemController from '@/actions/App/Http/Controllers/Inventory/InventoryItemController';
import InventoryProductController from '@/actions/App/Http/Controllers/Inventory/InventoryProductController';
import InventoryProductOptionController from '@/actions/App/Http/Controllers/Inventory/InventoryProductOptionController';
import InventoryProductOptionValueController from '@/actions/App/Http/Controllers/Inventory/InventoryProductOptionValueController';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';

type OptionValue = { id: number; value: string; active: boolean };
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
    baseUnitOfMeasure: { id: number; name: string; symbol: string };
    brand: { id: number; name: string } | null;
    active: boolean;
};
type ProductFamily = {
    id: number;
    name: string;
    active: boolean;
    options: ProductOption[];
    variants: Variant[];
};
type Props = { productFamily: ProductFamily; canManage: boolean };

export default function ProductFamilyShow({ productFamily, canManage }: Props) {
    return (
        <>
            <Head title={productFamily.name} />

            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {productFamily.name}
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Manage this family’s controlled options and review
                            the inventory items associated with it.
                        </p>
                    </div>
                    <Button asChild variant="outline">
                        <Link href={InventoryProductController.index()}>
                            All product families
                        </Link>
                    </Button>
                </div>

                {canManage && (
                    <section className="rounded-xl border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
                        <h2 className="text-sm font-semibold">
                            Family details
                        </h2>
                        <Form
                            {...InventoryProductController.update.form(
                                productFamily.id,
                            )}
                            className="mt-4 grid gap-4 sm:grid-cols-[minmax(0,1fr)_10rem_auto] sm:items-end"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="product-family-name">
                                            Name
                                        </Label>
                                        <Input
                                            id="product-family-name"
                                            name="name"
                                            defaultValue={productFamily.name}
                                            required
                                        />
                                        <InputError message={errors.name} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="product-family-active">
                                            Status
                                        </Label>
                                        <select
                                            id="product-family-active"
                                            name="active"
                                            defaultValue={
                                                productFamily.active ? '1' : '0'
                                            }
                                            className="h-9 rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                        >
                                            <option value="1">Active</option>
                                            <option value="0">Inactive</option>
                                        </select>
                                        <InputError message={errors.active} />
                                    </div>
                                    <Button type="submit" disabled={processing}>
                                        {processing
                                            ? 'Saving...'
                                            : 'Save family'}
                                    </Button>
                                </>
                            )}
                        </Form>
                    </section>
                )}

                <section className="rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border">
                    <div className="border-b border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border">
                        <h2 className="text-sm font-semibold">
                            Controlled options
                        </h2>
                        <p className="mt-1 text-xs text-muted-foreground">
                            Define the allowed dimensions and values used to
                            describe variants in this family.
                        </p>
                    </div>
                    <div className="grid gap-4 p-4">
                        {canManage && (
                            <Form
                                {...InventoryProductOptionController.store.form(
                                    productFamily.id,
                                )}
                                className="grid gap-3 rounded-lg border border-border p-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <div className="grid gap-2">
                                            <Label htmlFor="new-option-name">
                                                Add option
                                            </Label>
                                            <Input
                                                id="new-option-name"
                                                name="name"
                                                placeholder="e.g., Size"
                                                required
                                            />
                                            <input
                                                type="hidden"
                                                name="active"
                                                value="1"
                                            />
                                            <InputError message={errors.name} />
                                        </div>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            <Plus
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                            {processing
                                                ? 'Adding...'
                                                : 'Add option'}
                                        </Button>
                                    </>
                                )}
                            </Form>
                        )}

                        {productFamily.options.length === 0 ? (
                            <div className="py-6 text-center">
                                <p className="font-medium">No options yet</p>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {canManage
                                        ? 'Add an option such as size or color to control variant values.'
                                        : 'Controlled options will appear here when configured.'}
                                </p>
                            </div>
                        ) : (
                            productFamily.options.map((option) => (
                                <div
                                    key={option.id}
                                    className="rounded-lg border border-border p-4"
                                >
                                    <div className="flex flex-wrap items-center justify-between gap-3">
                                        <div className="flex items-center gap-2">
                                            <h3 className="font-semibold">
                                                {option.name}
                                            </h3>
                                            <Badge
                                                variant={
                                                    option.active
                                                        ? 'default'
                                                        : 'secondary'
                                                }
                                            >
                                                {option.active
                                                    ? 'Active'
                                                    : 'Inactive'}
                                            </Badge>
                                        </div>
                                        {canManage && (
                                            <Form
                                                {...InventoryProductOptionController.update.form(
                                                    [
                                                        productFamily.id,
                                                        option.id,
                                                    ],
                                                )}
                                                className="flex flex-wrap items-end gap-2"
                                            >
                                                {({ processing, errors }) => (
                                                    <>
                                                        <div className="grid gap-1">
                                                            <Label
                                                                className="sr-only"
                                                                htmlFor={`option-name-${option.id}`}
                                                            >
                                                                Option name
                                                            </Label>
                                                            <Input
                                                                id={`option-name-${option.id}`}
                                                                name="name"
                                                                defaultValue={
                                                                    option.name
                                                                }
                                                                required
                                                            />
                                                            <InputError
                                                                message={
                                                                    errors.name
                                                                }
                                                            />
                                                        </div>
                                                        <select
                                                            name="active"
                                                            defaultValue={
                                                                option.active
                                                                    ? '1'
                                                                    : '0'
                                                            }
                                                            className="h-9 rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                                        >
                                                            <option value="1">
                                                                Active
                                                            </option>
                                                            <option value="0">
                                                                Inactive
                                                            </option>
                                                        </select>
                                                        <Button
                                                            type="submit"
                                                            variant="outline"
                                                            disabled={
                                                                processing
                                                            }
                                                        >
                                                            Save
                                                        </Button>
                                                    </>
                                                )}
                                            </Form>
                                        )}
                                    </div>
                                    <div
                                        className="mt-4 flex flex-wrap gap-2"
                                        aria-label={`${option.name} values`}
                                    >
                                        {option.values.map((value) =>
                                            canManage ? (
                                                <Form
                                                    key={value.id}
                                                    {...InventoryProductOptionValueController.update.form(
                                                        [
                                                            productFamily.id,
                                                            option.id,
                                                            value.id,
                                                        ],
                                                    )}
                                                    className="flex items-end gap-1"
                                                >
                                                    {({
                                                        processing,
                                                        errors,
                                                    }) => (
                                                        <>
                                                            <div className="grid gap-1">
                                                                <Label
                                                                    className="sr-only"
                                                                    htmlFor={`option-value-name-${value.id}`}
                                                                >
                                                                    {
                                                                        option.name
                                                                    }{' '}
                                                                    value
                                                                </Label>
                                                                <Input
                                                                    id={`option-value-name-${value.id}`}
                                                                    name="value"
                                                                    defaultValue={
                                                                        value.value
                                                                    }
                                                                    required
                                                                    className="h-8 w-28"
                                                                />
                                                                <InputError
                                                                    message={
                                                                        errors.value
                                                                    }
                                                                />
                                                            </div>
                                                            <select
                                                                aria-label={`${value.value} status`}
                                                                name="active"
                                                                defaultValue={
                                                                    value.active
                                                                        ? '1'
                                                                        : '0'
                                                                }
                                                                className="h-8 rounded-md border border-input bg-background px-2 text-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                                            >
                                                                <option value="1">
                                                                    Active
                                                                </option>
                                                                <option value="0">
                                                                    Inactive
                                                                </option>
                                                            </select>
                                                            <Button
                                                                type="submit"
                                                                variant="ghost"
                                                                size="sm"
                                                                disabled={
                                                                    processing
                                                                }
                                                            >
                                                                Save
                                                            </Button>
                                                        </>
                                                    )}
                                                </Form>
                                            ) : (
                                                <Badge
                                                    key={value.id}
                                                    variant={
                                                        value.active
                                                            ? 'outline'
                                                            : 'secondary'
                                                    }
                                                >
                                                    {value.value}
                                                    {!value.active
                                                        ? ' (inactive)'
                                                        : ''}
                                                </Badge>
                                            ),
                                        )}
                                    </div>
                                    {canManage && (
                                        <Form
                                            {...InventoryProductOptionValueController.store.form(
                                                [productFamily.id, option.id],
                                            )}
                                            className="mt-4 flex flex-wrap items-end gap-2"
                                        >
                                            {({ processing, errors }) => (
                                                <>
                                                    <div className="grid gap-1">
                                                        <Label
                                                            htmlFor={`option-value-${option.id}`}
                                                        >
                                                            Add value
                                                        </Label>
                                                        <Input
                                                            id={`option-value-${option.id}`}
                                                            name="value"
                                                            required
                                                            placeholder="e.g., Small"
                                                        />
                                                        <InputError
                                                            message={
                                                                errors.value
                                                            }
                                                        />
                                                    </div>
                                                    <input
                                                        type="hidden"
                                                        name="active"
                                                        value="1"
                                                    />
                                                    <Button
                                                        type="submit"
                                                        variant="outline"
                                                        disabled={processing}
                                                    >
                                                        {processing
                                                            ? 'Adding...'
                                                            : 'Add value'}
                                                    </Button>
                                                </>
                                            )}
                                        </Form>
                                    )}
                                </div>
                            ))
                        )}
                    </div>
                </section>

                <section className="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border">
                    <div className="border-b border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border">
                        <h2 className="text-sm font-semibold">Variants</h2>
                        <p className="mt-1 text-xs text-muted-foreground">
                            Item details remain owned and edited in the
                            inventory item record.
                        </p>
                    </div>
                    <div className="overflow-x-auto">
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
                                </tr>
                            </thead>
                            <tbody>
                                {productFamily.variants.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={6}
                                            className="px-4 py-10 text-center"
                                        >
                                            <p className="font-medium">
                                                No variants yet
                                            </p>
                                            <p className="mt-1 text-sm text-muted-foreground">
                                                Associate an existing inventory
                                                item with this product family
                                                from its item form.
                                            </p>
                                        </td>
                                    </tr>
                                ) : (
                                    productFamily.variants.map((variant) => (
                                        <tr
                                            key={variant.id}
                                            className="border-t border-sidebar-border/70 hover:bg-muted/30 dark:border-sidebar-border"
                                        >
                                            <td className="px-4 py-3">
                                                <Link
                                                    href={InventoryItemController.edit(
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
                                                {variant.brand?.name ?? '—'}
                                            </td>
                                            <td className="px-4 py-3 font-mono text-xs">
                                                {variant.sku}
                                            </td>
                                            <td className="px-4 py-3 font-mono text-xs">
                                                {variant.barcode ?? '—'}
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
                                                <Badge
                                                    variant={
                                                        variant.active
                                                            ? 'default'
                                                            : 'secondary'
                                                    }
                                                >
                                                    {variant.active
                                                        ? 'Active'
                                                        : 'Inactive'}
                                                </Badge>
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

ProductFamilyShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Inventory', href: InventoryItemController.index() },
        { title: 'Product families', href: InventoryProductController.index() },
    ],
};
