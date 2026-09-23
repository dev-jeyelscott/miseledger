import { Head, router } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { useState } from 'react';

import { Button } from '@/components/ui/button';
import mobileTransfers from '@/routes/mobile/transfers';

type StockTransferLine = {
    id: number;
    itemName: string;
    quantity: string;
    unitSymbol: string;
};

type StockTransferReviewProps = {
    stockTransfer: {
        id: number;
        number: string;
        status: string;
        fromLocationName: string;
        fromStorageLocationName: string;
        toLocationName: string;
        toStorageLocationName: string;
        lines: StockTransferLine[];
    };
};

/**
 * Full source/destination/item/quantity review, never skippable before
 * Submit (decision #38.A). The direction header stays pinned so shipping
 * stock the wrong way is never ambiguous.
 */
export default function StockTransferReview({
    stockTransfer,
}: StockTransferReviewProps) {
    const [submitting, setSubmitting] = useState(false);
    const isDraft = stockTransfer.status === 'draft';

    function submit() {
        if (submitting) {
            return;
        }

        setSubmitting(true);

        router.post(
            mobileTransfers.store.url(stockTransfer.id),
            {},
            { onFinish: () => setSubmitting(false) },
        );
    }

    return (
        <>
            <Head title="Review transfer" />

            <div className="space-y-4">
                <div>
                    <h1 className="text-lg font-semibold">Review transfer</h1>
                    <p className="text-sm text-muted-foreground">
                        {stockTransfer.number}
                    </p>
                </div>

                <div className="rounded-md border border-input bg-muted/40 px-4 py-3">
                    <p className="flex items-center gap-1.5 text-sm font-medium">
                        Moving from{' '}
                        <span className="font-semibold">
                            {stockTransfer.fromStorageLocationName}
                        </span>
                        <ArrowRight
                            className="size-4 shrink-0"
                            aria-hidden="true"
                        />
                        to{' '}
                        <span className="font-semibold">
                            {stockTransfer.toLocationName} ·{' '}
                            {stockTransfer.toStorageLocationName}
                        </span>
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

                {isDraft ? (
                    <Button
                        type="button"
                        className="min-h-[44px] w-full"
                        disabled={
                            submitting || stockTransfer.lines.length === 0
                        }
                        onClick={submit}
                    >
                        Submit transfer {stockTransfer.number}
                    </Button>
                ) : (
                    <p className="text-center text-sm text-muted-foreground">
                        This transfer has already been submitted.
                    </p>
                )}
            </div>
        </>
    );
}
