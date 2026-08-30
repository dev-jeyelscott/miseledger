import { Form, Head } from '@inertiajs/react';
import { useEffect } from 'react';
import OrganizationStorageLocationController from '@/actions/App/Http/Controllers/OrganizationStorageLocationController';
import { PreviousPageButton } from '@/components/navigation/previous-page-button';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { useDirtyFormNavigation } from '@/hooks/use-dirty-form-navigation';
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

export default function EditStorageLocation({
    organization,
    location,
    storageLocation,
}: Props) {
    const dirtyFormNavigation = useDirtyFormNavigation(
        'You have unsaved storage location changes. Leave without saving them?',
    );

    return (
        <>
            <Head title={`Edit ${storageLocation.name}`} />

            <div className="flex flex-1 items-start justify-center p-4">
                <div className="w-full max-w-xl rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border">
                    <PageHeader
                        title="Edit storage location"
                        description={`${organization.name} · ${location.name}`}
                        className="mb-6"
                    />

                    <Form
                        {...OrganizationStorageLocationController.update.form([
                            organization.id,
                            location.id,
                            storageLocation.id,
                        ])}
                        className="space-y-5"
                    >
                        {({ processing, errors, isDirty }) => (
                            <>
                                <DirtyStateTracker
                                    dirty={isDirty}
                                    onChange={dirtyFormNavigation.setIsDirty}
                                />

                                <Field
                                    id="name"
                                    label="Name"
                                    error={errors.name}
                                >
                                    <Input
                                        name="name"
                                        required
                                        defaultValue={storageLocation.name}
                                    />
                                </Field>

                                <Field
                                    id="code"
                                    label="Code"
                                    error={errors.code}
                                >
                                    <Input
                                        name="code"
                                        required
                                        defaultValue={storageLocation.code}
                                    />
                                </Field>

                                <Field
                                    id="active"
                                    label="Status"
                                    error={errors.active}
                                >
                                    <NativeSelect
                                        name="active"
                                        defaultValue={
                                            storageLocation.active ? '1' : '0'
                                        }
                                    >
                                        <option value="1">Active</option>
                                        <option value="0">Inactive</option>
                                    </NativeSelect>
                                </Field>

                                <div className="flex gap-2">
                                    <Button type="submit" disabled={processing}>
                                        {processing
                                            ? 'Saving…'
                                            : 'Save storage location'}
                                    </Button>

                                    <PreviousPageButton
                                        fallback={OrganizationStorageLocationController.index.url(
                                            [organization.id, location.id],
                                        )}
                                        variant="outline"
                                        disabled={processing}
                                        onNavigate={
                                            dirtyFormNavigation.confirmNavigation
                                        }
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
