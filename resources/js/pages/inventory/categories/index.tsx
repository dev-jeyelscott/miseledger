import { Form, Head, Link } from '@inertiajs/react';
import InventoryItemController from '@/actions/App/Http/Controllers/Inventory/InventoryItemController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import type { InventoryCategoryData } from '@/types';
import InventoryCategoryController from '@/actions/App/Http/Controllers/Inventory/InventoryCategoryController';

type Props = {
    categories: InventoryCategoryData[];
    canManage: boolean;
};

export default function InventoryCategoriesIndex({
    categories,
    canManage,
}: Props) {
    return (
        <>
            <Head title="Inventory categories" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div>
                    <h1 className="text-2xl font-semibold">
                        Inventory categories
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Organize inventory with flat, organization-specific
                        categories.
                    </p>
                </div>

                <div
                    className={
                        canManage
                            ? 'grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px]'
                            : ''
                    }
                >
                    <div className="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                        <div className="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                            {categories.length === 0 ? (
                                <div className="px-5 py-10 text-center text-sm text-muted-foreground">
                                    No inventory categories have been created.
                                </div>
                            ) : (
                                categories.map((category) => (
                                    <div
                                        key={category.id}
                                        className="flex items-center justify-between gap-4 px-5 py-4"
                                    >
                                        <div>
                                            <p className="font-medium">
                                                {category.name}
                                            </p>
                                            <p className="mt-1 text-sm text-muted-foreground">
                                                {category.active
                                                    ? 'Active'
                                                    : 'Inactive'}
                                            </p>
                                        </div>

                                        {canManage && (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                asChild
                                            >
                                                <Link
                                                    href={InventoryCategoryController.edit(
                                                        category.id,
                                                    )}
                                                >
                                                    Edit
                                                </Link>
                                            </Button>
                                        )}
                                    </div>
                                ))
                            )}
                        </div>
                    </div>

                    {canManage && (
                        <div className="h-fit rounded-xl border border-sidebar-border/70 p-5 dark:border-sidebar-border">
                            <h2 className="mb-5 font-medium">
                                Create category
                            </h2>

                            <Form
                                {...InventoryCategoryController.store.form()}
                                className="space-y-5"
                                resetOnSuccess
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <input
                                            type="hidden"
                                            name="active"
                                            value="1"
                                        />

                                        <div className="grid gap-2">
                                            <Label htmlFor="name">Name</Label>
                                            <Input
                                                id="name"
                                                name="name"
                                                required
                                                placeholder="Dry goods"
                                            />
                                            <InputError message={errors.name} />
                                        </div>

                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            Create category
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </div>
                    )}
                </div>

                <div>
                    <Button variant="outline" asChild>
                        <Link href={InventoryItemController.index()}>
                            Back to inventory
                        </Link>
                    </Button>
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
