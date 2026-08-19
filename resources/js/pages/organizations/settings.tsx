import { Form, Head } from '@inertiajs/react';
import OrganizationController from '@/actions/App/Http/Controllers/OrganizationController';
import InputError from '@/components/input-error';
import { PreviousPageButton } from '@/components/navigation/previous-page-button';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';

type Props = {
    organization: {
        id: number;
        name: string;
        slug: string;
        timezone: string;
        currency: string;
        active: boolean;
    };
};

export default function OrganizationSettings({ organization }: Props) {
    return (
        <>
            <Head title="Organization settings" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div>
                    <h1 className="text-2xl font-semibold">
                        Organization settings
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Manage the configuration for {organization.name}.
                    </p>
                </div>

                <div className="max-w-xl rounded-xl border border-sidebar-border/70 p-5 dark:border-sidebar-border">
                    <Form
                        {...OrganizationController.update.form(organization.id)}
                        className="space-y-5"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Name</Label>
                                    <Input
                                        id="name"
                                        name="name"
                                        defaultValue={organization.name}
                                        required
                                        maxLength={160}
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="slug">Slug</Label>
                                    <Input
                                        id="slug"
                                        name="slug"
                                        defaultValue={organization.slug}
                                        required
                                        maxLength={160}
                                    />
                                    <InputError message={errors.slug} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="timezone">Timezone</Label>
                                    <Input
                                        id="timezone"
                                        name="timezone"
                                        defaultValue={organization.timezone}
                                        required
                                        maxLength={64}
                                        placeholder="Asia/Manila"
                                    />
                                    <InputError message={errors.timezone} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="currency">Currency</Label>
                                    <Input
                                        id="currency"
                                        name="currency"
                                        defaultValue={organization.currency}
                                        required
                                        maxLength={3}
                                        placeholder="PHP"
                                    />
                                    <InputError message={errors.currency} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="active">Status</Label>
                                    <select
                                        id="active"
                                        name="active"
                                        defaultValue={
                                            organization.active ? '1' : '0'
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
                                        Save settings
                                    </Button>
                                    <PreviousPageButton
                                        fallback={dashboard.url()}
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

OrganizationSettings.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
