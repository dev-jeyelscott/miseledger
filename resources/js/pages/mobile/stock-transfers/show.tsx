import { Head, router } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { useState } from 'react';

import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import mobileTransfers from '@/routes/mobile/transfers';

type StockTransferLine = {
    id: number;
    itemName: string;
    quantity: string;
    unitSymbol: string;
};

type StockTransferStatus = 'draft' | 'shipped' | 'received' | 'cancelled';

type StockTransferShowProps = {
    stockTransfer: {
        id: number;
        number: string;
        status: StockTransferStatus;
        fromLocationName: string;
        fromStorageLocationName: string;
        toLocationName: string;
        toStorageLocationName: string;
        lines: StockTransferLine[];
    };
    canShip: boolean;
    canReceive: boolean;
};

const statusVariant: Record<
    StockTransferStatus,
    'neutral' | 'success' | 'info' | 'danger'
> = {
    draft: 'neutral',
    shipped: 'info',
    received: 'success',
    cancelled: 'danger',
};

const statusLabel: Record<StockTransferStatus, string> = {
    draft: 'Draft — not yet shipped',
    shipped: 'Shipped — awaiting receipt',
    received: 'Received',
    cancelled: 'Cancelled',
};

/**
 * Transfer detail screen: Ship and Receive stay separate, explicit,
 * permission-gated actions from here (decision #6), never merged with
 * Submit. Status copy reuses desktop's `StockTransferStatus` meaning
 * instead of inventing mobile-only wording that could drift from it.
 */
export default function StockTransferShow({
    stockTransfer,
    canShip,
    canReceive,
}: StockTransferShowProps) {
    const [shipping, setShipping] = useState(false);
    const [receiving, setReceiving] = useState(false);

    function ship() {
        if (shipping) {
            return;
        }

        setShipping(true);

        router.post(
            mobileTransfers.ship.url(stockTransfer.id),
            {},
            { onFinish: () => setShipping(false) },
        );
    }

    function receive() {
        if (receiving) {
            return;
        }

        setReceiving(true);

        router.post(
            mobileTransfers.receive.url(stockTransfer.id),
            {},
            { onFinish: () => setReceiving(false) },
        );
    }

    return (
        <>
            <Head title={`Transfer ${stockTransfer.number}`} />

            <div className="space-y-4">
                <div className="space-y-1">
                    <h1 className="text-lg font-semibold">
                        {stockTransfer.number}
                    </h1>
                    <StatusBadge
                        label={statusLabel[stockTransfer.status]}
                        variant={statusVariant[stockTransfer.status]}
                    />
                </div>

                <div className="rounded-md border border-input bg-muted/40 px-4 py-3">
                    <p className="flex items-center gap-1.5 text-sm font-medium">
                        {stockTransfer.fromStorageLocationName}
                        <ArrowRight
                            className="size-4 shrink-0"
                            aria-hidden="true"
                        />
                        {stockTransfer.toLocationName} ·{' '}
                        {stockTransfer.toStorageLocationName}
                    </p>
                </div>

                <ul className="space-y-2">
                    {stockTransfer.lines.map((line) => (
                        <li
                            key={line.id}
                            className="flex items-center justify-between gap-2 rounded-md border border-input px-4 py-3"
                        >
                            <p className="min-w-0 truncate font-medium">
                                {line.itemName}
                            </p>
                            <p className="shrink-0 text-sm text-muted-foreground tabular-nums">
                                {line.quantity} {line.unitSymbol}
                            </p>
                        </li>
                    ))}
                </ul>

                {stockTransfer.status === 'draft' && canShip ? (
                    <Button
                        type="button"
                        className="min-h-[44px] w-full"
                        disabled={shipping}
                        onClick={ship}
                    >
                        Ship now
                    </Button>
                ) : null}

                {stockTransfer.status === 'shipped' && canReceive ? (
                    <Button
                        type="button"
                        className="min-h-[44px] w-full"
                        disabled={receiving}
                        onClick={receive}
                    >
                        Receive transfer
                    </Button>
                ) : null}
            </div>
        </>
    );
}
