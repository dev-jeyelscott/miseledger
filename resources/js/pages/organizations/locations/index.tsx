import { Form, Head, Link } from '@inertiajs/react';
import OrganizationLocationController from '@/actions/App/Http/Controllers/OrganizationLocationController';
import OrganizationStorageLocationController from '@/actions/App/Http/Controllers/OrganizationStorageLocationController';
import InputError from '@/components/input-error';
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
import type { LocationSummary, OrganizationSummary } from '@/types';

type Props = {
    organization: OrganizationSummary;
    locations: LocationSummary[];
};

type EditLocationDialogProps = {
    organization: OrganizationSummary;
    location: LocationSummary;
};

/** Edit one location in-place while retaining the management workspace context. */
function EditLocationDialog({
    organization,
    location,
}: EditLocationDialogProps) {
    const dialog = useGuardedDialog(
        'Discard the location changes you entered?',
    );
    const fieldPrefix = `edit-location-${location.id}`;

    return (
        <Dialog open={dialog.open} onOpenChange={dialog.onOpenChange}>
            <DialogTrigger asChild>
                <Button variant="outline" size="sm">
                    Edit
                </Button>
            </DialogTrigger>

            <DialogContent className="max-h-[calc(100vh-2rem)] overflow-y-auto sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>Edit location</DialogTitle>
                    <DialogDescription>{organization.name}</DialogDescription>
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
                                        className="h-9 rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                    >
                                        <option value="1">Active</option>
                                        <option value="0">Inactive</option>
                                    </select>
                                    <InputError message={errors.active} />
                                </div>

                                <div className="flex flex-wrap gap-2">
                                    <Button type="submit" disabled={processing}>
                                        Save location
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

export default function OrganizationLocations({
    organization,
    locations,
}: Props) {
    return (
        <>
            <Head title={`${organization.name} locations`} />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div>
                    <h1 className="text-2xl font-semibold">
                        Organization locations
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {organization.name}
                    </p>
                </div>

                <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
                    <div className="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                        <div className="border-b border-sidebar-border/70 px-5 py-4 dark:border-sidebar-border">
                            <h2 className="font-medium">Current locations</h2>
                        </div>

                        {locations.length === 0 ? (
                            <div className="px-5 py-8 text-sm text-muted-foreground">
                                No locations have been configured yet.
                            </div>
                        ) : (
                            <div className="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                                {locations.map((location) => (
                                    <div
                                        key={location.id}
                                        className="flex items-center justify-between gap-4 px-5 py-4"
                                    >
                                        <div className="min-w-0">
                                            <p className="truncate font-medium">
                                                {location.name}
                                            </p>

                                            <div className="mt-1 flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                                                <span>{location.code}</span>

                                                <span
                                                    className={`rounded-md border px-2 py-0.5 text-xs ${
                                                        location.active
                                                            ? ''
                                                            : 'text-muted-foreground'
                                                    }`}
                                                >
                                                    {location.active
                                                        ? 'Active'
                                                        : 'Inactive'}
                                                </span>
                                            </div>
                                        </div>

                                        <div className="flex shrink-0 gap-2">
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
                                                organization={organization}
                                                location={location}
                                            />
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>

                    <div className="h-fit rounded-xl border border-sidebar-border/70 p-5 dark:border-sidebar-border">
                        <div className="mb-5">
                            <h2 className="font-medium">Add location</h2>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Codes identify locations throughout inventory
                                workflows and must be unique in this
                                organization.
                            </p>
                        </div>

                        <Form
                            {...OrganizationLocationController.store.form(
                                organization.id,
                            )}
                            errorBag="createLocation"
                            className="space-y-5"
                            resetOnSuccess
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="name">
                                            Location name
                                        </Label>

                                        <Input
                                            id="name"
                                            name="name"
                                            required
                                            autoComplete="off"
                                            placeholder="Main Restaurant"
                                        />

                                        <InputError message={errors.name} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="code">
                                            Location code
                                        </Label>

                                        <Input
                                            id="code"
                                            name="code"
                                            required
                                            autoComplete="off"
                                            placeholder="MAIN"
                                        />

                                        <p className="text-xs text-muted-foreground">
                                            Letters, numbers, hyphens, and
                                            underscores only.
                                        </p>

                                        <InputError message={errors.code} />
                                    </div>

                                    <Button type="submit" disabled={processing}>
                                        Add location
                                    </Button>
                                </>
                            )}
                        </Form>
                    </div>
                </div>
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
