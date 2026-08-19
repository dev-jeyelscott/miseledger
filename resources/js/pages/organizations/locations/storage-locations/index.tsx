import { Form, Head } from '@inertiajs/react';
import OrganizationLocationController from '@/actions/App/Http/Controllers/OrganizationLocationController';
import OrganizationStorageLocationController from '@/actions/App/Http/Controllers/OrganizationStorageLocationController';
import InputError from '@/components/input-error';
import { PreviousPageButton } from '@/components/navigation/previous-page-button';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useGuardedDialog } from '@/hooks/use-guarded-dialog';
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

type EditStorageLocationDialogProps = {
    organization: OrganizationSummary;
    location: LocationSummary;
    storageLocation: StorageLocationSummary;
};

/** Edit one storage location without leaving its parent management workspace. */
function EditStorageLocationDialog({
    organization,
    location,
    storageLocation,
}: EditStorageLocationDialogProps) {
    const dialog = useGuardedDialog(
        'Discard the storage-location changes you entered?',
    );
    const fieldPrefix = `edit-storage-location-${storageLocation.id}`;

    return (
        <Dialog open={dialog.open} onOpenChange={dialog.onOpenChange}>
            <DialogTrigger asChild>
                <Button variant="outline" size="sm">
                    Edit
                </Button>
            </DialogTrigger>

            <DialogContent className="max-h-[calc(100vh-2rem)] overflow-y-auto sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>Edit storage location</DialogTitle>
                    <DialogDescription>
                        {organization.name} · {location.name}
                    </DialogDescription>
                </DialogHeader>

                <div onChange={dialog.markDirty}>
                    <Form
                        {...OrganizationStorageLocationController.update.form([
                            organization.id,
                            location.id,
                            storageLocation.id,
                        ])}
                        errorBag={`editStorageLocation${storageLocation.id}`}
                        className="space-y-5"
                        onSuccess={dialog.closeAfterSuccess}
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor={`${fieldPrefix}-name`}>
                                        Name
                                    </Label>
                                    <Input
                                        id={`${fieldPrefix}-name`}
                                        name="name"
                                        required
                                        defaultValue={storageLocation.name}
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor={`${fieldPrefix}-code`}>
                                        Code
                                    </Label>
                                    <Input
                                        id={`${fieldPrefix}-code`}
                                        name="code"
                                        required
                                        defaultValue={storageLocation.code}
                                    />
                                    <InputError message={errors.code} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor={`${fieldPrefix}-active`}>
                                        Status
                                    </Label>
                                    <select
                                        id={`${fieldPrefix}-active`}
                                        name="active"
                                        defaultValue={
                                            storageLocation.active ? '1' : '0'
                                        }
                                        className="h-9 rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                    >
                                        <option value="1">Active</option>
                                        <option value="0">Inactive</option>
                                    </select>
                                    <InputError message={errors.active} />
                                </div>

                                <div className="flex flex-wrap gap-2">
                                    <Button type="submit" disabled={processing}>
                                        Save storage location
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={processing}
                                        onClick={() =>
                                            dialog.onOpenChange(false)
                                        }
                                    >
                                        Cancel
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                </div>
            </DialogContent>
        </Dialog>
    );
}

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

                                        <EditStorageLocationDialog
                                            organization={organization}
                                            location={location}
                                            storageLocation={storageLocation}
                                        />
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
                            errorBag="createStorageLocation"
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
                    <PreviousPageButton
                        fallback={OrganizationLocationController.index.url(
                            organization.id,
                        )}
                        variant="outline"
                    >
                        Back to locations
                    </PreviousPageButton>
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
