import { Head, Link, router } from '@inertiajs/react';
import { Check, MapPin } from 'lucide-react';
import { useState } from 'react';

import LocationController from '@/actions/App/Http/Controllers/Mobile/LocationController';
import { EmptyState } from '@/components/empty-state';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import organizations from '@/routes/organizations';
import type {
    MobileActiveLocation,
    MobileLocationOption,
    MobileOrganizationSummary,
} from '@/types/mobile';

type LocationPickerProps = {
    activeLocation: MobileActiveLocation;
    canManageLocations: boolean;
    locationOptions: MobileLocationOption[];
    next: string | null;
    organization: MobileOrganizationSummary;
};

export default function MobileLocationPicker({
    activeLocation,
    canManageLocations,
    locationOptions,
    next,
    organization,
}: LocationPickerProps) {
    const [processing, setProcessing] = useState(false);

    function selectLocation(location: MobileLocationOption) {
        setProcessing(true);
        router.post(
            LocationController.store.url(),
            { location_id: location.id, next: next ?? '' },
            { preserveScroll: true, onFinish: () => setProcessing(false) },
        );
    }

    return (
        <>
            <Head title="Select location" />

            <h1 className="mb-4 text-lg font-semibold">Select a location</h1>

            {locationOptions.length === 0 ? (
                <EmptyState
                    icon={MapPin}
                    title="No active locations"
                    description={
                        canManageLocations
                            ? 'Add a location on desktop to start using MiseLedger on mobile.'
                            : 'Ask your manager to set up a location before you can use MiseLedger on mobile.'
                    }
                    action={
                        canManageLocations ? (
                            <Link
                                href={organizations.locations.index.url({
                                    organization: organization.id,
                                })}
                                className="text-sm font-medium text-primary underline-offset-2 hover:underline"
                            >
                                Manage locations
                            </Link>
                        ) : undefined
                    }
                />
            ) : (
                <ul className="space-y-2">
                    {locationOptions.map((location) => {
                        const isSelected = activeLocation?.id === location.id;

                        return (
                            <li key={location.id}>
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={processing}
                                    onClick={() => selectLocation(location)}
                                    className={cn(
                                        'flex h-auto min-h-[44px] w-full items-center justify-between px-4 py-3 text-left',
                                        isSelected && 'border-primary',
                                    )}
                                >
                                    <span>
                                        <span className="block font-medium">
                                            {location.name}
                                        </span>
                                        <span className="block text-xs text-muted-foreground">
                                            {location.code}
                                        </span>
                                    </span>
                                    {isSelected ? (
                                        <Check
                                            className="size-4 text-primary"
                                            aria-hidden="true"
                                        />
                                    ) : null}
                                </Button>
                            </li>
                        );
                    })}
                </ul>
            )}
        </>
    );
}
