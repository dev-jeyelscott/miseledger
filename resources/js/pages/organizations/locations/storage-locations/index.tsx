import { Form, Head } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import type { ReactNode } from 'react';
import OrganizationLocationController from '@/actions/App/Http/Controllers/OrganizationLocationController';
import OrganizationStorageLocationController from '@/actions/App/Http/Controllers/OrganizationStorageLocationController';
import InputError from '@/components/input-error';
import { PreviousPageButton } from '@/components/navigation/previous-page-button';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
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
import { NativeSelect } from '@/components/ui/native-select';
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

type StorageLocationStatusFilter = 'all' | 'active' | 'inactive';

type CreateStorageLocationDialogProps = {
    organization: OrganizationSummary;
    location: LocationSummary;
    trigger: ReactNode;
};

type EditStorageLocationDialogProps = {
    organization: OrganizationSummary;
    location: LocationSummary;
    storageLocation: StorageLocationSummary;
    trigger: ReactNode;
};

/** Format a storage-location count with the correct singular or plural label. */
function formatStorageLocationCount(count: number): string {
    return `${count.toLocaleString()} ${
        count === 1 ? 'storage location' : 'storage locations'
    }`;
}

/** Create a storage location without leaving the parent location workspace. */
function CreateStorageLocationDialog({
    organization,
    location,
    trigger,
}: CreateStorageLocationDialogProps) {
    const dialog = useGuardedDialog(
        'Discard the new storage-location details you entered?',
    );

    return (
        <Dialog open={dialog.open} onOpenChange={dialog.onOpenChange}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>

            <DialogContent className="max-h-[calc(100vh-2rem)] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Add storage location</DialogTitle>
                    <DialogDescription>
                        Create a storage area inside {location.name}. Codes must
                        be unique within this restaurant location.
                    </DialogDescription>
                </DialogHeader>

                <div onChange={dialog.markDirty}>
                    <Form
                        {...OrganizationStorageLocationController.store.form([
                            organization.id,
                            location.id,
                        ])}
                        errorBag="createStorageLocation"
                        className="space-y-5"
                        resetOnSuccess
                        onSuccess={dialog.closeAfterSuccess}
                    >
                        {({ processing, errors }) => (
                            <>
                                <input type="hidden" name="active" value="1" />

                                <div className="grid gap-2">
                                    <Label htmlFor="create-storage-location-name">
                                        Storage location name
                                    </Label>

                                    <Input
                                        id="create-storage-location-name"
                                        name="name"
                                        required
                                        autoFocus
                                        autoComplete="off"
                                        placeholder="e.g., Walk-in Chiller"
                                    />

                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="create-storage-location-code">
                                        Storage location code
                                    </Label>

                                    <Input
                                        id="create-storage-location-code"
                                        name="code"
                                        required
                                        autoComplete="off"
                                        placeholder="e.g., CHILLER"
                                        aria-describedby="create-storage-location-code-help"
                                    />

                                    <p
                                        id="create-storage-location-code-help"
                                        className="text-xs text-muted-foreground"
                                    >
                                        Letters, numbers, hyphens, and
                                        underscores only. Codes must be unique
                                        within this restaurant location.
                                    </p>

                                    <InputError message={errors.code} />
                                </div>

                                <p className="text-xs text-muted-foreground">
                                    New storage locations are active by default.
                                </p>

                                <div className="flex flex-wrap justify-end gap-2">
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

                                    <Button type="submit" disabled={processing}>
                                        <Plus
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        {processing
                                            ? 'Adding...'
                                            : 'Add storage location'}
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

/** Edit one storage location without leaving its parent management workspace. */
function EditStorageLocationDialog({
    organization,
    location,
    storageLocation,
    trigger,
}: EditStorageLocationDialogProps) {
    const dialog = useGuardedDialog(
        'Discard the storage-location changes you entered?',
    );
    const fieldPrefix = `edit-storage-location-${storageLocation.id}`;
    const statusHelpId = `${fieldPrefix}-status-help`;

    return (
        <Dialog open={dialog.open} onOpenChange={dialog.onOpenChange}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>

            <DialogContent className="max-h-[calc(100vh-2rem)] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Edit storage location</DialogTitle>
                    <DialogDescription>
                        Update {storageLocation.name} inside {location.name}.
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
                                        Storage location name
                                    </Label>

                                    <Input
                                        id={`${fieldPrefix}-name`}
                                        name="name"
                                        required
                                        autoFocus
                                        autoComplete="off"
                                        defaultValue={storageLocation.name}
                                    />

                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor={`${fieldPrefix}-code`}>
                                        Storage location code
                                    </Label>

                                    <Input
                                        id={`${fieldPrefix}-code`}
                                        name="code"
                                        required
                                        autoComplete="off"
                                        defaultValue={storageLocation.code}
                                    />

                                    <p className="text-xs text-muted-foreground">
                                        Letters, numbers, hyphens, and
                                        underscores only. Codes must remain
                                        unique within this restaurant location.
                                    </p>

                                    <InputError message={errors.code} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor={`${fieldPrefix}-active`}>
                                        Status
                                    </Label>

                                    <NativeSelect
                                        id={`${fieldPrefix}-active`}
                                        name="active"
                                        defaultValue={
                                            storageLocation.active ? '1' : '0'
                                        }
                                        aria-describedby={statusHelpId}
                                    >
                                        <option value="1">Active</option>
                                        <option value="0">Inactive</option>
                                    </NativeSelect>

                                    <p
                                        id={statusHelpId}
                                        className="text-xs text-muted-foreground"
                                    >
                                        Deactivation may be blocked while a
                                        shipped stock transfer is awaiting
                                        receipt here.
                                    </p>

                                    <InputError message={errors.active} />
                                </div>

                                <div className="flex flex-wrap justify-end gap-2">
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

                                    <Button type="submit" disabled={processing}>
                                        {processing
                                            ? 'Saving...'
                                            : 'Save storage location'}
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

/** Render storage locations as a compact operational management workspace. */
export default function StorageLocationsIndex({
    organization,
    location,
    storageLocations,
}: Props) {
    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] =
        useState<StorageLocationStatusFilter>('all');

    const filteredStorageLocations = useMemo(() => {
        const normalizedSearch = search.trim().toLowerCase();

        return storageLocations.filter((storageLocation) => {
            const matchesSearch =
                normalizedSearch === '' ||
                storageLocation.name.toLowerCase().includes(normalizedSearch) ||
                storageLocation.code.toLowerCase().includes(normalizedSearch);

            const matchesStatus =
                statusFilter === 'all' ||
                (statusFilter === 'active' && storageLocation.active) ||
                (statusFilter === 'inactive' && !storageLocation.active);

            return matchesSearch && matchesStatus;
        });
    }, [search, statusFilter, storageLocations]);

    const activeStorageLocationCount = storageLocations.filter(
        (storageLocation) => storageLocation.active,
    ).length;

    const hasFilters = search.trim() !== '' || statusFilter !== 'all';

    const storageLocationCount =
        filteredStorageLocations.length === storageLocations.length
            ? formatStorageLocationCount(storageLocations.length)
            : `${filteredStorageLocations.length.toLocaleString()} of ${formatStorageLocationCount(
                  storageLocations.length,
              )}`;

    return (
        <>
            <Head title={`${location.name} storage locations`} />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Storage locations"
                    description={
                        <>
                            Manage storage areas for {organization.name} ·{' '}
                            {location.name}.
                        </>
                    }
                    actions={
                        <CreateStorageLocationDialog
                            organization={organization}
                            location={location}
                            trigger={
                                <Button>
                                    <Plus
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Add storage location
                                </Button>
                            }
                        />
                    }
                />

                <section
                    aria-label="Storage location summary"
                    className="grid max-w-xl gap-3 sm:grid-cols-2"
                >
                    <div className="rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                        <p className="text-sm text-muted-foreground">
                            Total storage locations
                        </p>

                        <p className="mt-1 text-2xl font-semibold tabular-nums">
                            {storageLocations.length.toLocaleString()}
                        </p>
                    </div>

                    <div className="rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                        <p className="text-sm text-muted-foreground">
                            Active locations
                        </p>

                        <p className="mt-1 text-2xl font-semibold tabular-nums">
                            {activeStorageLocationCount.toLocaleString()}
                        </p>
                    </div>
                </section>

                <section
                    aria-label="Storage locations"
                    className="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border"
                >
                    <div className="grid gap-3 border-b border-sidebar-border/70 p-4 md:grid-cols-[minmax(0,1fr)_12rem_auto] md:items-center dark:border-sidebar-border">
                        <div className="relative">
                            <label
                                htmlFor="storage-location-search"
                                className="sr-only"
                            >
                                Search storage locations
                            </label>

                            <Search
                                className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                aria-hidden="true"
                            />

                            <Input
                                id="storage-location-search"
                                type="search"
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder="Search storage locations by name or code..."
                                className="pl-9"
                            />
                        </div>

                        <div>
                            <label
                                htmlFor="storage-location-status-filter"
                                className="sr-only"
                            >
                                Filter storage locations by status
                            </label>

                            <NativeSelect
                                id="storage-location-status-filter"
                                value={statusFilter}
                                onChange={(event) =>
                                    setStatusFilter(
                                        event.target
                                            .value as StorageLocationStatusFilter,
                                    )
                                }
                            >
                                <option value="all">All statuses</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </NativeSelect>
                        </div>

                        <div className="flex items-center gap-2 md:justify-end">
                            <p
                                aria-live="polite"
                                className="text-sm whitespace-nowrap text-muted-foreground"
                            >
                                {storageLocationCount}
                            </p>

                            {hasFilters && (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => {
                                        setSearch('');
                                        setStatusFilter('all');
                                    }}
                                >
                                    Reset
                                </Button>
                            )}
                        </div>
                    </div>

                    {filteredStorageLocations.length === 0 ? (
                        <div className="px-4 py-12 text-center">
                            <div className="mx-auto max-w-sm">
                                <p className="font-medium">
                                    {hasFilters
                                        ? 'No storage locations match these filters.'
                                        : 'No storage locations have been configured yet.'}
                                </p>

                                <p className="mt-1 text-sm text-muted-foreground">
                                    {hasFilters
                                        ? 'Adjust or reset the filters to see more storage locations.'
                                        : 'Add your first storage location to organize inventory inside this restaurant location.'}
                                </p>
                            </div>
                        </div>
                    ) : (
                        <div
                            className="divide-y divide-sidebar-border/70 md:hidden dark:divide-sidebar-border"
                            data-testid="mobile-storage-locations"
                        >
                            {filteredStorageLocations.map((storageLocation) => (
                                <article
                                    key={storageLocation.id}
                                    className="space-y-3 p-4"
                                >
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="min-w-0">
                                            <p className="truncate font-medium">
                                                {storageLocation.name}
                                            </p>
                                            <span className="mt-1 inline-block rounded-md bg-muted px-2 py-1 font-mono text-xs">
                                                {storageLocation.code}
                                            </span>
                                        </div>
                                        <Badge
                                            variant={
                                                storageLocation.active
                                                    ? 'secondary'
                                                    : 'outline'
                                            }
                                            className={
                                                storageLocation.active
                                                    ? 'border border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/50 dark:text-emerald-300'
                                                    : 'text-muted-foreground'
                                            }
                                        >
                                            {storageLocation.active
                                                ? 'Active'
                                                : 'Inactive'}
                                        </Badge>
                                    </div>

                                    <EditStorageLocationDialog
                                        organization={organization}
                                        location={location}
                                        storageLocation={storageLocation}
                                        trigger={
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                className="w-full"
                                                aria-label={`Edit ${storageLocation.name}`}
                                            >
                                                Edit
                                            </Button>
                                        }
                                    />
                                </article>
                            ))}
                        </div>
                    )}

                    <div className="hidden overflow-x-auto md:block">
                        <table className="w-full min-w-[680px] text-sm">
                            <caption className="sr-only">
                                Storage locations configured for {location.name}
                            </caption>

                            <thead className="bg-muted/40 text-left text-xs text-muted-foreground">
                                <tr className="border-b border-sidebar-border/70 dark:border-sidebar-border">
                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-medium"
                                    >
                                        Name
                                    </th>

                                    <th
                                        scope="col"
                                        className="w-44 px-4 py-3 font-medium"
                                    >
                                        Code
                                    </th>

                                    <th
                                        scope="col"
                                        className="w-40 px-4 py-3 font-medium"
                                    >
                                        Status
                                    </th>

                                    <th
                                        scope="col"
                                        className="w-32 px-4 py-3 text-right font-medium"
                                    >
                                        Actions
                                    </th>
                                </tr>
                            </thead>

                            <tbody>
                                {filteredStorageLocations.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={4}
                                            className="px-4 py-12 text-center"
                                        >
                                            <div className="mx-auto max-w-sm">
                                                <p className="font-medium">
                                                    {hasFilters
                                                        ? 'No storage locations match these filters.'
                                                        : 'No storage locations have been configured yet.'}
                                                </p>

                                                <p className="mt-1 text-sm text-muted-foreground">
                                                    {hasFilters
                                                        ? 'Adjust or reset the filters to see more storage locations.'
                                                        : 'Add your first storage location to organize inventory inside this restaurant location.'}
                                                </p>
                                            </div>
                                        </td>
                                    </tr>
                                ) : (
                                    filteredStorageLocations.map(
                                        (storageLocation) => (
                                            <tr
                                                key={storageLocation.id}
                                                className="border-b border-sidebar-border/70 transition-colors last:border-b-0 hover:bg-muted/30 dark:border-sidebar-border"
                                            >
                                                <td className="px-4 py-3">
                                                    <span className="font-medium">
                                                        {storageLocation.name}
                                                    </span>
                                                </td>

                                                <td className="px-4 py-3">
                                                    <span className="rounded-md bg-muted px-2 py-1 font-mono text-xs">
                                                        {storageLocation.code}
                                                    </span>
                                                </td>

                                                <td className="px-4 py-3">
                                                    <Badge
                                                        variant={
                                                            storageLocation.active
                                                                ? 'secondary'
                                                                : 'outline'
                                                        }
                                                        className={
                                                            storageLocation.active
                                                                ? 'border border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/50 dark:text-emerald-300'
                                                                : 'text-muted-foreground'
                                                        }
                                                    >
                                                        <span
                                                            className={
                                                                storageLocation.active
                                                                    ? 'size-1.5 rounded-full bg-emerald-600 dark:bg-emerald-400'
                                                                    : 'size-1.5 rounded-full bg-muted-foreground'
                                                            }
                                                            aria-hidden="true"
                                                        />
                                                        {storageLocation.active
                                                            ? 'Active'
                                                            : 'Inactive'}
                                                    </Badge>
                                                </td>

                                                <td className="px-4 py-2">
                                                    <div
                                                        className="flex justify-end"
                                                        aria-label={`Actions for ${storageLocation.name}`}
                                                    >
                                                        <EditStorageLocationDialog
                                                            organization={
                                                                organization
                                                            }
                                                            location={location}
                                                            storageLocation={
                                                                storageLocation
                                                            }
                                                            trigger={
                                                                <Button
                                                                    variant="outline"
                                                                    size="sm"
                                                                    aria-label={`Edit ${storageLocation.name}`}
                                                                >
                                                                    Edit
                                                                </Button>
                                                            }
                                                        />
                                                    </div>
                                                </td>
                                            </tr>
                                        ),
                                    )
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>

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
