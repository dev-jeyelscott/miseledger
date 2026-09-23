import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import mobileStockCounts from '@/routes/mobile/stock-counts';

type VarianceSign = 'negative' | 'positive' | 'zero';

type StockCountReviewLine = {
    id: number;
    itemName: string;
    itemSku: string | null;
    expectedBaseQuantity: string;
    physicalQuantity: string;
    countUnitSymbol: string;
    baseUnitSymbol: string;
    varianceBaseQuantity: string;
    absVarianceBaseQuantity: string;
};

type StockCountReviewProps = {
    stockCount: {
        id: number;
        number: string;
        status: string;
        storageLocationName: string;
        lines: StockCountReviewLine[];
    };
};

/** Classify a persisted decimal variance without JavaScript numeric conversion. */
function varianceSign(value: string): VarianceSign {
    const trimmed = value.trim();
    const negative = trimmed.startsWith('-');
    const unsigned = negative ? trimmed.slice(1) : trimmed;
    const digits = unsigned.replace('.', '');

    if (digits !== '' && /^0+$/.test(digits)) {
        return 'zero';
    }

    return negative ? 'negative' : 'positive';
}

/** Return a visible non-color label for one variance direction, matching desktop's Count Variance report. */
function varianceLabel(sign: VarianceSign): string {
    switch (sign) {
        case 'negative':
            return 'Under';
        case 'positive':
            return 'Over';
        case 'zero':
            return 'Exact';
    }
}

/** Map variance direction to the shared semantic status treatment (matches desktop's `stock-counts/variance` page). */
function varianceVariant(sign: VarianceSign): 'info' | 'success' | 'warning' {
    switch (sign) {
        case 'negative':
            return 'warning';
        case 'positive':
            return 'info';
        case 'zero':
            return 'success';
    }
}

/** Full line list with live expected-vs-physical variance, then submit. */
export default function StockCountReview({
    stockCount,
}: StockCountReviewProps) {
    const [submitting, setSubmitting] = useState(false);
    const isDraft = stockCount.status === 'draft';

    function submit() {
        if (submitting) {
            return;
        }

        setSubmitting(true);

        router.post(
            mobileStockCounts.submit.url(stockCount.id),
            {},
            { onFinish: () => setSubmitting(false) },
        );
    }

    return (
        <>
            <Head title="Review count" />

            <div className="space-y-4">
                <div>
                    <h1 className="text-lg font-semibold">Review count</h1>
                    <p className="text-sm text-muted-foreground">
                        {stockCount.number} · {stockCount.storageLocationName}
                    </p>
                </div>

                <ul className="space-y-2">
                    {stockCount.lines.map((line) => {
                        const sign = varianceSign(line.varianceBaseQuantity);

                        return (
                            <li
                                key={line.id}
                                className="flex items-start justify-between gap-2 rounded-md border border-input px-4 py-3"
                            >
                                <div className="min-w-0">
                                    <p className="truncate font-medium">
                                        {line.itemName}
                                    </p>
                                    <p className="text-sm text-muted-foreground tabular-nums">
                                        {line.physicalQuantity}{' '}
                                        {line.countUnitSymbol} counted ·{' '}
                                        {line.expectedBaseQuantity}{' '}
                                        {line.baseUnitSymbol} expected
                                    </p>
                                </div>
                                <StatusBadge
                                    label={`${varianceLabel(sign)}${sign === 'zero' ? '' : ` ${line.absVarianceBaseQuantity} ${line.baseUnitSymbol}`}`}
                                    variant={varianceVariant(sign)}
                                    className="shrink-0"
                                />
                            </li>
                        );
                    })}
                </ul>

                {isDraft ? (
                    <Button
                        type="button"
                        className="min-h-[44px] w-full"
                        disabled={submitting}
                        onClick={submit}
                    >
                        Submit count {stockCount.number}
                    </Button>
                ) : (
                    <p className="text-center text-sm text-muted-foreground">
                        This count has already been submitted.
                    </p>
                )}
            </div>
        </>
    );
}
