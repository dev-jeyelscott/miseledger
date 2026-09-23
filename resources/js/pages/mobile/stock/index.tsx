import { Head, router, useHttp } from '@inertiajs/react';
import { Boxes, Camera, SearchX } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

import { EmptyState } from '@/components/empty-state';
import { CameraScanner } from '@/components/mobile/scanner/camera-scanner';
import type { CameraScannerHandle } from '@/components/mobile/scanner/camera-scanner';
import { SelectionList } from '@/components/mobile/scanner/selection-list';
import { SearchInput } from '@/components/ui/search-input';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import mobile from '@/routes/mobile';
import mobileScan from '@/routes/mobile/scan';
import type {
    ItemSearchResponse,
    MobileActiveLocation,
    MobileOrganizationSummary,
    ScanLookupResponse,
    ScannedItem,
} from '@/types/mobile';

type StockIndexProps = {
    activeLocation: MobileActiveLocation;
    organization: MobileOrganizationSummary | null;
};

type ScanLookupHttpErrorPayload = { notFound?: true; query?: string };

/**
 * The "Stock" tab (Spec 8): search or scan an item purely to see current
 * stock, without entering a Receive/Count/Waste/Transfer workflow.
 * Completing a scan or tapping a result here routes to the read-only item
 * detail screen, never the write-action hub.
 */
export default function StockIndex({
    activeLocation,
    organization,
}: StockIndexProps) {
    const [query, setQuery] = useState('');
    const [hasSearched, setHasSearched] = useState(false);
    const [scanning, setScanning] = useState(false);
    const [selectionItems, setSelectionItems] = useState<ScannedItem[] | null>(
        null,
    );
    const [notFoundQuery, setNotFoundQuery] = useState<string | null>(null);
    const scannerRef = useRef<CameraScannerHandle>(null);

    const search = useHttp<Record<string, never>, ItemSearchResponse>({});
    const lookup = useHttp<
        { value: string; location_id: number | null },
        ScanLookupResponse
    >({ value: '', location_id: activeLocation?.id ?? null });

    useEffect(() => {
        const term = query.trim();

        if (term.length < 2) {
            return;
        }

        const timeout = window.setTimeout(() => {
            setHasSearched(true);
            void search.get(mobileScan.search.url({ query: { q: term } }));
        }, 300);

        return () => window.clearTimeout(timeout);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [query]);

    function goToItem(item: ScannedItem) {
        router.visit(
            mobile.stock.items.show.url({
                inventoryItem: item.inventoryItemId,
            }),
        );
    }

    function handleDecode(value: string) {
        setNotFoundQuery(null);
        lookup.setData({ value, location_id: activeLocation?.id ?? null });
        lookup.post(mobile.scan.lookup.url(), {
            onSuccess: (response) => {
                if ('match' in response) {
                    goToItem(response.match);
                } else if ('matches' in response) {
                    setSelectionItems(response.matches);
                }
            },
            onHttpException: (response) => {
                if (response.status === 404) {
                    const data = response.data as ScanLookupHttpErrorPayload;

                    if (data.notFound) {
                        setNotFoundQuery(data.query ?? value);

                        return true;
                    }
                }

                return false;
            },
        });
    }

    const results = search.response?.matches ?? [];
    const showResults = hasSearched && !search.processing && !scanning;

    if (organization === null) {
        return (
            <>
                <Head title="Stock" />
                <EmptyState
                    icon={Boxes}
                    title="No organization yet"
                    description="Set up your organization on desktop to start using MiseLedger."
                />
            </>
        );
    }

    if (activeLocation === null) {
        return (
            <>
                <Head title="Stock" />
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
            <Head title="Stock" />

            <div className="space-y-4">
                <div>
                    <h1 className="text-lg font-semibold">Stock</h1>
                    <p className="text-sm text-muted-foreground">
                        {activeLocation.name}
                    </p>
                </div>

                <div className="space-y-1.5">
                    <SearchInput
                        aria-label="Search by name, SKU, or barcode"
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        placeholder="Search name, SKU, or barcode…"
                    />
                </div>

                {scanning ? (
                    <CameraScanner
                        ref={scannerRef}
                        onDecode={handleDecode}
                        onPermissionDenied={() => setScanning(false)}
                        paused={selectionItems !== null}
                    />
                ) : (
                    <button
                        type="button"
                        onClick={() => setScanning(true)}
                        className="flex min-h-[44px] w-full items-center justify-center gap-2 rounded-md border border-input px-4 py-3 text-sm font-medium hover:bg-accent"
                    >
                        <Camera className="size-4" aria-hidden="true" />
                        Scan to check stock
                    </button>
                )}

                {notFoundQuery ? (
                    <EmptyState
                        icon={SearchX}
                        title="No match found"
                        description={`"${notFoundQuery}" didn't match any item. Try a different search.`}
                    />
                ) : null}

                {showResults ? (
                    results.length > 0 ? (
                        <SelectionList items={results} onSelect={goToItem} />
                    ) : (
                        <EmptyState
                            icon={SearchX}
                            title="No matches"
                            description="Try a different name, SKU, or barcode."
                        />
                    )
                ) : null}
            </div>

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
                                onSelect={goToItem}
                            />
                        ) : null}
                    </div>
                </SheetContent>
            </Sheet>
        </>
    );
}
