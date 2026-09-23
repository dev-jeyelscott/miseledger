import { Head, router, useHttp } from '@inertiajs/react';
import { ArrowRight, Boxes, CheckCircle2 } from 'lucide-react';
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
import mobileTransfers from '@/routes/mobile/transfers';
import mobileTransfersLines from '@/routes/mobile/transfers/lines';
import type { MobileActiveLocation, ScannedItem } from '@/types/mobile';

type StockTransferContext = {
    stockTransferId: number | null;
    stockTransferNumber: string | null;
    fromStorageLocationId: number;
    fromStorageLocationName: string;
    toLocationId: number;
    toLocationName: string;
    toStorageLocationId: number;
    toStorageLocationName: string;
    draftLines: {
        inventoryItemId: number;
        itemName: string;
        unitId: number;
        unitSymbol: string;
        quantity: string;
    }[];
};

type StockTransferScanProps = {
    activeLocation: MobileActiveLocation;
    context: StockTransferContext;
    prefillItem: ScannedItem | null;
};

type ScanLookupHttpErrorPayload = { notFound?: true; query?: string };

/** Scan → Qty → Next, reusing Spec 2's scanner mounted in "transfer mode" (decision #2: stays in scanner). */
export default function StockTransferScan({
    activeLocation,
    context,
    prefillItem,
}: StockTransferScanProps) {
    const [cameraDenied, setCameraDenied] = useState(false);
    const [manualSheetOpen, setManualSheetOpen] = useState(false);
    const [selectionItems, setSelectionItems] = useState<ScannedItem[] | null>(
        null,
    );
    const [matchedItem, setMatchedItem] = useState<ScannedItem | null>(
        prefillItem,
    );
    const [quantity, setQuantity] = useState('0');
    const [submitting, setSubmitting] = useState(false);
    const scannerRef = useRef<CameraScannerHandle>(null);

    const lookup = useHttp<
        { value: string; location_id: number | null },
        { match: ScannedItem } | { matches: ScannedItem[] }
    >({ value: '', location_id: activeLocation?.id ?? null });

    function handleDecode(value: string) {
        lookup.setData({ value, location_id: activeLocation?.id ?? null });
        lookup.post(mobileScan.lookup.url(), {
            onSuccess: (response) => {
                if ('match' in response) {
                    setMatchedItem(response.match);
                    setQuantity('0');
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
        setMatchedItem(item);
        setQuantity('0');
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
            mobileTransfersLines.store.url(),
            {
                stock_transfer_id: context.stockTransferId,
                from_storage_location_id: context.fromStorageLocationId,
                to_location_id: context.toLocationId,
                to_storage_location_id: context.toStorageLocationId,
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
                <Head title="Transfer stock" />
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
                <Head title="Transfer item" />

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
                            disabled={
                                submitting ||
                                quantity === '0' ||
                                quantity === ''
                            }
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
            <Head title="Transfer stock" />

            <div className="space-y-4">
                <div className="rounded-md border border-input px-4 py-2 text-sm">
                    <p className="flex items-center gap-1.5 font-medium">
                        {context.fromStorageLocationName}
                        <ArrowRight
                            className="size-4 shrink-0"
                            aria-hidden="true"
                        />
                        {context.toLocationName} ·{' '}
                        {context.toStorageLocationName}
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
                            added
                        </span>
                        {context.stockTransferId !== null ? (
                            <Button asChild size="sm" variant="secondary">
                                <a
                                    href={mobileTransfers.review.url(
                                        context.stockTransferId,
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
