import { Head, Link } from '@inertiajs/react';
import { Boxes, ClipboardList } from 'lucide-react';

import { EmptyState } from '@/components/empty-state';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import mobileStockCounts from '@/routes/mobile/stock-counts';
import type { MobileActiveLocation } from '@/types/mobile';

type OpenStockCount = {
    id: number;
    number: string;
    status: 'draft' | 'submitted';
    storageLocationName: string;
    lineCount: number;
};

type StockCountIndexProps = {
    activeLocation: MobileActiveLocation;
    counts: OpenStockCount[];
};

/** Open (draft/submitted) counts for the active location, plus the new-count entry point. */
export default function StockCountIndex({
    activeLocation,
    counts,
}: StockCountIndexProps) {
    if (activeLocation === null) {
        return (
            <>
                <Head title="Stock counts" />
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
            <Head title="Stock counts" />

            <div className="space-y-4">
                <div>
                    <h1 className="text-lg font-semibold">Stock counts</h1>
                    <p className="text-sm text-muted-foreground">
                        {activeLocation.name}
                    </p>
                </div>

                <Button asChild className="min-h-[44px] w-full">
                    <Link href={mobileStockCounts.create.url()}>
                        <ClipboardList className="size-4" aria-hidden="true" />
                        Start a stock count
                    </Link>
                </Button>

                {counts.length === 0 ? (
                    <EmptyState
                        icon={ClipboardList}
                        title="No open counts"
                        description="Start a stock count above to begin recording physical quantities."
                    />
                ) : (
                    <ul className="space-y-2">
                        {counts.map((count) => (
                            <li key={count.id}>
                                <Link
                                    href={
                                        count.status === 'draft'
                                            ? mobileStockCounts.scan.url({
                                                  query: {
                                                      stock_count_id: count.id,
                                                  },
                                              })
                                            : mobileStockCounts.review.url(
                                                  count.id,
                                              )
                                    }
                                    className="flex min-h-[44px] w-full flex-col gap-1 rounded-md border border-input px-4 py-3 text-left hover:bg-accent"
                                >
                                    <span className="flex items-center justify-between gap-2">
                                        <span className="font-medium">
                                            {count.number}
                                        </span>
                                        <StatusBadge
                                            label={
                                                count.status === 'draft'
                                                    ? 'Draft'
                                                    : 'Submitted'
                                            }
                                            variant={
                                                count.status === 'draft'
                                                    ? 'neutral'
                                                    : 'info'
                                            }
                                        />
                                    </span>
                                    <span className="text-sm text-muted-foreground">
                                        {count.storageLocationName} ·{' '}
                                        {count.lineCount}{' '}
                                        {count.lineCount === 1
                                            ? 'item'
                                            : 'items'}{' '}
                                        counted
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
