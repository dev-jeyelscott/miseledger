import { Head, useHttp } from '@inertiajs/react';
import { Boxes, SearchX } from 'lucide-react';
import { useRef, useState } from 'react';

import { EmptyState } from '@/components/empty-state';
import { CameraScanner } from '@/components/mobile/scanner/camera-scanner';
import type { CameraScannerHandle } from '@/components/mobile/scanner/camera-scanner';
import { ManualEntrySheet } from '@/components/mobile/scanner/manual-entry-sheet';
import { SelectionList } from '@/components/mobile/scanner/selection-list';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import mobile from '@/routes/mobile';
import type {
    MobileActiveLocation,
    MobileOrganizationSummary,
    ScanLookupResponse,
    ScannedItem,
} from '@/types/mobile';

import { ItemActionHub } from './item-action-hub';

type ScanIndexProps = {
    activeLocation: MobileActiveLocation;
    organization: MobileOrganizationSummary | null;
    initialItem?: ScannedItem | null;
};

type ScanLookupHttpErrorPayload = { notFound?: true; query?: string };

/** The persistent scanner screen: camera viewfinder plus manual-entry fallback (Spec 2). */
export default function ScanIndex({
    activeLocation,
    organization,
    initialItem = null,
}: ScanIndexProps) {
    const [cameraDenied, setCameraDenied] = useState(false);
    const [manualSheetOpen, setManualSheetOpen] = useState(false);
    const [hubItem, setHubItem] = useState<ScannedItem | null>(initialItem);
    const [selectionItems, setSelectionItems] = useState<ScannedItem[] | null>(
        null,
    );
    const [notFoundQuery, setNotFoundQuery] = useState<string | null>(null);
    const scannerRef = useRef<CameraScannerHandle>(null);

    const lookup = useHttp<
        { value: string; location_id: number | null },
        ScanLookupResponse
    >({ value: '', location_id: activeLocation?.id ?? null });

    function handleDecode(value: string) {
        setNotFoundQuery(null);
        lookup.setData({ value, location_id: activeLocation?.id ?? null });
        lookup.post(mobile.scan.lookup.url(), {
            onSuccess: (response) => {
                if ('match' in response) {
                    setHubItem(response.match);
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

    function closeHub() {
        setHubItem(null);
        scannerRef.current?.resetScan();
    }

    function selectFromList(item: ScannedItem) {
        setSelectionItems(null);
        setManualSheetOpen(false);
        setHubItem(item);
    }

    if (organization === null) {
        return (
            <>
                <Head title="Scan" />
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
                <Head title="Scan" />
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
            <Head title="Scan" />

            <div className="space-y-4">
                {!cameraDenied ? (
                    <CameraScanner
                        ref={scannerRef}
                        onDecode={handleDecode}
                        onPermissionDenied={() => setCameraDenied(true)}
                        paused={hubItem !== null || selectionItems !== null}
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

                {notFoundQuery ? (
                    <EmptyState
                        icon={SearchX}
                        title="No match found"
                        description={`"${notFoundQuery}" didn't match any item. Try manual search.`}
                    />
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

            <ItemActionHub
                item={hubItem}
                onOpenChange={(open) => {
                    if (!open) {
                        closeHub();
                    }
                }}
            />
        </>
    );
}
