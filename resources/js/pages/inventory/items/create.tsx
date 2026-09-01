import { Form, Head, Link } from '@inertiajs/react';
import { useEffect } from 'react';
import InventoryItemController from '@/actions/App/Http/Controllers/Inventory/InventoryItemController';
import UnitOfMeasureController from '@/actions/App/Http/Controllers/Inventory/UnitOfMeasureController';
import { PreviousPageButton } from '@/components/navigation/previous-page-button';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { UsageLimitNotice } from '@/components/usage-limit-notice';
import { useDirtyFormNavigation } from '@/hooks/use-dirty-form-navigation';
import { dashboard } from '@/routes';
import type {
    InventoryBrandData,
    InventoryCategoryData,
    InventoryProductData,
    UnitOfMeasureData,
} from '@/types';

const textareaClassName =
    'min-h-24 w-full resize-y rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none transition-[color,box-shadow] placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive aria-invalid:ring-[3px] aria-invalid:ring-destructive/20 disabled:cursor-not-allowed disabled:opacity-50';

type Props = {
    units: UnitOfMeasureData[];
    categories: InventoryCategoryData[];
    brands: InventoryBrandData[];
    productFamilies: InventoryProductData[];
};

type DirtyStateTrackerProps = {
    dirty: boolean;
    onChange: (dirty: boolean) => void;
};

/** Keep the page navigation guard synchronized with Inertia Form dirty state. */
function DirtyStateTracker({ dirty, onChange }: DirtyStateTrackerProps) {
    useEffect(() => {
        onChange(dirty);
    }, [dirty, onChange]);

    return null;
}

