import { Form, Head, Link } from '@inertiajs/react';
import SupplierController from '@/actions/App/Http/Controllers/Suppliers/SupplierController';
import SupplierItemController from '@/actions/App/Http/Controllers/Suppliers/SupplierItemController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';

type InventoryItemOption = {
    id: number;
    name: string;
    sku: string;
};

type UnitOption = {
    id: number;
    name: string;
    symbol: string;
};

type SupplierItem = {
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
    };

    purchaseUnit: {
        id: number;
        name: string;
        symbol: string;
    };
};

type Supplier = {
    id: number;
    name: string;
    code: string;
    contactName: string | null;
    email: string | null;
    phone: string | null;
    paymentTerms: string | null;
    leadTimeDays: number | null;
    active: boolean;
    items: SupplierItem[];
};

type Props = {
    supplier: Supplier;
    itemOptions: InventoryItemOption[];
    unitOptions: UnitOption[];
    canManage: boolean;
};

export default function EditSupplier({
    supplier,
    itemOptions,
    unitOptions,
    canManage,
}: Props) {
    return (
        <>
            <Head title={supplier.name} />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div>
                    <h1 className="text-2xl font-semibold">{supplier.name}</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {supplier.code}
                    </p>
                </div>

                <div className="grid gap-6 xl:grid-cols-2">
                    <div className="rounded-xl border border-sidebar-border/70 p-5 dark:border-sidebar-border">
                        <h2 className="mb-5 font-medium">Supplier master</h2>

                        {canManage ? (
                            <Form
                                {...SupplierController.update.form(supplier.id)}
                                className="space-y-5"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <div className="grid gap-5 sm:grid-cols-2">
                                            <div className="grid gap-2">
                                                <Label htmlFor="name">
                                                    Name
                                                </Label>
                                                <Input
                                                    id="name"
                                                    name="name"
                                                    required
                                                    defaultValue={supplier.name}
                                                />
                                                <InputError
                                                    message={errors.name}
                                                />
                                            </div>

                                            <div className="grid gap-2">
                                                <Label htmlFor="code">
                                                    Code
                                                </Label>
                                                <Input
                                                    id="code"
                                                    name="code"
                                                    required
                                                    defaultValue={supplier.code}
                                                />
                                                <InputError
                                                    message={errors.code}
                                                />
                                            </div>

                                            <div className="grid gap-2">
                                                <Label htmlFor="contact_name">
                                                    Contact name
                                                </Label>
                                                <Input
                                                    id="contact_name"
                                                    name="contact_name"
                                                    defaultValue={
                                                        supplier.contactName ??
                                                        ''
                                                    }
                                                />
                                                <InputError
                                                    message={
                                                        errors.contact_name
                                                    }
                                                />
                                            </div>

                                            <div className="grid gap-2">
                                                <Label htmlFor="email">
                                                    Email
                                                </Label>
                                                <Input
                                                    id="email"
                                                    name="email"
                                                    type="email"
                                                    defaultValue={
                                                        supplier.email ?? ''
                                                    }
                                                />
                                                <InputError
                                                    message={errors.email}
                                                />
                                            </div>

                                            <div className="grid gap-2">
                                                <Label htmlFor="phone">
                                                    Phone
                                                </Label>
                                                <Input
                                                    id="phone"
                                                    name="phone"
                                                    defaultValue={
                                                        supplier.phone ?? ''
                                                    }
                                                />
                                                <InputError
                                                    message={errors.phone}
                                                />
                                            </div>

                                            <div className="grid gap-2">
                                                <Label htmlFor="payment_terms">
                                                    Payment terms
                                                </Label>
                                                <Input
                                                    id="payment_terms"
                                                    name="payment_terms"
                                                    defaultValue={
                                                        supplier.paymentTerms ??
                                                        ''
                                                    }
                                                />
                                                <InputError
                                                    message={
                                                        errors.payment_terms
                                                    }
                                                />
                                            </div>

                                            <div className="grid gap-2">
                                                <Label htmlFor="lead_time_days">
                                                    Lead time (days)
                                                </Label>
                                                <Input
                                                    id="lead_time_days"
                                                    name="lead_time_days"
                                                    type="number"
                                                    min="0"
                                                    step="1"
                                                    defaultValue={
                                                        supplier.leadTimeDays ??
                                                        ''
                                                    }
                                                />
                                                <InputError
                                                    message={
                                                        errors.lead_time_days
                                                    }
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
                                                        supplier.active
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
                                        </div>

                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            Save supplier
                                        </Button>
                                    </>
                                )}
                            </Form>
                        ) : (
                            <dl className="grid gap-4 text-sm sm:grid-cols-2">
                                <div>
                                    <dt className="text-muted-foreground">
                                        Contact
                                    </dt>
                                    <dd>{supplier.contactName ?? '—'}</dd>
                                </div>

                                <div>
                                    <dt className="text-muted-foreground">
                                        Email
                                    </dt>
                                    <dd>{supplier.email ?? '—'}</dd>
                                </div>

                                <div>
                                    <dt className="text-muted-foreground">
                                        Phone
                                    </dt>
                                    <dd>{supplier.phone ?? '—'}</dd>
                                </div>

                                <div>
                                    <dt className="text-muted-foreground">
                                        Payment terms
                                    </dt>
                                    <dd>{supplier.paymentTerms ?? '—'}</dd>
                                </div>

                                <div>
                                    <dt className="text-muted-foreground">
                                        Lead time
                                    </dt>
                                    <dd>
                                        {supplier.leadTimeDays === null
                                            ? '—'
                                            : `${supplier.leadTimeDays} days`}
                                    </dd>
                                </div>

                                <div>
                                    <dt className="text-muted-foreground">
                                        Status
                                    </dt>
                                    <dd>
                                        {supplier.active
                                            ? 'Active'
                                            : 'Inactive'}
                                    </dd>
                                </div>
                            </dl>
                        )}
                    </div>

                    <div className="space-y-6">
                        <div className="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                            <div className="border-b border-sidebar-border/70 px-5 py-4 dark:border-sidebar-border">
                                <h2 className="font-medium">Supplier items</h2>
                            </div>

                            {supplier.items.length === 0 ? (
                                <div className="px-5 py-8 text-sm text-muted-foreground">
                                    No supplier items configured.
                                </div>
                            ) : (
                                <div className="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                                    {supplier.items.map((item) => (
                                        <div
                                            key={item.id}
                                            className="flex flex-col justify-between gap-4 px-5 py-4 sm:flex-row sm:items-center"
                                        >
                                            <div>
                                                <Link
                                                    href={SupplierItemController.edit(
                                                        [supplier.id, item.id],
                                                    )}
                                                    className="font-medium hover:underline"
                                                >
                                                    {item.inventoryItem.name}
                                                </Link>

                                                <p className="mt-1 text-sm text-muted-foreground">
                                                    {item.supplierSku} · 1{' '}
                                                    {item.purchaseUnit.symbol} ={' '}
                                                    {item.baseQuantity} base
                                                    units
                                                </p>

                                                <div className="mt-2 flex items-center gap-3">
                                                    <p className="text-sm">
                                                        {item.currentPrice ===
                                                        null
                                                            ? 'No price'
                                                            : `${item.currency} ${item.currentPrice}`}
                                                    </p>

                                                    {canManage &&
                                                        supplier.active &&
                                                        item.active &&
                                                        item.currentPrice ===
                                                            null && (
                                                            <Button
                                                                size="sm"
                                                                variant="outline"
                                                                asChild
                                                            >
                                                                <Link
                                                                    href={SupplierItemController.edit(
                                                                        [
                                                                            supplier.id,
                                                                            item.id,
                                                                        ],
                                                                    )}
                                                                >
                                                                    Set price
                                                                </Link>
                                                            </Button>
                                                        )}
                                                </div>
                                            </div>

                                            <span className="text-sm">
                                                {item.active
                                                    ? 'Active'
                                                    : 'Inactive'}
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>

                        {canManage &&
                            supplier.active &&
                            itemOptions.length > 0 &&
                            unitOptions.length > 0 && (
                                <div className="rounded-xl border border-sidebar-border/70 p-5 dark:border-sidebar-border">
                                    <h2 className="mb-5 font-medium">
                                        Add supplier item
                                    </h2>

                                    <Form
                                        {...SupplierItemController.store.form(
                                            supplier.id,
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
                                                    <Label htmlFor="inventory_item_id">
                                                        Inventory item
                                                    </Label>

                                                    <select
                                                        id="inventory_item_id"
                                                        name="inventory_item_id"
                                                        required
                                                        defaultValue=""
                                                        className="h-9 rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                                    >
                                                        <option
                                                            value=""
                                                            disabled
                                                        >
                                                            Select item
                                                        </option>

                                                        {itemOptions.map(
                                                            (item) => (
                                                                <option
                                                                    key={
                                                                        item.id
                                                                    }
                                                                    value={
                                                                        item.id
                                                                    }
                                                                >
                                                                    {item.name}{' '}
                                                                    ({item.sku})
                                                                </option>
                                                            ),
                                                        )}
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
                                                    />
                                                    <InputError
                                                        message={
                                                            errors.supplier_sku
                                                        }
                                                    />
                                                </div>

                                                <div className="grid gap-2">
                                                    <Label htmlFor="description">
                                                        Description
                                                    </Label>
                                                    <Input
                                                        id="description"
                                                        name="description"
                                                    />
                                                    <InputError
                                                        message={
                                                            errors.description
                                                        }
                                                    />
                                                </div>

                                                <div className="grid gap-2">
                                                    <Label htmlFor="purchase_unit_of_measure_id">
                                                        Purchase unit
                                                    </Label>

                                                    <select
                                                        id="purchase_unit_of_measure_id"
                                                        name="purchase_unit_of_measure_id"
                                                        required
                                                        defaultValue=""
                                                        className="h-9 rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                                    >
                                                        <option
                                                            value=""
                                                            disabled
                                                        >
                                                            Select unit
                                                        </option>

                                                        {unitOptions.map(
                                                            (unit) => (
                                                                <option
                                                                    key={
                                                                        unit.id
                                                                    }
                                                                    value={
                                                                        unit.id
                                                                    }
                                                                >
                                                                    {unit.name}{' '}
                                                                    (
                                                                    {
                                                                        unit.symbol
                                                                    }
                                                                    )
                                                                </option>
                                                            ),
                                                        )}
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
                                                    />
                                                    <p className="text-xs text-muted-foreground">
                                                        Quantity in the
                                                        inventory item's base
                                                        unit represented by one
                                                        purchase unit.
                                                    </p>
                                                    <InputError
                                                        message={
                                                            errors.base_quantity
                                                        }
                                                    />
                                                </div>

                                                <Button
                                                    type="submit"
                                                    disabled={processing}
                                                >
                                                    Add supplier item
                                                </Button>
                                            </>
                                        )}
                                    </Form>
                                </div>
                            )}
                    </div>
                </div>
            </div>
        </>
    );
}

EditSupplier.layout = {
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
