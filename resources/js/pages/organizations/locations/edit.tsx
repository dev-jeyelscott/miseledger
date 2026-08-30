import { Form, Head } from '@inertiajs/react';
import { useEffect } from 'react';
import OrganizationLocationController from '@/actions/App/Http/Controllers/OrganizationLocationController';
import { PreviousPageButton } from '@/components/navigation/previous-page-button';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { useDirtyFormNavigation } from '@/hooks/use-dirty-form-navigation';
import { dashboard } from '@/routes';
import type { LocationSummary, OrganizationSummary } from '@/types';

type Props = {
    organization: OrganizationSummary;
    location: LocationSummary;
};

/** Sync the Inertia form's dirty state into the shared navigation guard. */
function DirtyStateTracker({
    dirty,
    onChange,
}: {
    dirty: boolean;
    onChange: (dirty: boolean) => void;
}) {
    useEffect(() => {
        onChange(dirty);
    }, [dirty, onChange]);

    return null;
}

export default function EditOrganizationLocation({
    organization,
    location,
}: Props) {
    const dirtyFormNavigation = useDirtyFormNavigation(
        'You have unsaved location changes. Leave without saving them?',
    );

    return (
        <>
            <Head title={`Edit ${location.name}`} />

            <div className="flex flex-1 items-start justify-center p-4 sm:p-6">
                <div className="w-full max-w-xl rounded-xl border border-border bg-card p-6 shadow-sm">
                    <PageHeader
                        title="Edit location"
                        description={`${organization.name} · ${location.name}`}
                        className="mb-6"
                    />

                    <Form
                        {...OrganizationLocationController.update.form([
                            organization.id,
                            location.id,
                        ])}
                        className="grid gap-5"
                    >
                        {({ processing, errors, isDirty }) => {
                            return (
                                <>
                                    <DirtyStateTracker
                                        dirty={isDirty}
                                        onChange={
                                            dirtyFormNavigation.setIsDirty
                                        }
                                    />

                                    <Field
                                        id="name"
                                        label="Location name"
                                        error={errors.name}
                                    >
                                        <Input
                                            name="name"
                                            required
                                            defaultValue={location.name}
                                            autoComplete="off"
                                        />
                                    </Field>

                                    <Field
                                        id="code"
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
                                        id="active"
                                        label="Status"
                                        helper="Deactivating this location keeps its storage areas and history but blocks new inventory activity here. Deactivation may be blocked when this location is still required by an active inventory workflow."
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

                                    <div className="flex flex-wrap items-center gap-2">
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            {processing
                                                ? 'Saving…'
                                                : 'Save location'}
                                        </Button>

                                        <PreviousPageButton
                                            fallback={OrganizationLocationController.index.url(
                                                organization.id,
                                            )}
                                            variant="outline"
                                            disabled={processing}
                                            onNavigate={
                                                dirtyFormNavigation.confirmNavigation
                                            }
                                        >
                                            Cancel
                                        </PreviousPageButton>

                                        {isDirty && (
                                            <span className="text-sm text-muted-foreground">
                                                Unsaved changes
                                            </span>
                                        )}
                                    </div>
                                </>
                            );
                        }}
                    </Form>
                </div>
            </div>
        </>
    );
}

EditOrganizationLocation.layout = (page: Props) => ({
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
        {
            title: 'Organization locations',
            href: OrganizationLocationController.index(page.organization.id),
        },
        {
            title: page.location.name,
            href: OrganizationLocationController.edit([
                page.organization.id,
                page.location.id,
            ]),
        },
    ],
});
