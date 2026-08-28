import { Head, Link } from '@inertiajs/react';
import InventoryItemController from '@/actions/App/Http/Controllers/Inventory/InventoryItemController';
import { PreviousPageButton } from '@/components/navigation/previous-page-button';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import type { InventoryItemDetail, InventoryItemType } from '@/types';

type Props = {
    canManage: boolean;
    item: InventoryItemDetail;
};

const itemTypeLabels: Record<InventoryItemType, string> = {
    ingredient: 'Ingredient',
    finished_item: 'Finished item',
    prepared_item: 'Prepared item',
    packaging: 'Packaging',
    consumable: 'Consumable',
};

const symbologyLabels: Record<
    InventoryItemDetail['barcodes'][number]['symbology'],
    string
> = {
    ean_13: 'EAN-13',
    ean_8: 'EAN-8',
    upc_a: 'UPC-A',
    upc_e: 'UPC-E',
    code_128: 'Code 128',
    code_39: 'Code 39',
    other: 'Other',
};

function ItemStatus({ active }: { active: boolean }) {
    return (
        <StatusBadge
            label={active ? 'Active' : 'Inactive'}
            variant={active ? 'success' : 'neutral'}
        />
    );
}

/** Render an organization-scoped, read-only inventory item record. */
export default function ShowInventoryItem({ canManage, item }: Props) {
    return (
        <>
            <Head title={item.name} />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={item.name}
                    description={<span className="font-mono">{item.sku}</span>}
                    actions={
                        <>
                            <PreviousPageButton
                                variant="outline"
                                fallback={InventoryItemController.index().url}
                            >
                                Back to items
                            </PreviousPageButton>
                            {canManage ? (
                                <Button asChild>
                                    <Link
                                        href={InventoryItemController.edit(
                                            item.id,
                                        )}
                                    >
                                        Edit item
                                    </Link>
                                </Button>
                            ) : null}
                        </>
                    }
                />

                <section
                    aria-labelledby="item-details-heading"
                    className="rounded-xl border border-border bg-card p-4 md:p-6"
                >
                    <h2 id="item-details-heading" className="font-semibold">
                        Item details
                    </h2>
                    <dl className="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                        <div>
                            <dt className="text-sm text-muted-foreground">
                                Status
                            </dt>
                            <dd className="mt-1">
                                <ItemStatus active={item.active} />
                            </dd>
                        </div>
                        <div>
                            <dt className="text-sm text-muted-foreground">
                                Item type
                            </dt>
                            <dd className="mt-1 text-sm">
                                {itemTypeLabels[item.type]}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-sm text-muted-foreground">
                                Yield
                            </dt>
                            <dd className="mt-1 text-sm tabular-nums">
                                {item.yieldPercentage}%
                            </dd>
                        </div>
                        <div>
                            <dt className="text-sm text-muted-foreground">
                                Category
                            </dt>
                            <dd className="mt-1 text-sm">
                                {item.inventoryCategory?.name ??
                                    'Uncategorized'}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-sm text-muted-foreground">
                                Brand
                            </dt>
                            <dd className="mt-1 text-sm">
                                {item.inventoryBrand?.name ?? 'Unbranded'}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-sm text-muted-foreground">
                                Product family
                            </dt>
                            <dd className="mt-1 text-sm">
                                {item.inventoryProduct?.name ??
                                    'No product family'}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-sm text-muted-foreground">
                                Model number
                            </dt>
                            <dd className="mt-1 text-sm break-words">
                                {item.modelNumber ?? '—'}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-sm text-muted-foreground">
                                Manufacturer part number
                            </dt>
                            <dd className="mt-1 text-sm break-words">
                                {item.manufacturerPartNumber ?? '—'}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-sm text-muted-foreground">
                                Base UOM
                            </dt>
                            <dd className="mt-1 text-sm">
                                <span className="font-medium">
                                    {item.baseUnitOfMeasure.symbol}
                                </span>{' '}
                                {item.baseUnitOfMeasure.name}
                            </dd>
                        </div>
                        <div className="sm:col-span-2 lg:col-span-3">
                            <dt className="text-sm text-muted-foreground">
                                Description
                            </dt>
                            <dd className="mt-1 text-sm whitespace-pre-wrap">
                                {item.description ?? '—'}
                            </dd>
                        </div>
                    </dl>
                </section>

                <section
                    aria-labelledby="units-heading"
                    className="rounded-xl border border-border bg-card"
                >
                    <div className="border-b border-border px-4 py-4 md:px-6">
                        <h2 id="units-heading" className="font-semibold">
                            Units and conversions
                        </h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Base unit: {item.baseUnitOfMeasure.name} (
                            {item.baseUnitOfMeasure.symbol})
                        </p>
                    </div>
                    {item.unitConversions.length === 0 ? (
                        <p className="px-4 py-6 text-sm text-muted-foreground md:px-6">
                            No alternate conversions configured.
                        </p>
                    ) : (
                        <ul className="divide-y divide-border">
                            {item.unitConversions.map((conversion) => (
                                <li
                                    key={conversion.id}
                                    className="flex flex-wrap items-center justify-between gap-3 px-4 py-4 md:px-6"
                                >
                                    <span className="text-sm">
                                        1 {conversion.unitOfMeasure.symbol} ={' '}
                                        {conversion.quantityInBaseUnit}{' '}
                                        {item.baseUnitOfMeasure.symbol}
                                    </span>
                                    <ItemStatus active={conversion.active} />
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                <section
                    aria-labelledby="barcodes-heading"
                    className="rounded-xl border border-border bg-card"
                >
                    <div className="border-b border-border px-4 py-4 md:px-6">
                        <h2 id="barcodes-heading" className="font-semibold">
                            Barcodes
                        </h2>
                    </div>
                    {item.barcodes.length === 0 ? (
                        <p className="px-4 py-6 text-sm text-muted-foreground md:px-6">
                            No barcodes configured.
                        </p>
                    ) : (
                        <ul className="divide-y divide-border">
                            {item.barcodes.map((barcode) => (
                                <li
                                    key={barcode.id}
                                    className="flex flex-wrap items-center justify-between gap-3 px-4 py-4 md:px-6"
                                >
                                    <div className="min-w-0">
                                        <p className="font-mono text-sm break-all">
                                            {barcode.value}
                                        </p>
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            {symbologyLabels[barcode.symbology]}{' '}
                                            ·{' '}
                                            {barcode.inventoryItemUnit
                                                ? `${barcode.inventoryItemUnit.unitOfMeasure.name} (${barcode.inventoryItemUnit.unitOfMeasure.symbol})`
                                                : 'Base item'}{' '}
                                            ·{' '}
                                            {barcode.isPrimary
                                                ? 'Primary'
                                                : 'Not primary'}
                                        </p>
                                    </div>
                                    <ItemStatus active={barcode.active} />
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </>
    );
}

ShowInventoryItem.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Inventory items', href: InventoryItemController.index() },
        { title: 'Item details', href: InventoryItemController.index() },
    ],
};
