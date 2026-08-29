import { Form, Head } from '@inertiajs/react';
import InventoryCategoryController from '@/actions/App/Http/Controllers/Inventory/InventoryCategoryController';
import InventoryItemController from '@/actions/App/Http/Controllers/Inventory/InventoryItemController';
import { PreviousPageButton } from '@/components/navigation/previous-page-button';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
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
                <PageHeader
                    title="Edit inventory category"
                    description={category.name}
                />

                <div className="max-w-xl rounded-xl border border-border p-5">
                    <Form
                        {...InventoryCategoryController.update.form(
                            category.id,
                        )}
                        className="space-y-5"
                    >
                        {({ processing, errors }) => (
                            <>
                                <Field
                                    id="name"
                                    label="Name"
                                    error={errors.name}
                                >
                                    <Input
                                        name="name"
                                        defaultValue={category.name}
                                        required
                                    />
                                </Field>

                                <Field
                                    id="active"
                                    label="Status"
                                    error={errors.active}
                                >
                                    <NativeSelect
                                        name="active"
                                        defaultValue={
                                            category.active ? '1' : '0'
                                        }
                                    >
                                        <option value="1">Active</option>
                                        <option value="0">Inactive</option>
                                    </NativeSelect>
                                </Field>

                                <div className="flex gap-2">
                                    <Button type="submit" disabled={processing}>
                                        {processing
                                            ? 'Saving…'
                                            : 'Save category'}
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
