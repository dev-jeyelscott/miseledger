import { Form, Head } from '@inertiajs/react';
import OrganizationController from '@/actions/App/Http/Controllers/OrganizationController';
import InputError from '@/components/input-error';
import { PreviousPageButton } from '@/components/navigation/previous-page-button';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';

export default function CreateOrganization() {
    return (
        <>
            <Head title="Create organization" />

            <div className="mx-auto w-full max-w-xl p-4">
                <div className="rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border">
                    <div className="mb-6 space-y-2">
                        <h1 className="text-2xl font-semibold">
                            Create organization
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Create the tenant boundary for your restaurant
                            inventory data.
                        </p>
                    </div>

                    <Form
                        {...OrganizationController.store.form()}
                        className="space-y-6"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="name">
                                        Organization name
                                    </Label>

                                    <Input
                                        id="name"
                                        name="name"
                                        required
                                        maxLength={160}
                                        autoFocus
                                        autoComplete="organization"
                                        placeholder="Example Restaurant Group"
                                    />

                                    <InputError message={errors.name} />
                                </div>

                                <div className="flex items-center gap-3">
                                    <Button type="submit" disabled={processing}>
                                        Create organization
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

CreateOrganization.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
        {
            title: 'Create organization',
            href: OrganizationController.create(),
        },
    ],
};
