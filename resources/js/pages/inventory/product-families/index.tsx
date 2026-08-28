import { Form, Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import InventoryProductController from '@/actions/App/Http/Controllers/Inventory/InventoryProductController';
import InventoryItemController from '@/actions/App/Http/Controllers/Inventory/InventoryItemController';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Product families
                        </h1>
                        <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
                            Group related inventory variants and define the
                            controlled options they use.
                        </p>
                    </div>
                </div>

                {canManage && (
                    <section className="rounded-xl border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
                        <h2 className="text-sm font-semibold">
                            Create product family
                        </h2>
                        <Form
                            {...InventoryProductController.store.form()}
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
                                            required
                                            placeholder="e.g., Cordless drills"
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
                                            defaultValue="1"
                                            className="h-9 rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                        >
                                            <option value="1">Active</option>
                                            <option value="0">Inactive</option>
                                        </select>
                                        <InputError message={errors.active} />
                                    </div>

                                    <Button type="submit" disabled={processing}>
                                        <Plus
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        {processing ? 'Creating...' : 'Create'}
                                    </Button>
                                </>
                            )}
                        </Form>
                    </section>
                )}

                <section className="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border">
                    <div className="border-b border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border">
                        <h2 className="text-sm font-semibold">
                            Available families
                        </h2>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[38rem] text-sm">
                            <caption className="sr-only">
                                Product families available to this organization
                            </caption>
                            <thead className="bg-muted/40 text-left text-muted-foreground">
                                <tr>
                                    <th scope="col" className="px-4 py-3 font-medium">
                                        Product family
                                    </th>
                                    <th scope="col" className="px-4 py-3 font-medium">
                                        Variants
                                    </th>
                                    <th scope="col" className="px-4 py-3 font-medium">
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
                                            className="border-t border-sidebar-border/70 hover:bg-muted/30 dark:border-sidebar-border"
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
                                                <Badge
                                                    variant={
                                                        productFamily.active
                                                            ? 'default'
                                                            : 'secondary'
                                                    }
                                                >
                                                    {productFamily.active
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

ProductFamiliesIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Inventory', href: InventoryItemController.index() },
        { title: 'Product families', href: InventoryProductController.index() },
    ],
};
