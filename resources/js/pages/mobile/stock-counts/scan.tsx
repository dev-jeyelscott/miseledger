import { Head, router, useHttp } from '@inertiajs/react';
import { Boxes, CheckCircle2 } from 'lucide-react';
import { useRef, useState } from 'react';

import { EmptyState } from '@/components/empty-state';
import { QuantityKeypad } from '@/components/mobile/quantity-keypad';
import { CameraScanner } from '@/components/mobile/scanner/camera-scanner';
import type { CameraScannerHandle } from '@/components/mobile/scanner/camera-scanner';
import { ManualEntrySheet } from '@/components/mobile/scanner/manual-entry-sheet';
import { SelectionList } from '@/components/mobile/scanner/selection-list';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import mobileScan from '@/routes/mobile/scan';
import mobileStockCounts from '@/routes/mobile/stock-counts';
import type { MobileActiveLocation, ScannedItem } from '@/types/mobile';

type DraftLine = {
    inventoryItemId: number;
    itemName: string;
    unitId: number;
    unitSymbol: string;
    physicalQuantity: string;
};

type StockCountContext = {
    stockCountId: number | null;
    stockCountNumber: string | null;
    storageLocationId: number | null;
    storageLocationName: string | null;
    draftLines: DraftLine[];
};

type StockCountScanProps = {
    activeLocation: MobileActiveLocation;
    context: StockCountContext;
};

type ScanLookupHttpErrorPayload = { notFound?: true; query?: string };

/** Scan → Physical Qty → Next, reusing Spec 2's scanner mounted in "count mode". */
export default function StockCountScan({
    activeLocation,
    context,
}: StockCountScanProps) {
    const [cameraDenied, setCameraDenied] = useState(false);
    const [manualSheetOpen, setManualSheetOpen] = useState(false);
    const [selectionItems, setSelectionItems] = useState<ScannedItem[] | null>(
        null,
    );
    const [matchedItem, setMatchedItem] = useState<ScannedItem | null>(null);
    const [quantity, setQuantity] = useState('0');
    const [submitting, setSubmitting] = useState(false);
    const scannerRef = useRef<CameraScannerHandle>(null);

    const lookup = useHttp<
        { value: string; location_id: number | null },
        { match: ScannedItem } | { matches: ScannedItem[] }
    >({ value: '', location_id: activeLocation?.id ?? null });

    /** Pre-fill the keypad with an already-counted line's quantity (decision #35.A resume-safety). */
    function openQuantity(item: ScannedItem) {
        const existingLine = context.draftLines.find(
            (line) =>
                line.inventoryItemId === item.inventoryItemId &&
                line.unitId === item.matchedUnit.id,
        );

        setMatchedItem(item);
        setQuantity(existingLine?.physicalQuantity ?? '0');
    }

    function handleDecode(value: string) {
        lookup.setData({ value, location_id: activeLocation?.id ?? null });
        lookup.post(mobileScan.lookup.url(), {
            onSuccess: (response) => {
                if ('match' in response) {
                    openQuantity(response.match);
                } else if ('matches' in response) {
                    setSelectionItems(response.matches);
                }
            },
            onHttpException: (response) => {
                if (response.status === 404) {
                    const data = response.data as ScanLookupHttpErrorPayload;

                    return data.notFound === true;
                }

                return false;
            },
        });
    }

    function selectFromList(item: ScannedItem) {
        setSelectionItems(null);
        setManualSheetOpen(false);
        openQuantity(item);
    }

    function closeQuantity() {
        setMatchedItem(null);
        scannerRef.current?.resetScan();
    }

    function submitLine() {
        if (matchedItem === null || submitting) {
            return;
        }

        setSubmitting(true);

        router.post(
            mobileStockCounts.lines.store.url(),
            {
                stock_count_id: context.stockCountId,
                storage_location_id: context.storageLocationId,
                inventory_item_id: matchedItem.inventoryItemId,
                unit_id: matchedItem.matchedUnit.id,
                quantity,
            },
            {
                onFinish: () => setSubmitting(false),
            },
        );
    }

    if (activeLocation === null) {
        return (
            <>
                <Head title="Stock count" />
                <EmptyState
                    icon={Boxes}
                    title="No locations configured"
                    description="Add a location on desktop before you can use MiseLedger on mobile."
                />
            </>
        );
    }

    if (matchedItem !== null) {
        return (
            <>
                <Head title="Count item" />

                <div className="space-y-4">
                    <div>
                        <h1 className="text-lg font-semibold">
                            {matchedItem.name}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {matchedItem.sku ?? 'No SKU'}
                        </p>
                    </div>

                    <QuantityKeypad
                        value={quantity}
                        onChange={setQuantity}
                        unitLabel={matchedItem.matchedUnit.symbol}
                    />

                    <div className="flex gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            className="flex-1"
                            onClick={closeQuantity}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            className="flex-1"
                            disabled={submitting || quantity === ''}
                            onClick={submitLine}
                        >
                            Next
                        </Button>
                    </div>
                </div>
            </>
        );
    }

    return (
        <>
            <Head title="Stock count" />

            <div className="space-y-4">
                <div>
                    <h1 className="text-lg font-semibold">
                        {context.stockCountNumber ?? 'New count'}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {context.storageLocationName}
                    </p>
                </div>

                {context.draftLines.length > 0 ? (
                    <div className="flex items-center justify-between rounded-md border border-input px-4 py-2 text-sm">
                        <span className="flex items-center gap-1.5 font-medium">
                            <CheckCircle2
                                className="size-4 text-primary"
                                aria-hidden="true"
                            />
                            {context.draftLines.length}{' '}
                            {context.draftLines.length === 1 ? 'item' : 'items'}{' '}
                            counted
                        </span>
                        {context.stockCountId !== null ? (
                            <Button asChild size="sm" variant="secondary">
                                <a
                                    href={mobileStockCounts.review.url(
                                        context.stockCountId,
                                    )}
                                >
                                    Review
                                </a>
                            </Button>
                        ) : null}
                    </div>
                ) : null}

                {!cameraDenied ? (
                    <CameraScanner
                        ref={scannerRef}
                        onDecode={handleDecode}
                        onPermissionDenied={() => setCameraDenied(true)}
                        paused={selectionItems !== null}
                    />
                ) : (
                    <ManualEntrySheet
                        variant="inline"
                        onSelect={selectFromList}
                        onEnableCamera={() => setCameraDenied(false)}
                    />
                )}

                {!cameraDenied ? (
                    <button
                        type="button"
                        onClick={() => setManualSheetOpen(true)}
                        className="text-sm font-medium text-primary underline-offset-2 hover:underline"
                    >
                        Search manually instead
                    </button>
                ) : null}
            </div>

            {!cameraDenied ? (
                <ManualEntrySheet
                    variant="sheet"
                    open={manualSheetOpen}
                    onOpenChange={setManualSheetOpen}
                    onSelect={selectFromList}
                />
            ) : null}

            <Sheet
                open={selectionItems !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setSelectionItems(null);
                    }
                }}
            >
                <SheetContent
                    side="bottom"
                    className="max-h-[80dvh] overflow-y-auto"
                >
                    <SheetHeader>
                        <SheetTitle>Multiple matches</SheetTitle>
                    </SheetHeader>
                    <div className="px-4 pb-4">
                        {selectionItems ? (
                            <SelectionList
                                items={selectionItems}
                                onSelect={selectFromList}
                            />
                        ) : null}
                    </div>
                </SheetContent>
            </Sheet>
        </>
    );
}
