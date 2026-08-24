import { Form, Head, Link } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import type { ReactNode } from 'react';
import OrganizationLocationController from '@/actions/App/Http/Controllers/OrganizationLocationController';
import OrganizationStorageLocationController from '@/actions/App/Http/Controllers/OrganizationStorageLocationController';
import InputError from '@/components/input-error';
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
import { UsageLimitNotice } from '@/components/usage-limit-notice';
import { useGuardedDialog } from '@/hooks/use-guarded-dialog';
import { dashboard } from '@/routes';
import type { LocationSummary, OrganizationSummary } from '@/types';

type Props = {
    organization: OrganizationSummary;
    locations: LocationSummary[];
};

type LocationStatusFilter = 'all' | 'active' | 'inactive';

type CreateLocationDialogProps = {
    organization: OrganizationSummary;
    trigger: ReactNode;
};

type EditLocationDialogProps = {
    organization: OrganizationSummary;
    location: LocationSummary;
    trigger: ReactNode;
};

/** Format a location count with the correct singular or plural label. */
function formatLocationCount(count: number): string {
    return `${count.toLocaleString()} ${count === 1 ? 'location' : 'locations'}`;
}

/** Create a location without leaving the organization locations workspace. */
function CreateLocationDialog({
    organization,
    trigger,
}: CreateLocationDialogProps) {
    const dialog = useGuardedDialog(
        'Discard the new location details you entered?',
    );

    return (
        <Dialog open={dialog.open} onOpenChange={dialog.onOpenChange}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>

            <DialogContent className="max-h-[calc(100vh-2rem)] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Add location</DialogTitle>
                    <DialogDescription>
                        Create a location for {organization.name}. A default
                        storage area will be created automatically.
                    </DialogDescription>
                </DialogHeader>

                <div onChange={dialog.markDirty}>
                    <Form
                        {...OrganizationLocationController.store.form(
                            organization.id,
                        )}
                        errorBag="createLocation"
                        className="space-y-5"
                        resetOnSuccess
                        onSuccess={dialog.closeAfterSuccess}
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="create-location-name">
                                        Location name
                                    </Label>

                                    <Input
                                        id="create-location-name"
                                        name="name"
                                        required
                                        autoFocus
                                        autoComplete="off"
                                        placeholder="e.g., BGC High Street"
                                    />

                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="create-location-code">
                                        Location code
                                    </Label>

                                    <Input
                                        id="create-location-code"
                                        name="code"
                                        required
                                        autoComplete="off"
                                        placeholder="e.g., BGC"
                                        aria-describedby="create-location-code-help"
                                    />

                                    <p
                                        id="create-location-code-help"
                                        className="text-xs text-muted-foreground"
                                    >
                                        Letters, numbers, hyphens, and
                                        underscores only. Codes must be unique
                                        within this organization.
                                    </p>

                                    <InputError message={errors.code} />
                                </div>

                                <p className="text-xs text-muted-foreground">
                                    New locations are active by default.
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
                                            : 'Add location'}
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

/** Edit one location in-place while retaining the management workspace context. */
function EditLocationDialog({
    organization,
    location,
    trigger,
}: EditLocationDialogProps) {
    const dialog = useGuardedDialog(
        'Discard the location changes you entered?',
    );
    const fieldPrefix = `edit-location-${location.id}`;
    const statusHelpId = `${fieldPrefix}-status-help`;

    return (
        <Dialog open={dialog.open} onOpenChange={dialog.onOpenChange}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>

            <DialogContent className="max-h-[calc(100vh-2rem)] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Edit location</DialogTitle>
                    <DialogDescription>
                        Update {location.name} for {organization.name}.
                    </DialogDescription>
                </DialogHeader>

                <div onChange={dialog.markDirty}>
                    <Form
                        {...OrganizationLocationController.update.form([
                            organization.id,
                            location.id,
                        ])}
                        errorBag={`editLocation${location.id}`}
                        className="space-y-5"
                        onSuccess={dialog.closeAfterSuccess}
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor={`${fieldPrefix}-name`}>
                                        Location name
                                    </Label>

                                    <Input
                                        id={`${fieldPrefix}-name`}
                                        name="name"
                                        required
                                        autoFocus
                                        defaultValue={location.name}
                                        autoComplete="off"
                                    />

                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor={`${fieldPrefix}-code`}>
                                        Location code
                                    </Label>

                                    <Input
                                        id={`${fieldPrefix}-code`}
                                        name="code"
                                        required
                                        defaultValue={location.code}
                                        autoComplete="off"
                                    />

                                    <p className="text-xs text-muted-foreground">
                                        Letters, numbers, hyphens, and
                                        underscores only.
                                    </p>

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
                                            location.active ? '1' : '0'
                                        }
                                        aria-describedby={statusHelpId}
                                        className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                    >
                                        <option value="1">Active</option>
                                        <option value="0">Inactive</option>
                                    </select>

                                    <p
                                        id={statusHelpId}
                                        className="text-xs text-muted-foreground"
                                    >
                                        Deactivation may be blocked when this
                                        location is still required by an active
                                        inventory workflow.
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
                                            : 'Save location'}
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

/** Render organization locations as a compact operational management workspace. */
export default function OrganizationLocations({
    organization,
    locations,
}: Props) {
    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] =
        useState<LocationStatusFilter>('all');

    const filteredLocations = useMemo(() => {
        const normalizedSearch = search.trim().toLowerCase();

        return locations.filter((location) => {
            const matchesSearch =
                normalizedSearch === '' ||
                location.name.toLowerCase().includes(normalizedSearch) ||
                location.code.toLowerCase().includes(normalizedSearch);

            const matchesStatus =
                statusFilter === 'all' ||
                (statusFilter === 'active' && location.active) ||
                (statusFilter === 'inactive' && !location.active);

            return matchesSearch && matchesStatus;
        });
    }, [locations, search, statusFilter]);

    const hasFilters = search.trim() !== '' || statusFilter !== 'all';

    const locationCount =
        filteredLocations.length === locations.length
            ? formatLocationCount(locations.length)
            : `${formatLocationCount(filteredLocations.length)} of ${formatLocationCount(
                  locations.length,
              )}`;

    return (
        <>
            <Head title={`${organization.name} locations`} />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Organization locations
                        </h1>

                        <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
                            Manage inventory locations and their storage areas
                            for {organization.name}.
                        </p>
                    </div>

                    <div className="flex flex-col items-end gap-2">
                        <CreateLocationDialog
                            organization={organization}
                            trigger={
                                <Button>
                                    <Plus
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Add location
                                </Button>
                            }
                        />

                        <UsageLimitNotice
                            limitKey="locations"
                            resourceLabel="locations"
                        />
                    </div>
                </div>

                <section
                    aria-label="Organization locations"
                    className="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border"
                >
                    <div className="grid gap-3 border-b border-sidebar-border/70 p-4 md:grid-cols-[minmax(0,1fr)_12rem_auto] md:items-center dark:border-sidebar-border">
                        <div className="relative">
                            <label
                                htmlFor="location-search"
                                className="sr-only"
                            >
                                Search locations
                            </label>

                            <Search
                                className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                aria-hidden="true"
                            />

                            <Input
                                id="location-search"
                                type="search"
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder="Search locations by name or code..."
                                className="pl-9"
                            />
                        </div>

                        <div>
                            <label
                                htmlFor="location-status-filter"
                                className="sr-only"
                            >
                                Filter locations by status
                            </label>

                            <select
                                id="location-status-filter"
                                value={statusFilter}
                                onChange={(event) =>
                                    setStatusFilter(
                                        event.target
                                            .value as LocationStatusFilter,
                                    )
                                }
                                className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            >
                                <option value="all">All statuses</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>

                        <div className="flex items-center gap-2 md:justify-end">
                            <p
                                aria-live="polite"
                                className="text-sm whitespace-nowrap text-muted-foreground"
                            >
                                {locationCount}
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

                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[680px] text-sm">
                            <caption className="sr-only">
                                Locations configured for {organization.name}
                            </caption>

                            <thead className="bg-muted/40 text-left text-xs text-muted-foreground">
                                <tr className="border-b border-sidebar-border/70 dark:border-sidebar-border">
                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-medium"
                                    >
                                        Location
                                    </th>

                                    <th
                                        scope="col"
                                        className="w-36 px-4 py-3 font-medium"
                                    >
                                        Code
                                    </th>

                                    <th
                                        scope="col"
                                        className="w-36 px-4 py-3 font-medium"
                                    >
                                        Status
                                    </th>

                                    <th
                                        scope="col"
                                        className="w-56 px-4 py-3 text-right font-medium"
                                    >
                                        Actions
                                    </th>
                                </tr>
                            </thead>

                            <tbody>
                                {filteredLocations.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={4}
                                            className="px-4 py-12 text-center"
                                        >
                                            <div className="mx-auto max-w-sm">
                                                <p className="font-medium">
                                                    {hasFilters
                                                        ? 'No locations match these filters.'
                                                        : 'No locations have been configured yet.'}
                                                </p>

                                                <p className="mt-1 text-sm text-muted-foreground">
                                                    {hasFilters
                                                        ? 'Adjust or reset the filters to see more locations.'
                                                        : 'Add your first location to start organizing inventory storage.'}
                                                </p>
                                            </div>
                                        </td>
                                    </tr>
                                ) : (
                                    filteredLocations.map((location) => (
                                        <tr
                                            key={location.id}
                                            className="border-b border-sidebar-border/70 transition-colors last:border-b-0 hover:bg-muted/30 dark:border-sidebar-border"
                                        >
                                            <td className="px-4 py-3">
                                                <span className="font-medium">
                                                    {location.name}
                                                </span>
                                            </td>

                                            <td className="px-4 py-3">
                                                <span className="rounded-md bg-muted px-2 py-1 font-mono text-xs">
                                                    {location.code}
                                                </span>
                                            </td>

                                            <td className="px-4 py-3">
                                                <Badge
                                                    variant={
                                                        location.active
                                                            ? 'secondary'
                                                            : 'outline'
                                                    }
                                                >
                                                    {location.active
                                                        ? 'Active'
                                                        : 'Inactive'}
                                                </Badge>
                                            </td>

                                            <td className="px-4 py-2">
                                                <div
                                                    className="flex justify-end gap-2"
                                                    aria-label={`Actions for ${location.name}`}
                                                >
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        asChild
                                                    >
                                                        <Link
                                                            href={OrganizationStorageLocationController.index(
                                                                [
                                                                    organization.id,
                                                                    location.id,
                                                                ],
                                                            )}
                                                        >
                                                            Storage
                                                        </Link>
                                                    </Button>

                                                    <EditLocationDialog
                                                        organization={
                                                            organization
                                                        }
                                                        location={location}
                                                        trigger={
                                                            <Button
                                                                variant="outline"
                                                                size="sm"
                                                                aria-label={`Edit ${location.name}`}
                                                            >
                                                                Edit
                                                            </Button>
                                                        }
                                                    />
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </>
    );
}

OrganizationLocations.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
