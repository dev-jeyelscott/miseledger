import { Link } from '@inertiajs/react';
import {
    ArrowLeftRight,
    Boxes,
    ClipboardList,
    PackagePlus,
    Trash2,
} from 'lucide-react';
import type { ComponentType } from 'react';

import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import type { ScannedItem, ScannedItemAction } from '@/types/mobile';

type ActionConfig = {
    label: string;
    icon: ComponentType<{ className?: string }>;
    /**
     * The scan-context entry for each workflow (Specs 3–6) is not built yet;
     * the `?from=scan&item_id=` contract this spec fixes is what those
     * routes read to pre-fill the scanned item and return to a live
     * scanner on "back" (see Spec 2 §"Behavior and Flow" step 6).
     */
    href: (item: ScannedItem) => string;
};

const ACTION_CONFIG: Record<ScannedItemAction, ActionConfig> = {
    receive: {
        label: 'Receive',
        icon: PackagePlus,
        href: () => `/mobile/receiving`,
    },
    count: {
        label: 'Count',
        icon: ClipboardList,
        href: () => `/mobile/stock-counts`,
    },
    waste: {
        label: 'Waste',
        icon: Trash2,
        href: (item) =>
            `/mobile/waste?from=scan&item_id=${item.inventoryItemId}`,
    },
    transfer: {
        label: 'Transfer',
        icon: ArrowLeftRight,
        href: (item) =>
            `/mobile/transfers?from=scan&item_id=${item.inventoryItemId}`,
    },
    details: {
        label: 'Stock Details',
        icon: Boxes,
        href: (item) => `/mobile/stock/items/${item.inventoryItemId}?from=scan`,
    },
};

type ItemActionHubProps = {
    item: ScannedItem | null;
    onOpenChange: (open: boolean) => void;
};

/**
 * Bottom sheet over the still-live scanner preview (decision #10.C): shows
 * the matched item, its current stock at the active location, and up to 5
 * permission-filtered action buttons (decision #34.A).
 */
function ItemActionHub({ item, onOpenChange }: ItemActionHubProps) {
    return (
        <Sheet open={item !== null} onOpenChange={onOpenChange}>
            <SheetContent side="bottom" className="max-h-[80dvh]">
                {item ? (
                    <>
                        <SheetHeader>
                            <SheetTitle>{item.name}</SheetTitle>
                            <SheetDescription>
                                {item.sku ?? 'No SKU'} ·{' '}
                                <span className="tabular-nums">
                                    {item.stockAtActiveLocation.quantityOnHand}
                                </span>{' '}
                                {item.stockAtActiveLocation.unitSymbol} on hand
                            </SheetDescription>
                        </SheetHeader>

                        <div className="grid grid-cols-2 gap-2 px-4 pb-4">
                            {item.availableActions.map((action) => {
                                const config = ACTION_CONFIG[action];
                                const Icon = config.icon;

                                return (
                                    <Link
                                        key={action}
                                        href={config.href(item)}
                                        className="flex min-h-[44px] flex-col items-center justify-center gap-1 rounded-md border border-input px-3 py-3 text-sm font-medium hover:bg-accent"
                                    >
                                        <Icon
                                            className="size-5"
                                            aria-hidden="true"
                                        />
                                        {config.label}
                                    </Link>
                                );
                            })}
                        </div>
                    </>
                ) : null}
            </SheetContent>
        </Sheet>
    );
}

export { ItemActionHub };
