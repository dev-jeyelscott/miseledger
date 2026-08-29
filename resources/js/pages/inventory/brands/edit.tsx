import { Form, Head } from '@inertiajs/react';
import InventoryBrandController from '@/actions/App/Http/Controllers/Inventory/InventoryBrandController';
import InventoryItemController from '@/actions/App/Http/Controllers/Inventory/InventoryItemController';
import { PreviousPageButton } from '@/components/navigation/previous-page-button';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { dashboard } from '@/routes';
import type { InventoryBrandData } from '@/types';

type Props = {
    brand: InventoryBrandData;
};

export default function EditInventoryBrand({ brand }: Props) {
    return (
        <>
            <Head title={`Edit ${brand.name}`} />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <PageHeader
                    title="Edit inventory brand"
                    description={brand.name}
                />

                <div className="max-w-xl rounded-xl border border-border p-5">
                    <Form
                        {...InventoryBrandController.update.form(brand.id)}
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
                                        defaultValue={brand.name}
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
                                        defaultValue={brand.active ? '1' : '0'}
                                    >
                                        <option value="1">Active</option>
                                        <option value="0">Inactive</option>
                                    </NativeSelect>
                                </Field>

                                <div className="flex gap-2">
                                    <Button type="submit" disabled={processing}>
                                        {processing ? 'Saving…' : 'Save brand'}
                                    </Button>

                                    <PreviousPageButton
                                        variant="outline"
                                        fallback={
                                            InventoryBrandController.index().url
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

EditInventoryBrand.layout = {
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
            title: 'Brands',
            href: InventoryBrandController.index(),
        },
    ],
};
