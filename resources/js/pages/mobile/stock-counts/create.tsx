import { Head, router } from '@inertiajs/react';
import { Boxes, MapPin } from 'lucide-react';
import { useState } from 'react';

import { EmptyState } from '@/components/empty-state';
import { Button } from '@/components/ui/button';
import mobileStockCounts from '@/routes/mobile/stock-counts';
import type { MobileActiveLocation } from '@/types/mobile';

type StorageLocationOption = {
    id: number;
    name: string;
};

type StockCountCreateProps = {
    activeLocation: MobileActiveLocation;
    storageLocations: StorageLocationOption[];
};

/** Storage-location picker: selecting one opens (or resumes) a draft count. */
export default function StockCountCreate({
    activeLocation,
    storageLocations,
}: StockCountCreateProps) {
    const [processing, setProcessing] = useState(false);

    function selectStorageLocation(storageLocation: StorageLocationOption) {
        if (processing) {
            return;
        }

        setProcessing(true);

        router.post(
            mobileStockCounts.store.url(),
            { storage_location_id: storageLocation.id },
            { onFinish: () => setProcessing(false) },
        );
    }

    if (activeLocation === null) {
        return (
            <>
                <Head title="Start a stock count" />
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
            <Head title="Start a stock count" />

            <div className="space-y-4">
                <div>
                    <h1 className="text-lg font-semibold">
                        Select a storage location
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {activeLocation.name}
                    </p>
                </div>

                {storageLocations.length === 0 ? (
                    <EmptyState
                        icon={MapPin}
                        title="No active storage locations"
                        description="Add a storage location on desktop before starting a count here."
                    />
                ) : (
                    <ul className="space-y-2">
                        {storageLocations.map((storageLocation) => (
                            <li key={storageLocation.id}>
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={processing}
                                    onClick={() =>
                                        selectStorageLocation(storageLocation)
                                    }
                                    className="flex h-auto min-h-[44px] w-full items-center justify-start px-4 py-3 text-left font-medium"
                                >
                                    {storageLocation.name}
                                </Button>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}
