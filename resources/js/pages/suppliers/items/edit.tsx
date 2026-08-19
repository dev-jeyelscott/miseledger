import { Form, Head } from '@inertiajs/react';
import SupplierController from '@/actions/App/Http/Controllers/Suppliers/SupplierController';
import SupplierItemController from '@/actions/App/Http/Controllers/Suppliers/SupplierItemController';
import InputError from '@/components/input-error';
import { PreviousPageButton } from '@/components/navigation/previous-page-button';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';

type ItemOption = {
    id: number;
    name: string;
    sku: string;
    active: boolean;
};

type UnitOption = {
    id: number;
    name: string;
    symbol: string;
    active: boolean;
};

type Price = {
    id: number;
    price: string;
    currency: string;
    effectiveAt: string;
};

type Props = {
    supplier: {
        id: number;
        name: string;
        code: string;
        active: boolean;
    };

    supplierItem: {
        id: number;
        supplierSku: string;
        description: string | null;
        baseQuantity: string;
        currentPrice: string | null;
        currency: string;
        active: boolean;

        inventoryItem: {
            id: number;
            name: string;
            sku: string;
            active: boolean;
        };

        purchaseUnit: {
            id: number;
            name: string;
            symbol: string;
            active: boolean;
        };

        prices: Price[];
    };

    itemOptions: ItemOption[];
    unitOptions: UnitOption[];
    canManage: boolean;
};

