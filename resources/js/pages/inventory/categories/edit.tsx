import { Form, Head } from '@inertiajs/react';
import InventoryCategoryController from '@/actions/App/Http/Controllers/Inventory/InventoryCategoryController';
import InventoryItemController from '@/actions/App/Http/Controllers/Inventory/InventoryItemController';
import InputError from '@/components/input-error';
import { PreviousPageButton } from '@/components/navigation/previous-page-button';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import type { InventoryCategoryData } from '@/types';

type Props = {
    category: InventoryCategoryData;
};

export default function EditInventoryCategory({ category }: Props) {
    return (
        <>
            <Head title={`Edit ${category.name}`} />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div>
                    <h1 className="text-2xl font-semibold">
                        Edit inventory category
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {category.name}
                    </p>
                </div>

                <div className="max-w-xl rounded-xl border border-sidebar-border/70 p-5 dark:border-sidebar-border">
                    <Form
                        {...InventoryCategoryController.update.form(
                            category.id,
                        )}
                        className="space-y-5"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Name</Label>
                                    <Input
                                        id="name"
                                        name="name"
                                        defaultValue={category.name}
                                        required
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="active">Status</Label>
                                    <select
                                        id="active"
                                        name="active"
                                        defaultValue={
                                            category.active ? '1' : '0'
                                        }
                                        className="h-9 rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                    >
                                        <option value="1">Active</option>
                                        <option value="0">Inactive</option>
                                    </select>
                                    <InputError message={errors.active} />
                                </div>

                                <div className="flex gap-2">
                                    <Button type="submit" disabled={processing}>
                                        Save category
                                    </Button>

                                    <PreviousPageButton
                                        variant="outline"
                                        fallback={
                                            InventoryCategoryController.index()
                                                .url
                                        }
                                        disabled={processing}
                                    >
                                        Back
                                    </PreviousPageButton>
                                </div>
                            </>
                        )}
                    </Form>
                </div>
            </div>
        </>
    );
}

EditInventoryCategory.layout = {
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
