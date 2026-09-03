import { Form, Head } from '@inertiajs/react';
import { History, Lock } from 'lucide-react';
import { useEffect } from 'react';
import SupplierController from '@/actions/App/Http/Controllers/Suppliers/SupplierController';
import SupplierItemController from '@/actions/App/Http/Controllers/Suppliers/SupplierItemController';
import { EmptyState } from '@/components/empty-state';
import { PreviousPageButton } from '@/components/navigation/previous-page-button';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { useDirtyFormNavigation } from '@/hooks/use-dirty-form-navigation';
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

type Props = {
    supplier: {
        id: number;
        name: string;
        code: string;
        active: boolean;
    };

    supplierItem: SupplierItem;
    itemOptions: ItemOption[];
    unitOptions: UnitOption[];
    timezone: string;
    canManage: boolean;
    canViewCosts: boolean;
};

/** Format an ISO instant in the organization timezone with a consistent locale. */
function formatOrganizationDate(value: string, timezone: string): string {
    return new Intl.DateTimeFormat('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        timeZone: timezone,
        timeZoneName: 'short',
    }).format(new Date(value));
}

function DirtyStateTracker({
    dirty,
    onChange,
}: {
    dirty: boolean;
    onChange: (dirty: boolean) => void;
}) {
    useEffect(() => {
        onChange(dirty);
    }, [dirty, onChange]);

    return null;
}

