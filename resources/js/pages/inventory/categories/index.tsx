import { Form, Head } from '@inertiajs/react';
import InventoryCategoryController from '@/actions/App/Http/Controllers/Inventory/InventoryCategoryController';
import InventoryItemController from '@/actions/App/Http/Controllers/Inventory/InventoryItemController';
import InputError from '@/components/input-error';
import { PreviousPageButton } from '@/components/navigation/previous-page-button';
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
import type { InventoryCategoryData } from '@/types';

type Props = {
    categories: InventoryCategoryData[];
    canManage: boolean;
};

type EditInventoryCategoryDialogProps = {
    category: InventoryCategoryData;
    trigger: React.ReactNode;
};

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
                    <DialogDescription>{category.name}</DialogDescription>
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

                                <div className="grid gap-2">
                                    <Label
                                        htmlFor={`category-name-${category.id}`}
                                    >
                                        Name
                                    </Label>
                                    <Input
                                        id={`category-name-${category.id}`}
                                        name="name"
                                        defaultValue={category.name}
                                        required
                                        autoFocus
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label
                                        htmlFor={`category-active-${category.id}`}
                                    >
                                        Status
                                    </Label>
                                    <select
                                        id={`category-active-${category.id}`}
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

                                <div className="flex flex-wrap gap-2">
                                    <Button type="submit" disabled={processing}>
                                        {processing
                                            ? 'Saving...'
                                            : 'Save category'}
                                    </Button>
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
                                </div>
                            </>
                        )}
                    </Form>
                </div>
            </DialogContent>
        </Dialog>
    );
}

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
                                            <EditInventoryCategoryDialog
                                                category={category}
                                                trigger={
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                    >
                                                        Edit
                                                    </Button>
                                                }
                                            />
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
    ],
};
