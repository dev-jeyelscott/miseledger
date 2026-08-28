import { Form, Head } from '@inertiajs/react';
import { useEffect } from 'react';
import InventoryItemBarcodeController from '@/actions/App/Http/Controllers/Inventory/InventoryItemBarcodeController';
import InventoryItemController from '@/actions/App/Http/Controllers/Inventory/InventoryItemController';
import InventoryItemUnitController from '@/actions/App/Http/Controllers/Inventory/InventoryItemUnitController';
import InputError from '@/components/input-error';
import { PreviousPageButton } from '@/components/navigation/previous-page-button';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { useDirtyFormNavigation } from '@/hooks/use-dirty-form-navigation';
import { useGuardedDialog } from '@/hooks/use-guarded-dialog';
import { dashboard } from '@/routes';
import type {
    BarcodeData,
    InventoryBrandData,
    InventoryCategoryData,
    InventoryItemDetail,
    InventoryProductData,
    UnitOfMeasureData,
} from '@/types';

const textareaClassName =
    'min-h-24 w-full resize-y rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none transition-[color,box-shadow] placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive aria-invalid:ring-[3px] aria-invalid:ring-destructive/20 disabled:cursor-not-allowed disabled:opacity-50';

const BARCODE_SYMBOLOGY_OPTIONS: Array<{ value: string; label: string }> = [
    { value: 'ean_13', label: 'EAN-13' },
    { value: 'ean_8', label: 'EAN-8' },
    { value: 'upc_a', label: 'UPC-A' },
    { value: 'upc_e', label: 'UPC-E' },
    { value: 'code_128', label: 'Code 128' },
    { value: 'code_39', label: 'Code 39' },
    { value: 'other', label: 'Other' },
];

type Props = {
    item: InventoryItemDetail;
    units: UnitOfMeasureData[];
    categories: InventoryCategoryData[];
    brands: InventoryBrandData[];
    productFamilies: InventoryProductData[];
    availableConversionUnits: UnitOfMeasureData[];
};

type EditBarcodeDialogProps = {
    barcode: BarcodeData;
    inventoryItemId: number;
    unitOptions: InventoryItemDetail['unitConversions'];
    trigger: React.ReactNode;
};

type DirtyStateTrackerProps = {
    dirty: boolean;
    onChange: (dirty: boolean) => void;
};

/** Keep the page navigation guard synchronized with the main edit form. */
function DirtyStateTracker({ dirty, onChange }: DirtyStateTrackerProps) {
    useEffect(() => {
        onChange(dirty);
    }, [dirty, onChange]);

    return null;
}

