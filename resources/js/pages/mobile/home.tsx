import { Head, Link } from '@inertiajs/react';
import { Boxes } from 'lucide-react';

import { EmptyState } from '@/components/empty-state';
import organizations from '@/routes/organizations';
import type {
    MobileActiveLocation,
    MobileOrganizationSummary,
} from '@/types/mobile';

type HomeProps = {
    activeLocation: MobileActiveLocation;
    hasLocations: boolean;
    organization: MobileOrganizationSummary | null;
};

export default function MobileHome({
    activeLocation,
    hasLocations,
    organization,
}: HomeProps) {
    return (
        <>
            <Head title="Mobile" />

            {organization === null ? (
                <EmptyState
                    icon={Boxes}
                    title="No organization yet"
                    description="Set up your organization on desktop to start using MiseLedger."
                    action={
                        <Link
                            href={organizations.create.url()}
                            className="text-sm font-medium text-primary underline-offset-2 hover:underline"
                        >
                            Create organization
                        </Link>
                    }
                />
            ) : !hasLocations ? (
                <EmptyState
                    icon={Boxes}
                    title="No locations configured"
                    description="Add a location on desktop before you can use MiseLedger on mobile."
                />
            ) : (
                <div className="space-y-2">
                    <h1 className="text-lg font-semibold">Home</h1>
                    <p className="text-sm text-muted-foreground">
                        Working at {activeLocation?.name}.
                    </p>
                </div>
            )}
        </>
    );
}
