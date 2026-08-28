import { Form, Head, Link } from '@inertiajs/react';
import InventoryItemController from '@/actions/App/Http/Controllers/Inventory/InventoryItemController';
import UnitOfMeasureController from '@/actions/App/Http/Controllers/Inventory/UnitOfMeasureController';
import InputError from '@/components/input-error';
import { PreviousPageButton } from '@/components/navigation/previous-page-button';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { UsageLimitNotice } from '@/components/usage-limit-notice';
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

export default function CreateInventoryItem({
    units,
    categories,
    brands,
    productFamilies,
}: Props) {
    return (
        <>
            <Head title="Create inventory item" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div>
                    <h1 className="text-2xl font-semibold">
                        Create inventory item
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Select the unit in which stock will be stored.
                    </p>
                </div>

                <UsageLimitNotice
                    limitKey="inventory_items"
                    resourceLabel="inventory items"
                />

                {units.length === 0 ? (
                    <div className="max-w-xl rounded-xl border border-sidebar-border/70 p-5 dark:border-sidebar-border">
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
                    <div className="max-w-xl rounded-xl border border-sidebar-border/70 p-5 dark:border-sidebar-border">
                        <Form
                            {...InventoryItemController.store.form()}
                            className="space-y-5"
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
                                            autoFocus
                                            placeholder="All-purpose flour"
                                        />
                                        <InputError message={errors.name} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="sku">SKU</Label>
                                        <Input
                                            id="sku"
                                            name="sku"
                                            required
                                            autoComplete="off"
                                            placeholder="FLOUR-001"
                                        />
                                        <InputError message={errors.sku} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="type">Item type</Label>
                                        <select
                                            id="type"
                                            name="type"
                                            defaultValue="ingredient"
                                            required
                                            className="h-9 rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
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
                                        </select>
                                        <InputError message={errors.type} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="inventory_category_id">
                                            Category
                                        </Label>
                                        <select
                                            id="inventory_category_id"
                                            name="inventory_category_id"
                                            defaultValue=""
                                            className="h-9 rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                        >
                                            <option value="">
                                                Uncategorized
                                            </option>
                                            {categories.map((category) => (
                                                <option
                                                    key={category.id}
                                                    value={category.id}
                                                >
                                                    {category.name}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError
                                            message={
                                                errors.inventory_category_id
                                            }
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="inventory_brand_id">
                                            Brand
                                        </Label>
                                        <select
                                            id="inventory_brand_id"
                                            name="inventory_brand_id"
                                            defaultValue=""
                                            className="h-9 rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                        >
                                            <option value="">No brand</option>
                                            {brands.map((brand) => (
                                                <option
                                                    key={brand.id}
                                                    value={brand.id}
                                                >
                                                    {brand.name}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError
                                            message={errors.inventory_brand_id}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="inventory_product_id">
                                            Product family
                                        </Label>
                                        <select
                                            id="inventory_product_id"
                                            name="inventory_product_id"
                                            defaultValue=""
                                            className="h-9 rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                        >
                                            <option value="">
                                                No product family
                                            </option>
                                            {productFamilies.map((product) => (
                                                <option
                                                    key={product.id}
                                                    value={product.id}
                                                >
                                                    {product.name}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError
                                            message={
                                                errors.inventory_product_id
                                            }
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="model_number">
                                            Model number
                                        </Label>
                                        <Input
                                            id="model_number"
                                            name="model_number"
                                            placeholder="Optional"
                                        />
                                        <InputError
                                            message={errors.model_number}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="manufacturer_part_number">
                                            Manufacturer part number
                                        </Label>
                                        <Input
                                            id="manufacturer_part_number"
                                            name="manufacturer_part_number"
                                            placeholder="Optional"
                                        />
                                        <InputError
                                            message={
                                                errors.manufacturer_part_number
                                            }
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="description">
                                            Description
                                        </Label>
                                        <textarea
                                            id="description"
                                            name="description"
                                            rows={3}
                                            maxLength={10000}
                                            placeholder="Optional"
                                            aria-invalid={
                                                errors.description
                                                    ? true
                                                    : undefined
                                            }
                                            className={textareaClassName}
                                        />
                                        <InputError
                                            message={errors.description}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="yield_percentage">
                                            Yield (%)
                                        </Label>
                                        <Input
                                            id="yield_percentage"
                                            name="yield_percentage"
                                            type="number"
                                            min="0"
                                            max="100"
                                            step="0.01"
                                            defaultValue="100.00"
                                            required
                                        />
                                        <InputError
                                            message={errors.yield_percentage}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="base_unit_of_measure_id">
                                            Base unit
                                        </Label>

                                        <select
                                            id="base_unit_of_measure_id"
                                            name="base_unit_of_measure_id"
                                            required
                                            defaultValue=""
                                            className="h-9 rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                        >
                                            <option value="" disabled>
                                                Select unit
                                            </option>

                                            {units.map((unit) => (
                                                <option
                                                    key={unit.id}
                                                    value={unit.id}
                                                >
                                                    {unit.name} ({unit.symbol})
                                                </option>
                                            ))}
                                        </select>

                                        <InputError
                                            message={
                                                errors.base_unit_of_measure_id
                                            }
                                        />
                                    </div>

                                    <div className="flex gap-2">
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            Create item
                                        </Button>

                                        <PreviousPageButton
                                            variant="outline"
                                            fallback={
                                                InventoryItemController.index()
                                                    .url
                                            }
                                            disabled={processing}
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
            title: 'Inventory',
            href: InventoryItemController.index(),
        },
    ],
};
