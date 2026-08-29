import { Form, Head, Link } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import type { ReactNode } from 'react';
import OrganizationLocationController from '@/actions/App/Http/Controllers/OrganizationLocationController';
import OrganizationStorageLocationController from '@/actions/App/Http/Controllers/OrganizationStorageLocationController';
import { EmptyState } from '@/components/empty-state';
import { FilterToolbar } from '@/components/filter-toolbar';
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
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
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
                                <Field
                                    id="create-location-name"
                                    label="Location name"
                                    error={errors.name}
                                >
                                    <Input
                                        name="name"
                                        required
                                        autoFocus
                                        autoComplete="off"
                                        placeholder="e.g., BGC High Street"
                                    />
                                </Field>

                                <Field
                                    id="create-location-code"
                                    label="Location code"
                                    helper="Letters, numbers, hyphens, and underscores only. Codes must be unique within this organization."
                                    error={errors.code}
                                >
                                    <Input
                                        name="code"
                                        required
                                        autoComplete="off"
                                        placeholder="e.g., BGC"
                                    />
                                </Field>

                                <p className="text-xs text-muted-foreground">
                                    New locations are active by default. A
                                    default storage area is created
                                    automatically and more can be added from
                                    this location&rsquo;s Storage page.
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
                                <Field
                                    id={`${fieldPrefix}-name`}
                                    label="Location name"
                                    error={errors.name}
                                >
                                    <Input
                                        name="name"
                                        required
                                        autoFocus
                                        defaultValue={location.name}
                                        autoComplete="off"
                                    />
                                </Field>

                                <Field
                                    id={`${fieldPrefix}-code`}
                                    label="Location code"
                                    helper="Letters, numbers, hyphens, and underscores only."
                                    error={errors.code}
                                >
                                    <Input
                                        name="code"
                                        required
                                        defaultValue={location.code}
                                        autoComplete="off"
                                    />
                                </Field>

                                <Field
                                    id={`${fieldPrefix}-active`}
                                    label="Status"
                                    helper="Deactivating this location keeps its storage areas and history but blocks new inventory activity here. Deactivation may be blocked when this location is still required by an active inventory workflow."
                                    helperId={statusHelpId}
                                    error={errors.active}
                                >
                                    <NativeSelect
                                        name="active"
                                        defaultValue={
                                            location.active ? '1' : '0'
                                        }
                                    >
                                        <option value="1">Active</option>
                                        <option value="0">Inactive</option>
                                    </NativeSelect>
                                </Field>

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
                <PageHeader
                    title="Organization locations"
                    description={`Manage inventory locations and their storage areas for ${organization.name}. Each location contains one or more storage areas — the specific shelves, coolers, or rooms where inventory is tracked.`}
                    actions={
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
                    }
                />

                <section
                    aria-label="Organization locations"
                    className="overflow-hidden rounded-xl border border-border bg-card"
                >
                    <FilterToolbar className="grid gap-3 rounded-none border-0 border-b border-border md:grid-cols-[minmax(0,1fr)_12rem_auto] md:items-end">
                        <Field id="location-search" label="Search locations">
                            <div className="relative">
                                <Search
                                    className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                    aria-hidden="true"
                                />

                                <Input
                                    type="search"
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    placeholder="Search locations by name or code..."
                                    className="pl-9"
                                />
                            </div>
                        </Field>

                        <Field
                            id="location-status-filter"
                            label="Filter locations by status"
                        >
                            <NativeSelect
                                value={statusFilter}
                                onChange={(event) =>
                                    setStatusFilter(
                                        event.target
                                            .value as LocationStatusFilter,
                                    )
                                }
                            >
                                <option value="all">All statuses</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </NativeSelect>
                        </Field>

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
                    </FilterToolbar>

                    <div className="grid gap-3 p-4 md:hidden">
                        {filteredLocations.length === 0 ? (
                            <EmptyState
                                title={
                                    hasFilters
                                        ? 'No locations match these filters.'
                                        : 'No locations have been configured yet.'
                                }
                                description={
                                    hasFilters
                                        ? 'Adjust or reset the filters to see more locations.'
                                        : 'Add your first location to start organizing inventory storage.'
                                }
                            />
                        ) : (
                            filteredLocations.map((location) => (
                                <article
                                    key={location.id}
                                    className="rounded-lg border border-border bg-background p-4"
                                >
                                    <div className="flex items-start justify-between gap-3">
                                        <div>
                                            <div className="font-medium">
                                                {location.name}
                                            </div>
                                            <span className="mt-1 inline-block rounded-md bg-muted px-2 py-1 font-mono text-xs">
                                                {location.code}
                                            </span>
                                        </div>

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
                                    </div>

                                    <div className="mt-4 flex gap-2 border-t border-border pt-3">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            className="flex-1"
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
                                            organization={organization}
                                            location={location}
                                            trigger={
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    className="flex-1"
                                                    aria-label={`Edit ${location.name}`}
                                                >
                                                    Edit
                                                </Button>
                                            }
                                        />
                                    </div>
                                </article>
                            ))
                        )}
                    </div>

                    <div className="hidden overflow-x-auto md:block">
                        <table className="w-full min-w-[680px] text-sm">
                            <caption className="sr-only">
                                Locations configured for {organization.name}
                            </caption>

                            <thead className="bg-muted/40 text-left text-xs text-muted-foreground">
                                <tr className="border-b border-border">
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
                                        <td colSpan={4} className="px-4 py-12">
                                            <EmptyState
                                                title={
                                                    hasFilters
                                                        ? 'No locations match these filters.'
                                                        : 'No locations have been configured yet.'
                                                }
                                                description={
                                                    hasFilters
                                                        ? 'Adjust or reset the filters to see more locations.'
                                                        : 'Add your first location to start organizing inventory storage.'
                                                }
                                            />
                                        </td>
                                    </tr>
                                ) : (
                                    filteredLocations.map((location) => (
                                        <tr
                                            key={location.id}
                                            className="border-b border-border transition-colors last:border-b-0 hover:bg-muted/30"
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
