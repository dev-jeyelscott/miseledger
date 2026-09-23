import { Head, router, useHttp } from '@inertiajs/react';
import { Boxes, CheckCircle2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

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
import mobileReceiving from '@/routes/mobile/receiving';
import mobileScan from '@/routes/mobile/scan';
import type { MobileActiveLocation, ScannedItem } from '@/types/mobile';

type ReceivingContext = {
    mode: 'purchase_order' | 'ad_hoc';
    purchaseOrderId: number | null;
    supplierId: number | null;
    goodsReceiptId: number | null;
    lineCount: number;
};

type UnitOption = {
    id: number;
    name: string;
    symbol: string;
    isBase: boolean;
};

type ReceivingScanProps = {
    activeLocation: MobileActiveLocation;
    context: ReceivingContext;
};

type ScanLookupHttpErrorPayload = { notFound?: true; query?: string };

/** Scan → Qty → Next, reusing Spec 2's scanner mounted in "receiving mode". */
export default function ReceivingScan({
    activeLocation,
    context,
}: ReceivingScanProps) {
    const [cameraDenied, setCameraDenied] = useState(false);
    const [manualSheetOpen, setManualSheetOpen] = useState(false);
    const [selectionItems, setSelectionItems] = useState<ScannedItem[] | null>(
        null,
    );
    const [matchedItem, setMatchedItem] = useState<ScannedItem | null>(null);
    const [unitOptions, setUnitOptions] = useState<UnitOption[]>([]);
    const [selectedUnitId, setSelectedUnitId] = useState<number | null>(null);
    const [quantity, setQuantity] = useState('0');
    const [submitting, setSubmitting] = useState(false);
    const scannerRef = useRef<CameraScannerHandle>(null);

    const lookup = useHttp<
        { value: string; location_id: number | null },
        { match: ScannedItem } | { matches: ScannedItem[] }
    >({ value: '', location_id: activeLocation?.id ?? null });

    useEffect(() => {
        if (matchedItem === null) {
            return;
        }

        let cancelled = false;

        fetch(
            mobileReceiving.items.units.url({
                inventoryItem: matchedItem.inventoryItemId,
            }),
            { headers: { Accept: 'application/json' } },
        )
            .then((response) => response.json())
            .then((data: { units: UnitOption[] }) => {
                if (cancelled) {
                    return;
                }

                setUnitOptions(data.units);
                setSelectedUnitId(
                    matchedItem.matchedUnit.id ?? data.units[0]?.id ?? null,
                );
            })
            .catch(() => {
                if (!cancelled) {
                    setUnitOptions([matchedItem.matchedUnit]);
                    setSelectedUnitId(matchedItem.matchedUnit.id);
                }
            });

        return () => {
            cancelled = true;
        };
    }, [matchedItem]);

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
        setUnitOptions([]);
        setSelectedUnitId(null);
        scannerRef.current?.resetScan();
    }

    function submitLine() {
        if (matchedItem === null || selectedUnitId === null || submitting) {
            return;
        }

        setSubmitting(true);

        router.post(
            mobileReceiving.lines.store.url(),
            {
                purchase_order_id: context.purchaseOrderId,
                goods_receipt_id: context.goodsReceiptId,
                supplier_id: context.supplierId,
                inventory_item_id: matchedItem.inventoryItemId,
                unit_id: selectedUnitId,
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
                <Head title="Receiving" />
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
                <Head title="Receive item" />

                <div className="space-y-4">
                    <div>
                        <h1 className="text-lg font-semibold">
                            {matchedItem.name}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {matchedItem.sku ?? 'No SKU'}
                        </p>
                    </div>

                    {unitOptions.length > 1 ? (
                        <div
                            className="flex gap-2"
                            role="group"
                            aria-label="Receiving unit"
                        >
                            {unitOptions.map((unit) => (
                                <Button
                                    key={unit.id}
                                    type="button"
                                    variant={
                                        unit.id === selectedUnitId
                                            ? 'default'
                                            : 'outline'
                                    }
                                    onClick={() => setSelectedUnitId(unit.id)}
                                >
                                    {unit.symbol}
                                </Button>
                            ))}
                        </div>
                    ) : null}

                    <QuantityKeypad
                        value={quantity}
                        onChange={setQuantity}
                        unitLabel={
                            unitOptions.find((u) => u.id === selectedUnitId)
                                ?.symbol
                        }
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
                                quantity === '' ||
                                selectedUnitId === null
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
            <Head title="Receiving" />

            <div className="space-y-4">
                {context.lineCount > 0 ? (
                    <div className="flex items-center justify-between rounded-md border border-input px-4 py-2 text-sm">
                        <span className="flex items-center gap-1.5 font-medium">
                            <CheckCircle2
                                className="size-4 text-primary"
                                aria-hidden="true"
                            />
                            {context.lineCount}{' '}
                            {context.lineCount === 1 ? 'item' : 'items'}{' '}
                            received
                        </span>
                        {context.goodsReceiptId !== null ? (
                            <Button asChild size="sm" variant="secondary">
                                <a
                                    href={mobileReceiving.review.url(
                                        context.goodsReceiptId,
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