/** Edit a barcode's value, symbology, unit, and primary/active state. */
function EditBarcodeDialog({
    barcode,
    inventoryItemId,
    unitOptions,
    trigger,
}: EditBarcodeDialogProps) {
    const dialog = useGuardedDialog('Discard the barcode changes you entered?');

    return (
        <Dialog open={dialog.open} onOpenChange={dialog.onOpenChange}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>

            <DialogContent className="max-h-[calc(100vh-2rem)] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Edit barcode</DialogTitle>
                    <DialogDescription>{barcode.value}</DialogDescription>
                </DialogHeader>

                <div onChange={dialog.markDirty}>
                    <Form
                        {...InventoryItemBarcodeController.update.form([
                            inventoryItemId,
                            barcode.id,
                        ])}
                        errorBag={`editBarcode${barcode.id}`}
                        className="space-y-5"
                        onSuccess={dialog.closeAfterSuccess}
                    >
                        {({ processing, errors }) => (
                            <>
                                <input type="hidden" name="_modal" value="1" />

                                <div className="grid gap-2">
                                    <Label
                                        htmlFor={`barcode-value-${barcode.id}`}
                                    >
                                        Value
                                    </Label>
                                    <Input
                                        id={`barcode-value-${barcode.id}`}
                                        name="value"
                                        defaultValue={barcode.value}
                                        maxLength={64}
                                        required
                                        autoFocus
                                    />
                                    <InputError message={errors.value} />
                                </div>

                                <div className="grid gap-2">
                                    <Label
                                        htmlFor={`barcode-symbology-${barcode.id}`}
                                    >
                                        Symbology
                                    </Label>
                                    <select
                                        id={`barcode-symbology-${barcode.id}`}
                                        name="symbology"
                                        defaultValue={barcode.symbology}
                                        required
                                        className="h-9 rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                    >
                                        {BARCODE_SYMBOLOGY_OPTIONS.map(
                                            (option) => (
                                                <option
                                                    key={option.value}
                                                    value={option.value}
                                                >
                                                    {option.label}
                                                </option>
                                            ),
                                        )}
                                    </select>
                                    <InputError message={errors.symbology} />
                                </div>

                                <div className="grid gap-2">
                                    <Label
                                        htmlFor={`barcode-unit-${barcode.id}`}
                                    >
                                        Associated unit
                                    </Label>
                                    <select
                                        id={`barcode-unit-${barcode.id}`}
                                        name="inventory_item_unit_id"
                                        defaultValue={
                                            barcode.inventoryItemUnit?.id ?? ''
                                        }
                                        className="h-9 rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                    >
                                        <option value="">
                                            Base item (no alternate unit)
                                        </option>
                                        {unitOptions.map((unit) => (
                                            <option
                                                key={unit.id}
                                                value={unit.id}
                                            >
                                                {unit.unitOfMeasure.name} (
                                                {unit.unitOfMeasure.symbol})
                                            </option>
                                        ))}
                                    </select>
                                    <InputError
                                        message={errors.inventory_item_unit_id}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label
                                        htmlFor={`barcode-primary-${barcode.id}`}
                                    >
                                        Primary
                                    </Label>
                                    <select
                                        id={`barcode-primary-${barcode.id}`}
                                        name="is_primary"
                                        defaultValue={
                                            barcode.isPrimary ? '1' : '0'
                                        }
                                        className="h-9 rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                    >
                                        <option value="0">Not primary</option>
                                        <option value="1">Primary</option>
                                    </select>
                                    <InputError message={errors.is_primary} />
                                </div>

                                <div className="grid gap-2">
                                    <Label
                                        htmlFor={`barcode-active-${barcode.id}`}
                                    >
                                        Status
                                    </Label>
                                    <select
                                        id={`barcode-active-${barcode.id}`}
                                        name="active"
                                        defaultValue={
                                            barcode.active ? '1' : '0'
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
                                            : 'Save barcode'}
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

type EditInventoryItemUnitDialogProps = {
    baseUnitSymbol: string;
    conversion: InventoryItemDetail['unitConversions'][number];
    inventoryItemId: number;
    trigger: React.ReactNode;
};

/** Edit one conversion factor without leaving the inventory item context. */
function EditInventoryItemUnitDialog({
    baseUnitSymbol,
    conversion,
    inventoryItemId,
    trigger,
}: EditInventoryItemUnitDialogProps) {
    const dialog = useGuardedDialog(
        'Discard the unit conversion changes you entered?',
    );

    return (
        <Dialog open={dialog.open} onOpenChange={dialog.onOpenChange}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>

            <DialogContent className="max-h-[calc(100vh-2rem)] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Edit unit conversion</DialogTitle>
                    <DialogDescription>
                        1 {conversion.unitOfMeasure.symbol} to {baseUnitSymbol}
                    </DialogDescription>
                </DialogHeader>

                <div onChange={dialog.markDirty}>
                    <Form
                        {...InventoryItemUnitController.update.form([
                            inventoryItemId,
                            conversion.id,
                        ])}
                        errorBag={`editInventoryItemUnit${conversion.id}`}
                        className="space-y-5"
                        onSuccess={dialog.closeAfterSuccess}
                    >
                        {({ processing, errors }) => (
                            <>
                                <input type="hidden" name="_modal" value="1" />

                                <div className="grid gap-2">
                                    <Label
                                        htmlFor={`conversion-quantity-${conversion.id}`}
                                    >
                                        Quantity in base unit
                                    </Label>

                                    <Input
                                        id={`conversion-quantity-${conversion.id}`}
                                        name="quantity_in_base_unit"
                                        type="number"
                                        min="0.000001"
                                        step="0.000001"
                                        defaultValue={
                                            conversion.quantityInBaseUnit
                                        }
                                        required
                                        autoFocus
                                    />

                                    <p className="text-xs text-muted-foreground">
                                        1 {conversion.unitOfMeasure.symbol} ={' '}
                                        quantity × {baseUnitSymbol}
                                    </p>

                                    <InputError
                                        message={errors.quantity_in_base_unit}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label
                                        htmlFor={`conversion-active-${conversion.id}`}
                                    >
                                        Status
                                    </Label>

                                    <select
                                        id={`conversion-active-${conversion.id}`}
                                        name="active"
                                        defaultValue={
                                            conversion.active ? '1' : '0'
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
                                            : 'Save conversion'}
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

export default function EditInventoryItem({
    item,
    units,
    categories,
    brands,
    productFamilies,
    availableConversionUnits,
}: Props) {
    const dirtyFormNavigation = useDirtyFormNavigation(
        'You have unsaved inventory item changes. Leave without saving them?',
    );

    return (
        <>
            <Head title={item.name} />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={item.name}
                    description={<span className="font-mono">{item.sku}</span>}
                    actions={
                        <>
                            <StatusBadge
                                label={item.active ? 'Active' : 'Inactive'}
                                variant={item.active ? 'success' : 'neutral'}
                            />
                            <PreviousPageButton
                                variant="outline"
                                fallback={
                                    InventoryItemController.show(item.id).url
                                }
                                onNavigate={
                                    dirtyFormNavigation.confirmNavigation
                                }
                            >
                                Back to item
                            </PreviousPageButton>
                        </>
                    }
                />

                <div className="grid gap-6 lg:grid-cols-2">
                    <div className="rounded-xl border border-border bg-card p-4 md:p-6">
                        <div>
                            <h2 className="font-semibold">Item details</h2>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Update the identity, classification, and stock
                                configuration for this item.
                            </p>
                        </div>

                        <Form
                            {...InventoryItemController.update.form(item.id)}
                            className="mt-5 space-y-5"
                        >
                            {({ processing, errors, isDirty }) => (
                                <>
                                    <DirtyStateTracker
                                        dirty={isDirty}
                                        onChange={
                                            dirtyFormNavigation.setIsDirty
                                        }
                                    />

                                    <div className="grid gap-5 md:grid-cols-2">
                                        <Field
                                            id="name"
                                            label="Name"
                                            error={errors.name}
                                        >
                                            <Input
                                                name="name"
                                                defaultValue={item.name}
                                                required
                                            />
                                        </Field>

                                        <Field
                                            id="sku"
                                            label="SKU"
                                            error={errors.sku}
                                        >
                                            <Input
                                                name="sku"
                                                defaultValue={item.sku}
                                                required
                                                className="font-mono"
                                            />
                                        </Field>

                                        <Field
                                            id="type"
                                            label="Item type"
                                            error={errors.type}
                                        >
                                            <NativeSelect
                                                name="type"
                                                defaultValue={item.type}
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
                                            label="Category"
                                            error={errors.inventory_category_id}
                                        >
                                            <NativeSelect
                                                name="inventory_category_id"
                                                defaultValue={
                                                    item.inventoryCategory
                                                        ?.id ?? ''
                                                }
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
                                                        {!category.active &&
                                                            ' (Inactive)'}
                                                    </option>
                                                ))}
                                            </NativeSelect>
                                        </Field>

                                        <Field
                                            id="inventory_brand_id"
                                            label="Brand"
                                            error={errors.inventory_brand_id}
                                        >
                                            <NativeSelect
                                                name="inventory_brand_id"
                                                defaultValue={
                                                    item.inventoryBrand?.id ??
                                                    ''
                                                }
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
                                                        {!brand.active &&
                                                            ' (Inactive)'}
                                                    </option>
                                                ))}
                                            </NativeSelect>
                                        </Field>

                                        {!item.editability.productFamily
                                            .editable && (
                                            <input
                                                type="hidden"
                                                name="inventory_product_id"
                                                value={
                                                    item.inventoryProduct?.id ??
                                                    ''
                                                }
                                            />
                                        )}
                                        <Field
                                            id="inventory_product_id"
                                            label="Product family"
                                            error={errors.inventory_product_id}
                                            helper={
                                                item.editability.productFamily
                                                    .reason ??
                                                'Associate related variants with a product family before assigning its option values.'
                                            }
                                        >
                                            <NativeSelect
                                                name={
                                                    item.editability
                                                        .productFamily.editable
                                                        ? 'inventory_product_id'
                                                        : undefined
                                                }
                                                defaultValue={
                                                    item.inventoryProduct?.id ??
                                                    ''
                                                }
                                                disabled={
                                                    !item.editability
                                                        .productFamily.editable
                                                }
                                            >
                                                <option value="">
                                                    No product family
                                                </option>
                                                {productFamilies.map(
                                                    (product) => (
                                                        <option
                                                            key={product.id}
                                                            value={product.id}
                                                        >
                                                            {product.name}
                                                            {!product.active
                                                                ? ' (inactive)'
                                                                : ''}
                                                        </option>
                                                    ),
                                                )}
                                            </NativeSelect>
                                        </Field>

                                        <Field
                                            id="model_number"
                                            label="Model number (optional)"
                                            error={errors.model_number}
                                        >
                                            <Input
                                                name="model_number"
                                                defaultValue={
                                                    item.modelNumber ?? ''
                                                }
                                                placeholder="Optional"
                                            />
                                        </Field>

                                        <Field
                                            id="manufacturer_part_number"
                                            label="Manufacturer part number (optional)"
                                            error={
                                                errors.manufacturer_part_number
                                            }
                                        >
                                            <Input
                                                name="manufacturer_part_number"
                                                defaultValue={
                                                    item.manufacturerPartNumber ??
                                                    ''
                                                }
                                                placeholder="Optional"
                                            />
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
                                                defaultValue={
                                                    item.description ?? ''
                                                }
                                                placeholder="Optional"
                                                className={textareaClassName}
                                            />
                                        </Field>

                                        <Field
                                            id="yield_percentage"
                                            label="Yield (%)"
                                            error={errors.yield_percentage}
                                        >
                                            <Input
                                                name="yield_percentage"
                                                type="number"
                                                min="0"
                                                max="100"
                                                step="0.01"
                                                defaultValue={
                                                    item.yieldPercentage
                                                }
                                                required
                                            />
                                        </Field>

                                        {!item.editability.baseUnitOfMeasure
                                            .editable && (
                                            <input
                                                type="hidden"
                                                name="base_unit_of_measure_id"
                                                value={
                                                    item.baseUnitOfMeasure.id
                                                }
                                            />
                                        )}
                                        <Field
                                            id="base_unit_of_measure_id"
                                            label="Base unit"
                                            error={
                                                errors.base_unit_of_measure_id
                                            }
                                            helper={
                                                item.editability
                                                    .baseUnitOfMeasure.reason ??
                                                'This is the authoritative unit for stock.'
                                            }
                                        >
                                            <NativeSelect
                                                name={
                                                    item.editability
                                                        .baseUnitOfMeasure
                                                        .editable
                                                        ? 'base_unit_of_measure_id'
                                                        : undefined
                                                }
                                                defaultValue={
                                                    item.baseUnitOfMeasure.id
                                                }
                                                disabled={
                                                    !item.editability
                                                        .baseUnitOfMeasure
                                                        .editable
                                                }
                                            >
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

                                        <Field
                                            id="active"
                                            label="Status"
                                            error={errors.active}
                                        >
                                            <NativeSelect
                                                name="active"
                                                defaultValue={
                                                    item.active ? '1' : '0'
                                                }
                                            >
                                                <option value="1">
                                                    Active
                                                </option>
                                                <option value="0">
                                                    Inactive
                                                </option>
                                            </NativeSelect>
                                        </Field>
                                    </div>

                                    <Button type="submit" disabled={processing}>
                                        {processing ? 'Saving…' : 'Save item'}
                                    </Button>
                                </>
                            )}
                        </Form>
                    </div>

                    <div className="space-y-6">
                        <div className="rounded-xl border border-border bg-card">
                            <div className="border-b border-border px-5 py-4">
                                <h2 className="font-medium">
                                    Units and conversions
                                </h2>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Base: {item.baseUnitOfMeasure.symbol}
                                </p>
                            </div>

                            {item.unitConversions.length === 0 ? (
                                <div className="px-5 py-8 text-sm text-muted-foreground">
                                    No alternate units configured.
                                </div>
                            ) : (
                                <div className="divide-y divide-border">
                                    {item.unitConversions.map((conversion) => (
                                        <div
                                            key={conversion.id}
                                            className="flex items-center justify-between gap-4 px-5 py-4"
                                        >
                                            <div>
                                                <p className="font-medium">
                                                    1{' '}
                                                    {
                                                        conversion.unitOfMeasure
                                                            .symbol
                                                    }{' '}
                                                    ={' '}
                                                    {
                                                        conversion.quantityInBaseUnit
                                                    }{' '}
                                                    {
                                                        item.baseUnitOfMeasure
                                                            .symbol
                                                    }
                                                </p>

                                                <p className="mt-1 text-sm text-muted-foreground">
                                                    {conversion.active
                                                        ? 'Active'
                                                        : 'Inactive'}
                                                </p>
                                            </div>

                                            <EditInventoryItemUnitDialog
                                                inventoryItemId={item.id}
                                                conversion={conversion}
                                                baseUnitSymbol={
                                                    item.baseUnitOfMeasure
                                                        .symbol
                                                }
                                                trigger={
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                    >
                                                        Edit
                                                    </Button>
                                                }
                                            />
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>

                        {availableConversionUnits.length > 0 && (
                            <div className="rounded-xl border border-border bg-card p-5">
                                <h3 className="mb-5 font-medium">
                                    Add alternate unit
                                </h3>

                                <Form
                                    {...InventoryItemUnitController.store.form(
                                        item.id,
                                    )}
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
                                                <Label htmlFor="unit_of_measure_id">
                                                    Unit
                                                </Label>

                                                <select
                                                    id="unit_of_measure_id"
                                                    name="unit_of_measure_id"
                                                    defaultValue=""
                                                    required
                                                    className="h-9 rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                                >
                                                    <option value="" disabled>
                                                        Select unit
                                                    </option>

                                                    {availableConversionUnits.map(
                                                        (unit) => (
                                                            <option
                                                                key={unit.id}
                                                                value={unit.id}
                                                            >
                                                                {unit.name} (
                                                                {unit.symbol})
                                                            </option>
                                                        ),
                                                    )}
                                                </select>

                                                <InputError
                                                    message={
                                                        errors.unit_of_measure_id
                                                    }
                                                />
                                            </div>

                                            <div className="grid gap-2">
                                                <Label htmlFor="quantity_in_base_unit">
                                                    Quantity in base unit
                                                </Label>

                                                <Input
                                                    id="quantity_in_base_unit"
                                                    name="quantity_in_base_unit"
                                                    type="number"
                                                    min="0.000001"
                                                    step="0.000001"
                                                    required
                                                    placeholder="1000"
                                                />

                                                <p className="text-xs text-muted-foreground">
                                                    Number of{' '}
                                                    {
                                                        item.baseUnitOfMeasure
                                                            .symbol
                                                    }{' '}
                                                    contained in one selected
                                                    unit.
                                                </p>

                                                <InputError
                                                    message={
                                                        errors.quantity_in_base_unit
                                                    }
                                                />
                                            </div>

                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            >
                                                {processing
                                                    ? 'Adding…'
                                                    : 'Add conversion'}
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            </div>
                        )}
                    </div>
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <div className="rounded-xl border border-border bg-card">
                        <div className="border-b border-border px-5 py-4">
                            <h2 className="font-medium">Barcodes</h2>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Identify this item or one of its alternate units
                                at scan.
                            </p>
                        </div>

                        {item.barcodes.length === 0 ? (
                            <div className="px-5 py-8 text-sm text-muted-foreground">
                                No barcodes configured.
                            </div>
                        ) : (
                            <div className="divide-y divide-border">
                                {item.barcodes.map((barcode) => (
                                    <div
                                        key={barcode.id}
                                        className="flex items-center justify-between gap-4 px-5 py-4"
                                    >
                                        <div>
                                            <p className="font-medium">
                                                {barcode.value}
                                                {barcode.isPrimary && (
                                                    <Badge
                                                        variant="secondary"
                                                        className="ml-2"
                                                    >
                                                        Primary
                                                    </Badge>
                                                )}
                                            </p>

                                            <p className="mt-1 text-sm text-muted-foreground">
                                                {
                                                    BARCODE_SYMBOLOGY_OPTIONS.find(
                                                        (option) =>
                                                            option.value ===
                                                            barcode.symbology,
                                                    )?.label
                                                }
                                                {' · '}
                                                {barcode.inventoryItemUnit
                                                    ? `${barcode.inventoryItemUnit.unitOfMeasure.name} (${barcode.inventoryItemUnit.unitOfMeasure.symbol})`
                                                    : 'Base item'}
                                                {' · '}
                                                {barcode.active
                                                    ? 'Active'
                                                    : 'Inactive'}
                                            </p>
                                        </div>

                                        <EditBarcodeDialog
                                            inventoryItemId={item.id}
                                            barcode={barcode}
                                            unitOptions={item.unitConversions}
                                            trigger={
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                >
                                                    Edit
                                                </Button>
                                            }
                                        />
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>

                    <div className="rounded-xl border border-border bg-card p-5">
                        <h3 className="mb-5 font-medium">Add barcode</h3>

                        <Form
                            {...InventoryItemBarcodeController.store.form(
                                item.id,
                            )}
                            className="space-y-5"
                            resetOnSuccess
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="value">Value</Label>
                                        <Input
                                            id="value"
                                            name="value"
                                            maxLength={64}
                                            required
                                            placeholder="0123456789012"
                                        />
                                        <InputError message={errors.value} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="symbology">
                                            Symbology
                                        </Label>
                                        <select
                                            id="symbology"
                                            name="symbology"
                                            defaultValue="ean_13"
                                            required
                                            className="h-9 rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                        >
                                            {BARCODE_SYMBOLOGY_OPTIONS.map(
                                                (option) => (
                                                    <option
                                                        key={option.value}
                                                        value={option.value}
                                                    >
                                                        {option.label}
                                                    </option>
                                                ),
                                            )}
                                        </select>
                                        <InputError
                                            message={errors.symbology}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="inventory_item_unit_id">
                                            Associated unit
                                        </Label>
                                        <select
                                            id="inventory_item_unit_id"
                                            name="inventory_item_unit_id"
                                            defaultValue=""
                                            className="h-9 rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                        >
                                            <option value="">
                                                Base item (no alternate unit)
                                            </option>
                                            {item.unitConversions.map(
                                                (unit) => (
                                                    <option
                                                        key={unit.id}
                                                        value={unit.id}
                                                    >
                                                        {
                                                            unit.unitOfMeasure
                                                                .name
                                                        }{' '}
                                                        (
                                                        {
                                                            unit.unitOfMeasure
                                                                .symbol
                                                        }
                                                        )
                                                    </option>
                                                ),
                                            )}
                                        </select>
                                        <InputError
                                            message={
                                                errors.inventory_item_unit_id
                                            }
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="is_primary">
                                            Primary
                                        </Label>
                                        <select
                                            id="is_primary"
                                            name="is_primary"
                                            defaultValue="0"
                                            className="h-9 rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                        >
                                            <option value="0">
                                                Not primary
                                            </option>
                                            <option value="1">Primary</option>
                                        </select>
                                        <InputError
                                            message={errors.is_primary}
                                        />
                                    </div>

                                    <input
                                        type="hidden"
                                        name="active"
                                        value="1"
                                    />

                                    <Button type="submit" disabled={processing}>
                                        {processing ? 'Adding…' : 'Add barcode'}
                                    </Button>
                                </>
                            )}
                        </Form>
                    </div>
                </div>
            </div>
        </>
    );
}

EditInventoryItem.layout = {
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
