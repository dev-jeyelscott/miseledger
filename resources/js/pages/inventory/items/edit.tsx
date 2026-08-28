import { Form, Head } from '@inertiajs/react';
import InventoryItemBarcodeController from '@/actions/App/Http/Controllers/Inventory/InventoryItemBarcodeController';
import InventoryItemController from '@/actions/App/Http/Controllers/Inventory/InventoryItemController';
import InventoryItemUnitController from '@/actions/App/Http/Controllers/Inventory/InventoryItemUnitController';
import InputError from '@/components/input-error';
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
import type {
    BarcodeData,
    InventoryCategoryData,
    InventoryItemDetail,
    UnitOfMeasureData,
} from '@/types';

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
    availableConversionUnits: UnitOfMeasureData[];
};

type EditBarcodeDialogProps = {
    barcode: BarcodeData;
    inventoryItemId: number;
    unitOptions: InventoryItemDetail['unitConversions'];
    trigger: React.ReactNode;
};

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
    availableConversionUnits,
}: Props) {
    return (
        <>
            <Head title={item.name} />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div>
                    <h1 className="text-2xl font-semibold">{item.name}</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {item.sku}
                    </p>
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <div className="rounded-xl border border-sidebar-border/70 p-5 dark:border-sidebar-border">
                        <h2 className="mb-5 font-medium">Item master</h2>

                        <Form
                            {...InventoryItemController.update.form(item.id)}
                            className="space-y-5"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="name">Name</Label>
                                        <Input
                                            id="name"
                                            name="name"
                                            defaultValue={item.name}
                                            required
                                        />
                                        <InputError message={errors.name} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="sku">SKU</Label>
                                        <Input
                                            id="sku"
                                            name="sku"
                                            defaultValue={item.sku}
                                            required
                                        />
                                        <InputError message={errors.sku} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="type">Item type</Label>
                                        <select
                                            id="type"
                                            name="type"
                                            defaultValue={item.type}
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
                                            defaultValue={
                                                item.inventoryCategory?.id ?? ''
                                            }
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
                                                    {!category.active &&
                                                        ' (Inactive)'}
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
                                            defaultValue={item.yieldPercentage}
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
                                            defaultValue={
                                                item.baseUnitOfMeasure.id
                                            }
                                            className="h-9 rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                        >
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

                                        {item.unitConversions.length > 0 && (
                                            <p className="text-xs text-muted-foreground">
                                                The base unit is locked once an
                                                alternate unit has been
                                                configured.
                                            </p>
                                        )}
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="active">Status</Label>

                                        <select
                                            id="active"
                                            name="active"
                                            defaultValue={
                                                item.active ? '1' : '0'
                                            }
                                            className="h-9 rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                        >
                                            <option value="1">Active</option>
                                            <option value="0">Inactive</option>
                                        </select>

                                        <InputError message={errors.active} />
                                    </div>

                                    <Button type="submit" disabled={processing}>
                                        Save item
                                    </Button>
                                </>
                            )}
                        </Form>
                    </div>

                    <div className="space-y-6">
                        <div className="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                            <div className="border-b border-sidebar-border/70 px-5 py-4 dark:border-sidebar-border">
                                <h2 className="font-medium">Alternate units</h2>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Base: {item.baseUnitOfMeasure.symbol}
                                </p>
                            </div>

                            {item.unitConversions.length === 0 ? (
                                <div className="px-5 py-8 text-sm text-muted-foreground">
                                    No alternate units configured.
                                </div>
                            ) : (
                                <div className="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
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
                            <div className="rounded-xl border border-sidebar-border/70 p-5 dark:border-sidebar-border">
                                <h2 className="mb-5 font-medium">
                                    Add alternate unit
                                </h2>

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
                                                Add conversion
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            </div>
                        )}
                    </div>
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <div className="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                        <div className="border-b border-sidebar-border/70 px-5 py-4 dark:border-sidebar-border">
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
                            <div className="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                                {item.barcodes.map((barcode) => (
                                    <div
                                        key={barcode.id}
                                        className="flex items-center justify-between gap-4 px-5 py-4"
                                    >
                                        <div>
                                            <p className="font-medium">
                                                {barcode.value}
                                                {barcode.isPrimary && (
                                                    <span className="ml-2 rounded-full bg-primary/10 px-2 py-0.5 text-xs text-primary">
                                                        Primary
                                                    </span>
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

                    <div className="rounded-xl border border-sidebar-border/70 p-5 dark:border-sidebar-border">
                        <h2 className="mb-5 font-medium">Add barcode</h2>

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
                                        Add barcode
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
