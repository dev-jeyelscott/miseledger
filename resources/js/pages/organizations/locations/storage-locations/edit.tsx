import { Form, Head } from '@inertiajs/react';
import OrganizationStorageLocationController from '@/actions/App/Http/Controllers/OrganizationStorageLocationController';
import InputError from '@/components/input-error';
import { PreviousPageButton } from '@/components/navigation/previous-page-button';
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
    storageLocation: StorageLocationSummary;
};

export default function EditStorageLocation({
    organization,
    location,
    storageLocation,
}: Props) {
    return (
        <>
            <Head title={`Edit ${storageLocation.name}`} />

            <div className="flex flex-1 items-start justify-center p-4">
                <div className="w-full max-w-xl rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border">
                    <div className="mb-6">
                        <h1 className="text-2xl font-semibold">
                            Edit storage location
                        </h1>

                        <p className="mt-1 text-sm text-muted-foreground">
                            {organization.name} · {location.name}
                        </p>
                    </div>

                    <Form
                        {...OrganizationStorageLocationController.update.form([
                            organization.id,
                            location.id,
                            storageLocation.id,
                        ])}
                        className="space-y-5"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Name</Label>
                                    <Input
                                        id="name"
                                        name="name"
                                        required
                                        defaultValue={storageLocation.name}
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="code">Code</Label>
                                    <Input
                                        id="code"
                                        name="code"
                                        required
                                        defaultValue={storageLocation.code}
                                    />
                                    <InputError message={errors.code} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="active">Status</Label>

                                    <select
                                        id="active"
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

                                <div className="flex gap-2">
                                    <Button type="submit" disabled={processing}>
                                        Save storage location
                                    </Button>

                                    <PreviousPageButton
                                        fallback={OrganizationStorageLocationController.index.url(
                                            [organization.id, location.id],
                                        )}
                                        variant="outline"
                                    >
                                        Cancel
                                    </PreviousPageButton>
                                </div>
                            </>
                        )}
                    </Form>
                </div>
            </div>
        </>
    );
}

EditStorageLocation.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
