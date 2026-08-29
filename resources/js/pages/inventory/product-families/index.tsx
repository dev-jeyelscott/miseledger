import { Form, Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import InventoryItemController from '@/actions/App/Http/Controllers/Inventory/InventoryItemController';
import InventoryProductController from '@/actions/App/Http/Controllers/Inventory/InventoryProductController';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { dashboard } from '@/routes';

type ProductFamily = {
    id: number;
    name: string;
    active: boolean;
    variantCount: number;
};

type Props = {
    productFamilies: ProductFamily[];
    canManage: boolean;
};

export default function ProductFamiliesIndex({
    productFamilies,
    canManage,
}: Props) {
    return (
        <>
            <Head title="Product families" />

            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <PageHeader
                    title="Product families"
                    description="Group related inventory variants and define the controlled options they use."
                />

                {canManage && (
                    <section className="rounded-xl border border-border bg-card p-5">
                        <h2 className="text-sm font-semibold">
                            Create product family
                        </h2>
                        <Form
                            {...InventoryProductController.store.form()}
                            className="mt-4 grid gap-4 sm:grid-cols-[minmax(0,1fr)_10rem_auto] sm:items-end"
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
                                            required
                                            placeholder="e.g., Cordless drills"
                                        />
                                    </Field>

                                    <Field
                                        id="product-family-active"
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

                                    <Button type="submit" disabled={processing}>
                                        <Plus
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        {processing ? 'Creating…' : 'Create'}
                                    </Button>
                                </>
                            )}
                        </Form>
                    </section>
                )}

                <section className="overflow-hidden rounded-xl border border-border bg-card">
                    <div className="border-b border-border px-4 py-3">
                        <h2 className="text-sm font-semibold">
                            Available families
                        </h2>
                    </div>

                    {productFamilies.length === 0 ? (
                        <div className="px-4 py-10 text-center md:hidden">
                            <p className="font-medium">
                                No product families yet
                            </p>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {canManage
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
                                                No product families yet
                                            </p>
                                            <p className="mt-1 text-sm text-muted-foreground">
                                                {canManage
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
