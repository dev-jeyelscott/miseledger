import { Head, Link } from '@inertiajs/react';
import { Boxes, PackagePlus } from 'lucide-react';

import { EmptyState } from '@/components/empty-state';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import mobileReceiving from '@/routes/mobile/receiving';
import type { MobileActiveLocation } from '@/types/mobile';

type ReceivablePurchaseOrder = {
    id: number;
    number: string;
    supplierName: string;
    expectedDeliveryDate: string | null;
    overdue: boolean;
};

type ReceivingIndexProps = {
    activeLocation: MobileActiveLocation;
    purchaseOrders: ReceivablePurchaseOrder[];
};

/**
 * PO picker plus the ad-hoc entry point (decision #36.A: always available,
 * never hidden behind "no POs found").
 */
export default function ReceivingIndex({
    activeLocation,
    purchaseOrders,
}: ReceivingIndexProps) {
    if (activeLocation === null) {
        return (
            <>
                <Head title="Receiving" />
                <EmptyState
                    icon={Boxes}
                    title="No locations configured"
                    description="Add a location on desktop before you can use MiseLedger on mobile."
                />
            </>
        );
    }

    return (
        <>
            <Head title="Receiving" />

            <div className="space-y-4">
                <div>
                    <h1 className="text-lg font-semibold">Receiving</h1>
                    <p className="text-sm text-muted-foreground">
                        {activeLocation.name}
                    </p>
                </div>

                <Button asChild className="min-h-[44px] w-full">
                    <Link href={mobileReceiving.create.url()}>
                        <PackagePlus className="size-4" aria-hidden="true" />
                        Start ad-hoc receiving
                    </Link>
                </Button>

                {purchaseOrders.length === 0 ? (
                    <EmptyState
                        icon={PackagePlus}
                        title="No open purchase orders"
                        description="Nothing is currently receivable at this location. Use ad-hoc receiving for an unplanned delivery."
                    />
                ) : (
                    <ul className="space-y-2">
                        {purchaseOrders.map((purchaseOrder) => (
                            <li key={purchaseOrder.id}>
                                <Link
                                    href={mobileReceiving.scan.url({
                                        query: {
                                            purchase_order_id: purchaseOrder.id,
                                        },
                                    })}
                                    className="flex min-h-[44px] w-full flex-col gap-1 rounded-md border border-input px-4 py-3 text-left hover:bg-accent"
                                >
                                    <span className="flex items-center justify-between gap-2">
                                        <span className="font-medium">
                                            {purchaseOrder.number}
                                        </span>
                                        {purchaseOrder.overdue ? (
                                            <Badge variant="destructive">
                                                Overdue
                                            </Badge>
                                        ) : null}
                                    </span>
                                    <span className="text-sm text-muted-foreground">
                                        {purchaseOrder.supplierName}
                                        {purchaseOrder.expectedDeliveryDate
                                            ? ` · Expected ${purchaseOrder.expectedDeliveryDate}`
                                            : ''}
                                    </span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}
