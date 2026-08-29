import { Form, Head } from '@inertiajs/react';
import OrganizationController from '@/actions/App/Http/Controllers/OrganizationController';
import { PreviousPageButton } from '@/components/navigation/previous-page-button';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { dashboard } from '@/routes';

export default function CreateOrganization() {
    return (
        <>
            <Head title="Create organization" />

            <div className="mx-auto w-full max-w-xl p-4 sm:p-6">
                <div className="rounded-xl border border-border bg-card p-6 shadow-sm">
                    <PageHeader
                        title="Create organization"
                        description="An organization is a fully isolated tenant boundary: its inventory, locations, recipes, and users are never shared with any other organization."
                        className="mb-6"
                    />

                    <Form
                        {...OrganizationController.store.form()}
                        className="grid gap-6"
                    >
                        {({ processing, errors }) => (
                            <>
                                <Field
                                    id="name"
                                    label="Organization name"
                                    helper="You can invite team members and add locations after the organization is created."
                                    error={errors.name}
                                >
                                    <Input
                                        name="name"
                                        required
                                        maxLength={160}
                                        autoFocus
                                        autoComplete="organization"
                                        placeholder="Example Restaurant Group"
                                    />
                                </Field>

                                <div className="flex items-center gap-3">
                                    <Button type="submit" disabled={processing}>
                                        {processing
                                            ? 'Creating…'
                                            : 'Create organization'}
                                    </Button>

                                    <PreviousPageButton
                                        fallback={dashboard.url()}
                                        variant="outline"
                                        disabled={processing}
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
