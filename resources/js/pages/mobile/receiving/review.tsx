import { Head, router } from '@inertiajs/react';
import { AlertTriangle, Trash2 } from 'lucide-react';
import { useState } from 'react';

import { Button } from '@/components/ui/button';
import mobileReceiving from '@/routes/mobile/receiving';
import mobileReceivingLines from '@/routes/mobile/receiving/lines';

type ReceivingLine = {
    id: number;
    itemName: string;
    quantity: string;
    unitSymbol: string;
    unitCost: string | null;
    totalCost: string | null;
    hasZeroCost: boolean;
};

type ReceivingReviewProps = {
    goodsReceipt: {
        id: number;
        number: string;
        status: string;
        purchaseOrderNumber: string;
        isAdHoc: boolean;
        supplierName: string;
        lines: ReceivingLine[];
        hasZeroCostLine: boolean;
    };
    canFinalize: boolean;
    canViewCosts: boolean;
};

/** Full line list, totals, and finalize (Spec 3: the one destructive, irreversible action). */
export default function ReceivingReview({
    goodsReceipt,
    canFinalize,
    canViewCosts,
}: ReceivingReviewProps) {
    const [finalizing, setFinalizing] = useState(false);
    const isDraft = goodsReceipt.status === 'draft';

    function removeLine(lineId: number) {
        router.delete(
            mobileReceivingLines.destroy.url({
                goodsReceipt: goodsReceipt.id,
                line: lineId,
            }),
        );
    }

    function finalize() {
        if (finalizing) {
            return;
        }

        setFinalizing(true);

        router.post(
            mobileReceiving.finalize.url(goodsReceipt.id),
            {},
            { onFinish: () => setFinalizing(false) },
        );
    }

    return (
        <>
            <Head title="Review receipt" />

            <div className="space-y-4">
                <div>
                    <h1 className="text-lg font-semibold">Review receipt</h1>
                    <p className="text-sm text-muted-foreground">
                        {goodsReceipt.supplierName} ·{' '}
                        {goodsReceipt.isAdHoc
                            ? 'Ad-hoc'
                            : goodsReceipt.purchaseOrderNumber}
                    </p>
                </div>

                {canViewCosts && goodsReceipt.hasZeroCostLine ? (
                    <div
                        role="alert"
                        className="flex items-start gap-2 rounded-md border border-destructive/50 bg-destructive/10 px-4 py-3 text-sm text-destructive"
                    >
                        <AlertTriangle
                            className="mt-0.5 size-4 shrink-0"
                            aria-hidden="true"
                        />
                        <span>
                            One or more lines have no supplier price on file and
                            will post at zero cost. This will skew average cost
                            until corrected on desktop.
                        </span>
                    </div>
                ) : null}

                <ul className="space-y-2">
                    {goodsReceipt.lines.map((line) => (
                        <li
                            key={line.id}
                            className="flex items-center justify-between gap-2 rounded-md border border-input px-4 py-3"
                        >
                            <div className="min-w-0">
                                <p className="truncate font-medium">
                                    {line.itemName}
                                </p>
                                <p className="text-sm text-muted-foreground tabular-nums">
                                    {line.quantity} {line.unitSymbol}
                                    {canViewCosts && line.totalCost !== null
                                        ? ` · ${line.totalCost}`
                                        : ''}
                                    {line.hasZeroCost
                                        ? ' · No cost on file'
                                        : ''}
                                </p>
                            </div>
                            {isDraft ? (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    aria-label={`Remove ${line.itemName} from this receipt`}
                                    onClick={() => removeLine(line.id)}
                                >
                                    <Trash2
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                </Button>
                            ) : null}
                        </li>
                    ))}
                </ul>

                {isDraft && canFinalize ? (
                    <Button
                        type="button"
                        className="min-h-[44px] w-full"
                        disabled={finalizing}
                        onClick={finalize}
                    >
                        Finalize receipt {goodsReceipt.number}
                    </Button>
                ) : null}

                {!isDraft ? (
                    <p className="text-center text-sm text-muted-foreground">
                        This receipt has already been finalized.
                    </p>
                ) : null}
            </div>
        </>
    );
}