export default function EditSupplierItem({
    supplier,
    supplierItem,
    itemOptions,
    unitOptions,
    canManage,
}: Props) {
    return (
        <>
            <Head title={supplierItem.supplierSku} />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div>
                    <h1 className="text-2xl font-semibold">
                        {supplierItem.inventoryItem.name}
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {supplier.name} · {supplierItem.supplierSku}
                    </p>
                </div>

                <div className="grid gap-6 xl:grid-cols-2">
                    <div className="rounded-xl border border-sidebar-border/70 p-5 dark:border-sidebar-border">
                        <h2 className="mb-5 font-medium">
                            Supplier item mapping
                        </h2>

                        {canManage ? (
                            <Form
                                {...SupplierItemController.update.form([
                                    supplier.id,
                                    supplierItem.id,
                                ])}
                                className="space-y-5"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <div className="grid gap-2">
                                            <Label htmlFor="inventory_item_id">
                                                Inventory item
                                            </Label>

                                            <select
                                                id="inventory_item_id"
                                                name="inventory_item_id"
                                                defaultValue={
                                                    supplierItem.inventoryItem
                                                        .id
                                                }
                                                className="h-9 rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                            >
                                                {itemOptions.map((item) => (
                                                    <option
                                                        key={item.id}
                                                        value={item.id}
                                                    >
                                                        {item.name} ({item.sku})
                                                        {!item.active
                                                            ? ' — inactive'
                                                            : ''}
                                                    </option>
                                                ))}
                                            </select>

                                            <InputError
                                                message={
                                                    errors.inventory_item_id
                                                }
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="supplier_sku">
                                                Supplier SKU
                                            </Label>
                                            <Input
                                                id="supplier_sku"
                                                name="supplier_sku"
                                                required
                                                defaultValue={
                                                    supplierItem.supplierSku
                                                }
                                            />
                                            <InputError
                                                message={errors.supplier_sku}
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="description">
                                                Description
                                            </Label>
                                            <Input
                                                id="description"
                                                name="description"
                                                defaultValue={
                                                    supplierItem.description ??
                                                    ''
                                                }
                                            />
                                            <InputError
                                                message={errors.description}
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="purchase_unit_of_measure_id">
                                                Purchase unit
                                            </Label>

                                            <select
                                                id="purchase_unit_of_measure_id"
                                                name="purchase_unit_of_measure_id"
                                                defaultValue={
                                                    supplierItem.purchaseUnit.id
                                                }
                                                className="h-9 rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                            >
                                                {unitOptions.map((unit) => (
                                                    <option
                                                        key={unit.id}
                                                        value={unit.id}
                                                    >
                                                        {unit.name} (
                                                        {unit.symbol})
                                                        {!unit.active
                                                            ? ' — inactive'
                                                            : ''}
                                                    </option>
                                                ))}
                                            </select>

                                            <InputError
                                                message={
                                                    errors.purchase_unit_of_measure_id
                                                }
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="base_quantity">
                                                Base quantity
                                            </Label>
                                            <Input
                                                id="base_quantity"
                                                name="base_quantity"
                                                type="number"
                                                required
                                                min="0.000001"
                                                step="0.000001"
                                                defaultValue={
                                                    supplierItem.baseQuantity
                                                }
                                            />
                                            <InputError
                                                message={errors.base_quantity}
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="active">
                                                Status
                                            </Label>

                                            <select
                                                id="active"
                                                name="active"
                                                defaultValue={
                                                    supplierItem.active
                                                        ? '1'
                                                        : '0'
                                                }
                                                className="h-9 rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                            >
                                                <option value="1">
                                                    Active
                                                </option>
                                                <option value="0">
                                                    Inactive
                                                </option>
                                            </select>

                                            <InputError
                                                message={errors.active}
                                            />
                                        </div>

                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            Save supplier item
                                        </Button>
                                    </>
                                )}
                            </Form>
                        ) : (
                            <dl className="space-y-4 text-sm">
                                <div>
                                    <dt className="text-muted-foreground">
                                        Internal item
                                    </dt>
                                    <dd>
                                        {supplierItem.inventoryItem.name} (
                                        {supplierItem.inventoryItem.sku})
                                    </dd>
                                </div>

                                <div>
                                    <dt className="text-muted-foreground">
                                        Purchase unit
                                    </dt>
                                    <dd>
                                        {supplierItem.purchaseUnit.name} (
                                        {supplierItem.purchaseUnit.symbol})
                                    </dd>
                                </div>

                                <div>
                                    <dt className="text-muted-foreground">
                                        Base quantity
                                    </dt>
                                    <dd>{supplierItem.baseQuantity}</dd>
                                </div>

                                <div>
                                    <dt className="text-muted-foreground">
                                        Status
                                    </dt>
                                    <dd>
                                        {supplierItem.active
                                            ? 'Active'
                                            : 'Inactive'}
                                    </dd>
                                </div>
                            </dl>
                        )}

                        <PreviousPageButton
                            variant="outline"
                            className="mt-5"
                            fallback={SupplierController.edit(supplier.id).url}
                        >
                            Back to supplier
                        </PreviousPageButton>
                    </div>

                    <div className="space-y-6">
                        <div className="rounded-xl border border-sidebar-border/70 p-5 dark:border-sidebar-border">
                            <h2 className="font-medium">Current price</h2>

                            <p className="mt-3 text-2xl font-semibold">
                                {supplierItem.currentPrice === null
                                    ? 'Not set'
                                    : `${supplierItem.currency} ${supplierItem.currentPrice}`}
                            </p>

                            {canManage &&
                                supplier.active &&
                                supplierItem.active && (
                                    <Form
                                        {...SupplierItemController.storePrice.form(
                                            [supplier.id, supplierItem.id],
                                        )}
                                        className="mt-5 flex max-w-sm items-end gap-3"
                                        resetOnSuccess
                                    >
                                        {({ processing, errors }) => (
                                            <>
                                                <div className="grid flex-1 gap-2">
                                                    <Label htmlFor="price">
                                                        New price
                                                    </Label>
                                                    <Input
                                                        id="price"
                                                        name="price"
                                                        type="number"
                                                        required
                                                        min="0"
                                                        step="0.0001"
                                                    />
                                                    <InputError
                                                        message={errors.price}
                                                    />
                                                </div>

                                                <Button
                                                    type="submit"
                                                    disabled={processing}
                                                >
                                                    Record price
                                                </Button>
                                            </>
                                        )}
                                    </Form>
                                )}
                        </div>

                        <div className="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                            <div className="border-b border-sidebar-border/70 px-5 py-4 dark:border-sidebar-border">
                                <h2 className="font-medium">Price history</h2>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Historical prices are append-only.
                                </p>
                            </div>

                            {supplierItem.prices.length === 0 ? (
                                <div className="px-5 py-8 text-sm text-muted-foreground">
                                    No supplier prices recorded.
                                </div>
                            ) : (
                                <div className="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                                    {supplierItem.prices.map((price) => (
                                        <div
                                            key={price.id}
                                            className="flex items-center justify-between gap-4 px-5 py-4"
                                        >
                                            <span className="font-medium">
                                                {price.currency} {price.price}
                                            </span>

                                            <span className="text-sm text-muted-foreground">
                                                {new Date(
                                                    price.effectiveAt,
                                                ).toLocaleString()}
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}

EditSupplierItem.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
        {
            title: 'Suppliers',
            href: SupplierController.index(),
        },
    ],
};