export default function EditSupplierItem({
    supplier,
    supplierItem,
    itemOptions,
    unitOptions,
    timezone,
    canManage,
    canViewCosts,
}: Props) {
    const dirtyFormNavigation = useDirtyFormNavigation(
        'You have unsaved supplier item changes. Leave without saving them?',
    );

    const headerActions = (
        <>
            <StatusBadge
                label={supplierItem.active ? 'Active' : 'Inactive'}
                variant={supplierItem.active ? 'success' : 'neutral'}
            />
            <PreviousPageButton
                variant="outline"
                fallback={SupplierController.edit(supplier.id).url}
                onNavigate={dirtyFormNavigation.confirmNavigation}
            >
                Back to supplier
            </PreviousPageButton>
        </>
    );

    return (
        <>
            <Head title={supplierItem.inventoryItem.name} />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <PageHeader
                    title={supplierItem.inventoryItem.name}
                    description={
                        <>
                            {supplier.name} ·{' '}
                            <span className="font-mono">
                                {supplierItem.supplierSku}
                            </span>
                        </>
                    }
                    actions={headerActions}
                />

                <div className="grid gap-6 xl:grid-cols-2">
                    <div className="rounded-xl border border-border bg-card p-5 shadow-sm">
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
                                {({ processing, errors, isDirty }) => (
                                    <>
                                        <DirtyStateTracker
                                            dirty={isDirty}
                                            onChange={
                                                dirtyFormNavigation.setIsDirty
                                            }
                                        />

                                        <Field
                                            id="inventory_item_id"
                                            label="Inventory item"
                                            error={errors.inventory_item_id}
                                        >
                                            <NativeSelect
                                                name="inventory_item_id"
                                                defaultValue={
                                                    supplierItem.inventoryItem
                                                        .id
                                                }
                                                required
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
                                                defaultValue={
                                                    supplierItem.supplierSku
                                                }
                                            />
                                        </Field>

                                        <Field
                                            id="description"
                                            label="Description"
                                            error={errors.description}
                                        >
                                            <Input
                                                name="description"
                                                defaultValue={
                                                    supplierItem.description ??
                                                    ''
                                                }
                                            />
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
                                                defaultValue={
                                                    supplierItem.purchaseUnit.id
                                                }
                                                required
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
                                                defaultValue={
                                                    supplierItem.baseQuantity
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
                                                    supplierItem.active
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

                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            {processing
                                                ? 'Saving…'
                                                : 'Save supplier item'}
                                        </Button>
                                    </>
                                )}
                            </Form>
                        ) : (
                            <dl className="grid gap-4 text-sm sm:grid-cols-2">
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
                                        <StatusBadge
                                            label={
                                                supplierItem.active
                                                    ? 'Active'
                                                    : 'Inactive'
                                            }
                                            variant={
                                                supplierItem.active
                                                    ? 'success'
                                                    : 'neutral'
                                            }
                                        />
                                    </dd>
                                </div>
                            </dl>
                        )}
                    </div>

                    <div className="space-y-6">
                        {canViewCosts ? (
                            <>
                                <div className="rounded-xl border border-border bg-card p-5 shadow-sm">
                                    <h2 className="font-medium">
                                        Current price
                                    </h2>

                                    <p className="mt-3 text-2xl font-semibold tabular-nums">
                                        {supplierItem.currentPrice === null
                                            ? 'Not set'
                                            : `${supplierItem.currency} ${supplierItem.currentPrice}`}
                                    </p>

                                    {canManage &&
                                        supplier.active &&
                                        supplierItem.active && (
                                            <Form
                                                {...SupplierItemController.storePrice.form(
                                                    [
                                                        supplier.id,
                                                        supplierItem.id,
                                                    ],
                                                )}
                                                className="mt-5 flex max-w-sm items-end gap-3"
                                                resetOnSuccess
                                            >
                                                {({ processing, errors }) => (
                                                    <>
                                                        <Field
                                                            id="price"
                                                            label="New price"
                                                            helper="Adds a new price entry. It does not edit or delete previous prices."
                                                            error={errors.price}
                                                            className="flex-1"
                                                        >
                                                            <Input
                                                                name="price"
                                                                type="number"
                                                                required
                                                                min="0"
                                                                step="0.0001"
                                                            />
                                                        </Field>

                                                        <Button
                                                            type="submit"
                                                            disabled={
                                                                processing
                                                            }
                                                        >
                                                            {processing
                                                                ? 'Recording…'
                                                                : 'Record price'}
                                                        </Button>
                                                    </>
                                                )}
                                            </Form>
                                        )}
                                </div>

                                <div className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                                    <div className="border-b border-border px-5 py-4">
                                        <h2 className="font-medium">
                                            Price history
                                        </h2>
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            Every supplier price is preserved
                                            permanently. Recording a new price
                                            adds an entry here; it never edits
                                            or removes a previous price.
                                        </p>
                                    </div>

                                    {supplierItem.prices.length === 0 ? (
                                        <EmptyState
                                            className="px-5 py-8"
                                            icon={History}
                                            title="No supplier prices recorded"
                                            description="Record a price above to start this item's append-only price history."
                                        />
                                    ) : (
                                        <div className="divide-y divide-border">
                                            {supplierItem.prices.map(
                                                (price) => (
                                                    <div
                                                        key={price.id}
                                                        className="flex items-center justify-between gap-4 px-5 py-4"
                                                    >
                                                        <span className="font-medium tabular-nums">
                                                            {price.currency}{' '}
                                                            {price.price}
                                                        </span>

                                                        <time
                                                            dateTime={
                                                                price.effectiveAt
                                                            }
                                                            className="text-sm text-muted-foreground"
                                                        >
                                                            {formatOrganizationDate(
                                                                price.effectiveAt,
                                                                timezone,
                                                            )}
                                                        </time>
                                                    </div>
                                                ),
                                            )}
                                        </div>
                                    )}
                                </div>
                            </>
                        ) : (
                            <div className="rounded-xl border border-border bg-card shadow-sm">
                                <EmptyState
                                    className="px-5 py-8"
                                    icon={Lock}
                                    title="Pricing is hidden"
                                    description="You don't have permission to view supplier costs."
                                />
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}

EditSupplierItem.layout = (page: Props) => ({
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
        {
            title: 'Suppliers',
            href: SupplierController.index(),
        },
        {
            title: page.supplier.name,
            href: SupplierController.edit(page.supplier.id),
        },
        {
            title: page.supplierItem.supplierSku,
            href: SupplierItemController.edit([
                page.supplier.id,
                page.supplierItem.id,
            ]),
        },
    ],
});
