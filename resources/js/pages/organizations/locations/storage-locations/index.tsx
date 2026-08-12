import { Form, Head, Link } from '@inertiajs/react';
import OrganizationLocationController from '@/actions/App/Http/Controllers/OrganizationLocationController';
import OrganizationStorageLocationController from '@/actions/App/Http/Controllers/OrganizationStorageLocationController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import type {
    LocationSummary,
    OrganizationSummary,
    StorageLocationSummary,
} from '@/types';

type Props = {
    organization: OrganizationSummary;
    location: LocationSummary;
    storageLocations: StorageLocationSummary[];
};

export default function StorageLocationsIndex({
    organization,
    location,
    storageLocations,
}: Props) {
    return (
        <>
            <Head title={`${location.name} storage locations`} />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div>
                    <h1 className="text-2xl font-semibold">
                        Storage locations
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {organization.name} · {location.name}
                    </p>
                </div>

                <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
                    <div className="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                        <div className="border-b border-sidebar-border/70 px-5 py-4 dark:border-sidebar-border">
                            <h2 className="font-medium">
                                Current storage locations
                            </h2>
                        </div>

                        {storageLocations.length === 0 ? (
                            <div className="px-5 py-8 text-sm text-muted-foreground">
                                No storage locations are configured.
                            </div>
                        ) : (
                            <div className="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                                {storageLocations.map((storageLocation) => (
                                    <div
                                        key={storageLocation.id}
                                        className="flex items-center justify-between gap-4 px-5 py-4"
                                    >
                                        <div className="min-w-0">
                                            <p className="truncate font-medium">
                                                {storageLocation.name}
                                            </p>
                                            <p className="mt-1 text-sm text-muted-foreground">
                                                {storageLocation.code} ·{' '}
                                                {storageLocation.active
                                                    ? 'Active'
                                                    : 'Inactive'}
                                            </p>
                                        </div>

                                        <Button
                                            variant="outline"
                                            size="sm"
                                            asChild
                                        >
                                            <Link
                                                href={OrganizationStorageLocationController.edit(
                                                    [
                                                        organization.id,
                                                        location.id,
                                                        storageLocation.id,
                                                    ],
                                                )}
                                            >
                                                Edit
                                            </Link>
                                        </Button>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>

                    <div className="h-fit rounded-xl border border-sidebar-border/70 p-5 dark:border-sidebar-border">
                        <div className="mb-5">
                            <h2 className="font-medium">
                                Add storage location
                            </h2>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Codes must be unique inside this restaurant
                                location.
                            </p>
                        </div>

                        <Form
                            {...OrganizationStorageLocationController.store.form(
                                [organization.id, location.id],
                            )}
                            className="space-y-5"
                            resetOnSuccess
                        >
                            {({ processing, errors }) => (
                                <>
                                    <input
                                        type="hidden"
                                        name="active"
                                        value="1"
                                    />

                                    <div className="grid gap-2">
                                        <Label htmlFor="name">Name</Label>
                                        <Input
                                            id="name"
                                            name="name"
                                            required
                                            placeholder="Walk-in Chiller"
                                        />
                                        <InputError message={errors.name} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="code">Code</Label>
                                        <Input
                                            id="code"
                                            name="code"
                                            required
                                            placeholder="CHILLER"
                                        />
                                        <InputError message={errors.code} />
                                    </div>

                                    <Button type="submit" disabled={processing}>
                                        Add storage location
                                    </Button>
                                </>
                            )}
                        </Form>
                    </div>
                </div>

                <div>
                    <Button variant="outline" asChild>
                        <Link
                            href={OrganizationLocationController.index(
                                organization.id,
                            )}
                        >
                            Back to locations
                        </Link>
                    </Button>
                </div>
            </div>
        </>
    );
}

StorageLocationsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
