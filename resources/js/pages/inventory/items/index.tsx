import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import InventoryAdjustmentController from '@/actions/App/Http/Controllers/Inventory/InventoryAdjustmentController';
import InventoryCategoryController from '@/actions/App/Http/Controllers/Inventory/InventoryCategoryController';
import InventoryItemController from '@/actions/App/Http/Controllers/Inventory/InventoryItemController';
import OpeningBalanceController from '@/actions/App/Http/Controllers/Inventory/OpeningBalanceController';
import UnitOfMeasureController from '@/actions/App/Http/Controllers/Inventory/UnitOfMeasureController';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import type { InventoryItemListItem } from '@/types';

type Props = {
    items: InventoryItemListItem[];
    canManage: boolean;
};

export default function InventoryItemsIndex({ items, canManage }: Props) {
    return (
        <>
            <Head title="Inventory" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                    <div>
                        <h1 className="text-2xl font-semibold">
                            Inventory items
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Manage inventory master records and their base
                            units.
                        </p>
                    </div>

                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" asChild>
                            <Link href={UnitOfMeasureController.index()}>
                                Units of measure
                            </Link>
                        </Button>

                        <Button variant="outline" asChild>
                            <Link href={InventoryCategoryController.index()}>
                                Categories
                            </Link>
                        </Button>

                        {canManage && (
                            <>
                                <Button variant="outline" asChild>
                                    <Link
                                        href={OpeningBalanceController.create()}
                                    >
                                        Opening balance
                                    </Link>
                                </Button>

                                <Button variant="outline" asChild>
                                    <Link
                                        href={InventoryAdjustmentController.create()}
                                    >
                                        Adjust inventory
                                    </Link>
                                </Button>

                                <Button asChild>
                                    <Link
                                        href={InventoryItemController.create()}
                                    >
                                        <Plus className="size-4" />
                                        New item
                                    </Link>
                                </Button>
                            </>
                        )}
                    </div>
                </div>

                <div className="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <div className="grid grid-cols-[minmax(0,1fr)_130px_150px_110px_100px] gap-4 border-b border-sidebar-border/70 px-5 py-3 text-xs font-medium text-muted-foreground uppercase dark:border-sidebar-border">
                        <span>Item</span>
                        <span>Type</span>
                        <span>Base UOM</span>
                        <span>Conversions</span>
                        <span>Status</span>
                    </div>

                    {items.length === 0 ? (
                        <div className="px-5 py-10 text-center text-sm text-muted-foreground">
                            No inventory items have been created.
                        </div>
                    ) : (
                        <div className="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                            {items.map((item) => (
                                <div
                                    key={item.id}
                                    className="grid grid-cols-[minmax(0,1fr)_130px_150px_110px_100px] items-center gap-4 px-5 py-4"
                                >
                                    <div className="min-w-0">
                                        {canManage ? (
                                            <Link
                                                href={InventoryItemController.edit(
                                                    item.id,
                                                )}
                                                className="font-medium hover:underline"
                                            >
                                                {item.name}
                                            </Link>
                                        ) : (
                                            <p className="font-medium">
                                                {item.name}
                                            </p>
                                        )}

                                        <p className="mt-1 text-sm text-muted-foreground">
                                            {item.sku}
                                        </p>
                                    </div>

                                    <span className="text-sm">
                                        {item.type.replace('_', ' ')}
                                    </span>

                                    <span className="text-sm">
                                        {item.baseUnitOfMeasure.symbol}
                                    </span>

                                    <span className="text-sm">
                                        {item.conversionCount}
                                    </span>

                                    <span className="text-sm">
                                        {item.active ? 'Active' : 'Inactive'}
                                    </span>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}

InventoryItemsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