export default function CreateInventoryItem({
    units,
    categories,
    brands,
    productFamilies,
}: Props) {
    const dirtyFormNavigation = useDirtyFormNavigation(
        'You have unsaved inventory item changes. Leave without saving them?',
    );

    return (
        <>
            <Head title="Create inventory item" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Create inventory item"
                    description="Add the master details and unit used to record this item’s stock."
                />

                <UsageLimitNotice
                    limitKey="inventory_items"
                    resourceLabel="inventory items"
                />

                {units.length === 0 ? (
                    <div className="max-w-xl rounded-xl bg-card p-4 shadow-sm md:p-6">
                        <p className="text-sm text-muted-foreground">
                            Create at least one active unit of measure before
                            creating an inventory item.
                        </p>

                        <Button className="mt-4" asChild>
                            <Link href={UnitOfMeasureController.index()}>
                                Manage units
                            </Link>
                        </Button>
                    </div>
                ) : (
                    <div className="max-w-4xl">
                        <Form
                            {...InventoryItemController.store.form()}
                            className="space-y-6"
                        >
                            {({ processing, errors, isDirty }) => (
                                <>
                                    <DirtyStateTracker
                                        dirty={isDirty}
                                        onChange={
                                            dirtyFormNavigation.setIsDirty
                                        }
                                    />

                                    <input
                                        type="hidden"
                                        name="active"
                                        value="1"
                                    />

                                    <section
                                        aria-labelledby="identity-heading"
                                        className="rounded-xl bg-card p-4 shadow-sm md:p-6"
                                    >
                                        <div>
                                            <h2
                                                id="identity-heading"
                                                className="font-semibold"
                                            >
                                                Identity
                                            </h2>
                                            <p className="mt-1 text-sm text-muted-foreground">
                                                Use the name and SKU your team
                                                will recognize when recording
                                                stock.
                                            </p>
                                        </div>

                                        <div className="mt-5 grid gap-5 md:grid-cols-2">
                                            <Field
                                                id="name"
                                                label="Name"
                                                error={errors.name}
                                            >
                                                <Input
                                                    name="name"
                                                    required
                                                    autoFocus
                                                    placeholder="All-purpose flour"
                                                />
                                            </Field>

                                            <Field
                                                id="sku"
                                                label="SKU"
                                                error={errors.sku}
                                            >
                                                <Input
                                                    name="sku"
                                                    required
                                                    autoComplete="off"
                                                    placeholder="FLOUR-001"
                                                />
                                            </Field>
                                        </div>
                                    </section>

                                    <section
                                        aria-labelledby="classification-heading"
                                        className="rounded-xl bg-card p-4 shadow-sm md:p-6"
                                    >
                                        <div>
                                            <h2
                                                id="classification-heading"
                                                className="font-semibold"
                                            >
                                                Classification
                                            </h2>
                                            <p className="mt-1 text-sm text-muted-foreground">
                                                Categorize the item for
                                                reporting and optionally
                                                associate it with a product
                                                family.
                                            </p>
                                        </div>

                                        <div className="mt-5 grid gap-5 md:grid-cols-2">
                                            <Field
                                                id="type"
                                                label="Item type"
                                                error={errors.type}
                                            >
                                                <NativeSelect
                                                    name="type"
                                                    defaultValue="ingredient"
                                                    required
                                                >
                                                    <option value="ingredient">
                                                        Ingredient
                                                    </option>
                                                    <option value="finished_item">
                                                        Finished item
                                                    </option>
                                                    <option value="prepared_item">
                                                        Prepared item
                                                    </option>
                                                    <option value="packaging">
                                                        Packaging
                                                    </option>
                                                    <option value="consumable">
                                                        Consumable
                                                    </option>
                                                </NativeSelect>
                                            </Field>

                                            <Field
                                                id="inventory_category_id"
                                                label="Category (optional)"
                                                error={
                                                    errors.inventory_category_id
                                                }
                                            >
                                                <NativeSelect
                                                    name="inventory_category_id"
                                                    defaultValue=""
                                                >
                                                    <option value="">
                                                        Uncategorized
                                                    </option>
                                                    {categories.map(
                                                        (category) => (
                                                            <option
                                                                key={
                                                                    category.id
                                                                }
                                                                value={
                                                                    category.id
                                                                }
                                                            >
                                                                {category.name}
                                                            </option>
                                                        ),
                                                    )}
                                                </NativeSelect>
                                            </Field>

                                            <Field
                                                id="inventory_brand_id"
                                                label="Brand (optional)"
                                                error={
                                                    errors.inventory_brand_id
                                                }
                                            >
                                                <NativeSelect
                                                    name="inventory_brand_id"
                                                    defaultValue=""
                                                >
                                                    <option value="">
                                                        No brand
                                                    </option>
                                                    {brands.map((brand) => (
                                                        <option
                                                            key={brand.id}
                                                            value={brand.id}
                                                        >
                                                            {brand.name}
                                                        </option>
                                                    ))}
                                                </NativeSelect>
                                            </Field>

                                            <Field
                                                id="inventory_product_id"
                                                label="Product family (optional)"
                                                helper="Associate related variants with a product family before assigning its option values."
                                                error={
                                                    errors.inventory_product_id
                                                }
                                            >
                                                <NativeSelect
                                                    name="inventory_product_id"
                                                    defaultValue=""
                                                >
                                                    <option value="">
                                                        No product family
                                                    </option>
                                                    {productFamilies.map(
                                                        (product) => (
                                                            <option
                                                                key={product.id}
                                                                value={
                                                                    product.id
                                                                }
                                                            >
                                                                {product.name}
                                                            </option>
                                                        ),
                                                    )}
                                                </NativeSelect>
                                            </Field>
                                        </div>
                                    </section>

                                    <section
                                        aria-labelledby="product-details-heading"
                                        className="rounded-xl bg-card p-4 shadow-sm md:p-6"
                                    >
                                        <div>
                                            <h2
                                                id="product-details-heading"
                                                className="font-semibold"
                                            >
                                                Product details
                                            </h2>
                                            <p className="mt-1 text-sm text-muted-foreground">
                                                Add optional manufacturer and
                                                descriptive information to make
                                                the item easier to identify.
                                            </p>
                                        </div>

                                        <div className="mt-5 grid gap-5 md:grid-cols-2">
                                            <Field
                                                id="model_number"
                                                label="Model number (optional)"
                                                error={errors.model_number}
                                            >
                                                <Input name="model_number" />
                                            </Field>

                                            <Field
                                                id="manufacturer_part_number"
                                                label="Manufacturer part number (optional)"
                                                error={
                                                    errors.manufacturer_part_number
                                                }
                                            >
                                                <Input name="manufacturer_part_number" />
                                            </Field>

                                            <Field
                                                id="description"
                                                label="Description (optional)"
                                                error={errors.description}
                                                className="md:col-span-2"
                                            >
                                                <textarea
                                                    name="description"
                                                    rows={3}
                                                    maxLength={10000}
                                                    className={
                                                        textareaClassName
                                                    }
                                                />
                                            </Field>
                                        </div>
                                    </section>

                                    <section
                                        aria-labelledby="stock-configuration-heading"
                                        className="rounded-xl bg-card p-4 shadow-sm md:p-6"
                                    >
                                        <div>
                                            <h2
                                                id="stock-configuration-heading"
                                                className="font-semibold"
                                            >
                                                Stock configuration
                                            </h2>
                                            <p className="mt-1 text-sm text-muted-foreground">
                                                Set the usable yield and the
                                                authoritative unit used to store
                                                and move this item’s stock.
                                            </p>
                                        </div>

                                        <div className="mt-5 grid gap-5 md:grid-cols-2">
                                            <Field
                                                id="yield_percentage"
                                                label="Yield (%)"
                                                helper="Record the usable percentage of this item. Use 100% when no loss or trim applies."
                                                error={errors.yield_percentage}
                                            >
                                                <Input
                                                    name="yield_percentage"
                                                    type="number"
                                                    min="0"
                                                    max="100"
                                                    step="0.01"
                                                    defaultValue="100.00"
                                                    required
                                                />
                                            </Field>

                                            <Field
                                                id="base_unit_of_measure_id"
                                                label="Base unit"
                                                helper="This is the authoritative unit for stock. It may be restricted after alternate units or stock movements are recorded."
                                                error={
                                                    errors.base_unit_of_measure_id
                                                }
                                            >
                                                <NativeSelect
                                                    name="base_unit_of_measure_id"
                                                    required
                                                    defaultValue=""
                                                >
                                                    <option value="" disabled>
                                                        Select unit
                                                    </option>

                                                    {units.map((unit) => (
                                                        <option
                                                            key={unit.id}
                                                            value={unit.id}
                                                        >
                                                            {unit.name} (
                                                            {unit.symbol})
                                                        </option>
                                                    ))}
                                                </NativeSelect>
                                            </Field>
                                        </div>
                                    </section>

                                    <div className="flex gap-2">
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            {processing
                                                ? 'Creating…'
                                                : 'Create item'}
                                        </Button>

                                        <PreviousPageButton
                                            variant="outline"
                                            fallback={
                                                InventoryItemController.index()
                                                    .url
                                            }
                                            disabled={processing}
                                            onNavigate={
                                                dirtyFormNavigation.confirmNavigation
                                            }
                                        >
                                            Cancel
                                        </PreviousPageButton>
                                    </div>
                                </>
                            )}
                        </Form>
                    </div>
                )}
            </div>
        </>
    );
}

CreateInventoryItem.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
        {
            title: 'Inventory items',
            href: InventoryItemController.index(),
        },
        {
            title: 'Create inventory item',
            href: InventoryItemController.create(),
        },
    ],
};
