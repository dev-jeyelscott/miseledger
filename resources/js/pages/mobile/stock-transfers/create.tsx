import { Head, router } from '@inertiajs/react';
import { Boxes, ChevronLeft, SearchX } from 'lucide-react';
import { useMemo, useState } from 'react';

import { EmptyState } from '@/components/empty-state';
import { Label } from '@/components/ui/label';
import { SearchInput } from '@/components/ui/search-input';
import mobileTransfers from '@/routes/mobile/transfers';
import type { MobileActiveLocation } from '@/types/mobile';

type StorageLocationOption = {
    id: number;
    name: string;
};

type DestinationLocationOption = {
    id: number;
    name: string;
    storageLocations: StorageLocationOption[];
};

type StockTransferCreateProps = {
    activeLocation: MobileActiveLocation;
    sourceStorageLocations: StorageLocationOption[];
    destinationLocations: DestinationLocationOption[];
    prefillItemId: number | null;
};

type Step = 'source' | 'destination-location' | 'destination-storage';

/** Source → destination picker (Spec 6, decision Transfer A step 1). */
export default function StockTransferCreate({
    activeLocation,
    sourceStorageLocations,
    destinationLocations,
    prefillItemId,
}: StockTransferCreateProps) {
    const [step, setStep] = useState<Step>(
        sourceStorageLocations.length === 1 ? 'destination-location' : 'source',
    );
    const [query, setQuery] = useState('');
    const [fromStorageLocationId, setFromStorageLocationId] = useState<
        number | null
    >(
        sourceStorageLocations.length === 1
            ? sourceStorageLocations[0].id
            : null,
    );
    const [toLocation, setToLocation] =
        useState<DestinationLocationOption | null>(null);
    const [submitting, setSubmitting] = useState(false);

    const availableDestinationLocations = useMemo(
        () =>
            destinationLocations.filter(
                (location) =>
                    location.id !== activeLocation?.id ||
                    location.storageLocations.some(
                        (storage) => storage.id !== fromStorageLocationId,
                    ),
            ),
        [destinationLocations, activeLocation, fromStorageLocationId],
    );

    const filteredDestinationLocations = useMemo(() => {
        const term = query.trim().toLowerCase();

        if (term === '') {
            return availableDestinationLocations;
        }

        return availableDestinationLocations.filter((location) =>
            location.name.toLowerCase().includes(term),
        );
    }, [query, availableDestinationLocations]);

    const filteredDestinationStorageLocations = useMemo(() => {
        if (toLocation === null) {
            return [];
        }

        const storageLocations = toLocation.storageLocations.filter(
            (storage) => storage.id !== fromStorageLocationId,
        );

        const term = query.trim().toLowerCase();

        if (term === '') {
            return storageLocations;
        }

        return storageLocations.filter((storage) =>
            storage.name.toLowerCase().includes(term),
        );
    }, [toLocation, fromStorageLocationId, query]);

    function chooseSource(id: number) {
        setFromStorageLocationId(id);
        setQuery('');
        setStep('destination-location');
    }

    function chooseDestinationLocation(location: DestinationLocationOption) {
        setToLocation(location);
        setQuery('');
        setStep('destination-storage');
    }

    function chooseDestinationStorage(toStorageLocationId: number) {
        if (
            submitting ||
            fromStorageLocationId === null ||
            toLocation === null
        ) {
            return;
        }

        setSubmitting(true);

        router.post(
            mobileTransfers.begin.url(),
            {
                from_storage_location_id: fromStorageLocationId,
                to_location_id: toLocation.id,
                to_storage_location_id: toStorageLocationId,
                item_id: prefillItemId,
            },
            { onFinish: () => setSubmitting(false) },
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

    return (
        <>
            <Head title="Transfer stock" />

            <div className="space-y-4">
                <div>
                    {step !== 'source' ? (
                        <button
                            type="button"
                            onClick={() =>
                                setStep(
                                    step === 'destination-storage'
                                        ? 'destination-location'
                                        : 'source',
                                )
                            }
                            className="mb-2 flex items-center gap-1 text-sm font-medium text-muted-foreground hover:text-foreground"
                        >
                            <ChevronLeft
                                className="size-4"
                                aria-hidden="true"
                            />
                            Back
                        </button>
                    ) : null}

                    <h1 className="text-lg font-semibold">
                        {step === 'source'
                            ? 'Transfer from which storage location?'
                            : step === 'destination-location'
                              ? 'Transfer to which location?'
                              : 'Which storage location?'}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {activeLocation.name}
                        {toLocation !== null && step === 'destination-storage'
                            ? ` → ${toLocation.name}`
                            : ''}
                    </p>
                </div>

                {step === 'source' ? (
                    sourceStorageLocations.length === 0 ? (
                        <EmptyState
                            icon={Boxes}
                            title="No active storage locations"
                            description="Add a storage location on desktop before transferring stock here."
                        />
                    ) : (
                        <ul className="space-y-2">
                            {sourceStorageLocations.map((storageLocation) => (
                                <li key={storageLocation.id}>
                                    <button
                                        type="button"
                                        onClick={() =>
                                            chooseSource(storageLocation.id)
                                        }
                                        className="flex min-h-[44px] w-full items-center rounded-md border border-input px-4 py-3 text-left font-medium hover:bg-accent"
                                    >
                                        {storageLocation.name}
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )
                ) : null}

                {step === 'destination-location' ? (
                    <>
                        <div className="space-y-1.5">
                            <Label htmlFor="destination-location-search">
                                Destination location
                            </Label>
                            <SearchInput
                                id="destination-location-search"
                                value={query}
                                onChange={(event) =>
                                    setQuery(event.target.value)
                                }
                                placeholder="Search locations…"
                            />
                        </div>

                        {filteredDestinationLocations.length === 0 ? (
                            <EmptyState
                                icon={SearchX}
                                title="No matching locations"
                                description="Try a different search, or add a location on desktop first."
                            />
                        ) : (
                            <ul className="space-y-2">
                                {filteredDestinationLocations.map(
                                    (location) => (
                                        <li key={location.id}>
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    chooseDestinationLocation(
                                                        location,
                                                    )
                                                }
                                                className="flex min-h-[44px] w-full items-center rounded-md border border-input px-4 py-3 text-left font-medium hover:bg-accent"
                                            >
                                                {location.name}
                                            </button>
                                        </li>
                                    ),
                                )}
                            </ul>
                        )}
                    </>
                ) : null}

                {step === 'destination-storage' ? (
                    filteredDestinationStorageLocations.length === 0 ? (
                        <EmptyState
                            icon={Boxes}
                            title="No active storage locations"
                            description="Add a storage location on desktop before transferring stock here."
                        />
                    ) : (
                        <ul className="space-y-2">
                            {filteredDestinationStorageLocations.map(
                                (storageLocation) => (
                                    <li key={storageLocation.id}>
                                        <button
                                            type="button"
                                            disabled={submitting}
                                            onClick={() =>
                                                chooseDestinationStorage(
                                                    storageLocation.id,
                                                )
                                            }
                                            className="flex min-h-[44px] w-full items-center rounded-md border border-input px-4 py-3 text-left font-medium hover:bg-accent disabled:opacity-50"
                                        >
                                            {storageLocation.name}
                                        </button>
                                    </li>
                                ),
                            )}
                        </ul>
                    )
                ) : null}
            </div>
        </>
    );
}
