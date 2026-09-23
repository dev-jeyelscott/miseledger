import { Head } from '@inertiajs/react';
import { useState } from 'react';

import { cn } from '@/lib/utils';
import type { MobileActiveLocation, ScannedItem } from '@/types/mobile';

import { ItemActionHub } from '../scan/item-action-hub';

type StockItemProps = {
    activeLocation: MobileActiveLocation;
    item: ScannedItem;
};

/**
 * Read-only current-stock detail for one item (Spec 8, decision #31.A):
 * quantity, unit, per-storage-location breakdown, and "key status" read
 * literally from what the system already tracks. Writes only happen behind
 * the shared item action hub, reached via "View actions".
 */
export default function StockItem({ activeLocation, item }: StockItemProps) {
    const [hubOpen, setHubOpen] = useState(false);

    const quantity = Number(item.stockAtActiveLocation.quantityOnHand);
    const isLowStock = quantity <= 0;

    return (
        <>
            <Head title={item.name} />

            <div className="space-y-6">
                <div className="space-y-1">
                    <p className="text-sm text-muted-foreground">
                        {item.sku ?? 'No SKU'}
                    </p>
                    <h1 className="text-lg font-semibold">{item.name}</h1>
                    <p className="text-sm text-muted-foreground">
                        {activeLocation?.name}
                    </p>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <span className="rounded-md bg-primary/10 px-1.5 py-0.5 text-[10px] font-medium text-primary">
                        Active
                    </span>
                    {isLowStock ? (
                        <span className="rounded-md bg-muted px-1.5 py-0.5 text-[10px] font-medium text-muted-foreground">
                            Low stock
                        </span>
                    ) : null}
                </div>

                <div className="rounded-md border border-input bg-card px-4 py-6 text-center">
                    <p
                        className={cn(
                            'text-4xl font-semibold tabular-nums',
                            isLowStock && 'text-destructive',
                        )}
                    >
                        {item.stockAtActiveLocation.quantityOnHand}
                    </p>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {item.stockAtActiveLocation.unitSymbol} on hand
                    </p>
                </div>

                <div className="space-y-2">
                    <h2 className="text-sm font-semibold text-muted-foreground">
                        By storage location
                    </h2>

                    {item.stockAtActiveLocation.byStorageLocation.length ===
                    0 ? (
                        <p className="text-sm text-muted-foreground">
                            No storage-location breakdown available.
                        </p>
                    ) : (
                        <table className="w-full text-sm">
                            <tbody>
                                {item.stockAtActiveLocation.byStorageLocation.map(
                                    (balance) => (
                                        <tr
                                            key={balance.storageLocationId}
                                            className="border-b border-input last:border-b-0"
                                        >
                                            <td className="py-2 pr-2 text-left">
                                                {balance.name}
                                            </td>
                                            <td className="py-2 text-right tabular-nums">
                                                {balance.quantityOnHand}{' '}
                                                {
                                                    item.stockAtActiveLocation
                                                        .unitSymbol
                                                }
                                            </td>
                                        </tr>
                                    ),
                                )}
                            </tbody>
                        </table>
                    )}
                </div>

                <button
                    type="button"
                    onClick={() => setHubOpen(true)}
                    className="flex min-h-[44px] w-full items-center justify-center rounded-md border border-input px-4 py-3 text-sm font-medium hover:bg-accent"
                >
                    View actions
                </button>
            </div>

            <ItemActionHub
                item={hubOpen ? item : null}
                onOpenChange={(open) => setHubOpen(open)}
            />
        </>
    );
}
