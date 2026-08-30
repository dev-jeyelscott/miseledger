import { Form, Head, Link } from '@inertiajs/react';
import SupplierController from '@/actions/App/Http/Controllers/Suppliers/SupplierController';
import SupplierItemController from '@/actions/App/Http/Controllers/Suppliers/SupplierItemController';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
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
                <PageHeader title={supplier.name} description={supplier.code} />

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
                                            <Field
                                                id="name"
                                                label="Name"
                                                error={errors.name}
                                            >
                                                <Input
                                                    name="name"
                                                    required
                                                    defaultValue={supplier.name}
                                                />
                                            </Field>

                                            <Field
                                                id="code"
                                                label="Code"
                                                error={errors.code}
                                            >
                                                <Input
                                                    name="code"
                                                    required
                                                    defaultValue={supplier.code}
                                                />
                                            </Field>

                                            <Field
                                                id="contact_name"
                                                label="Contact name"
                                                error={errors.contact_name}
                                            >
                                                <Input
                                                    name="contact_name"
                                                    defaultValue={
                                                        supplier.contactName ??
                                                        ''
                                                    }
                                                />
                                            </Field>

                                            <Field
                                                id="email"
                                                label="Email"
                                                error={errors.email}
                                            >
                                                <Input
                                                    name="email"
                                                    type="email"
                                                    defaultValue={
                                                        supplier.email ?? ''
                                                    }
                                                />
                                            </Field>

                                            <Field
                                                id="phone"
                                                label="Phone"
                                                error={errors.phone}
                                            >
                                                <Input
                                                    name="phone"
                                                    defaultValue={
                                                        supplier.phone ?? ''
                                                    }
                                                />
                                            </Field>

                                            <Field
                                                id="payment_terms"
                                                label="Payment terms"
                                                error={errors.payment_terms}
                                            >
                                                <Input
                                                    name="payment_terms"
                                                    defaultValue={
                                                        supplier.paymentTerms ??
                                                        ''
                                                    }
                                                />
                                            </Field>

                                            <Field
                                                id="lead_time_days"
                                                label="Lead time (days)"
                                                error={errors.lead_time_days}
                                            >
                                                <Input
                                                    name="lead_time_days"
                                                    type="number"
                                                    min="0"
                                                    step="1"
                                                    defaultValue={
                                                        supplier.leadTimeDays ??
                                                        ''
                                                    }
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
                                                        supplier.active
                                                            ? '1'
                                                            : '0'
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

                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            {processing
                                                ? 'Saving…'
                                                : 'Save supplier'}
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

                                                <p className="mt-1 text-sm">
                                                    {item.currentPrice === null
                                                        ? 'No price'
                                                        : `${item.currency} ${item.currentPrice}`}
                                                </p>
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

                                                <Field
                                                    id="inventory_item_id"
                                                    label="Inventory item"
                                                    error={
                                                        errors.inventory_item_id
                                                    }
                                                >
                                                    <NativeSelect
                                                        name="inventory_item_id"
                                                        required
                                                        defaultValue=""
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
                                                    </NativeSelect>
                                                </Field>

                                                <Field
                                                    id="supplier_sku"
                                                    label="Supplier SKU"
                                                    error={errors.supplier_sku}
                                                >
                                                    <Input
                                                        name="supplier_sku"
                                                        required
                                                    />
                                                </Field>

                                                <Field
                                                    id="description"
                                                    label="Description"
                                                    error={errors.description}
                                                >
                                                    <Input name="description" />
                                                </Field>

                                                <Field
                                                    id="purchase_unit_of_measure_id"
                                                    label="Purchase unit"
                                                    error={
                                                        errors.purchase_unit_of_measure_id
                                                    }
                                                >
                                                    <NativeSelect
                                                        name="purchase_unit_of_measure_id"
                                                        required
                                                        defaultValue=""
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
                                                    </NativeSelect>
                                                </Field>

                                                <Field
                                                    id="base_quantity"
                                                    label="Base quantity"
                                                    helper="Quantity in the inventory item's base unit represented by one purchase unit."
                                                    error={errors.base_quantity}
                                                >
                                                    <Input
                                                        name="base_quantity"
                                                        type="number"
                                                        required
                                                        min="0.000001"
                                                        step="0.000001"
                                                    />
                                                </Field>

                                                <Field
                                                    id="price"
                                                    label="Price"
                                                    helper="Optional initial supplier price. When provided, this becomes the first price-history entry."
                                                    error={errors.price}
                                                >
                                                    <Input
                                                        name="price"
                                                        type="number"
                                                        min="0"
                                                        step="0.0001"
                                                    />
                                                </Field>

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
