import { Form, Head, Link } from '@inertiajs/react';
import OrganizationLocationController from '@/actions/App/Http/Controllers/OrganizationLocationController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import type { LocationSummary, OrganizationSummary } from '@/types';

type Props = {
    organization: OrganizationSummary;
    location: LocationSummary;
};

export default function EditOrganizationLocation({
    organization,
    location,
}: Props) {
    return (
        <>
            <Head title={`Edit ${location.name}`} />

            <div className="flex flex-1 items-start justify-center p-4">
                <div className="w-full max-w-xl rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border">
                    <div className="mb-6">
                        <h1 className="text-2xl font-semibold">
                            Edit location
                        </h1>

                        <p className="mt-1 text-sm text-muted-foreground">
                            {organization.name}
                        </p>
                    </div>

                    <Form
                        {...OrganizationLocationController.update.form([
                            organization.id,
                            location.id,
                        ])}
                        className="space-y-5"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Location name</Label>

                                    <Input
                                        id="name"
                                        name="name"
                                        required
                                        defaultValue={location.name}
                                        autoComplete="off"
                                    />

                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="code">Location code</Label>

                                    <Input
                                        id="code"
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
                                    <Label htmlFor="active">Status</Label>

                                    <select
                                        id="active"
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
                                        asChild
                                    >
                                        <Link
                                            href={OrganizationLocationController.index(
                                                organization.id,
                                            )}
                                        >
                                            Cancel
                                        </Link>
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                </div>
            </div>
        </>
    );
}

EditOrganizationLocation.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
