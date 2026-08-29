import { Form, Head, router, usePage } from '@inertiajs/react';

import RecipeController from '@/actions/App/Http/Controllers/Recipes/RecipeController';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { dashboard } from '@/routes';
import type { RecipeData } from '@/types';

type Props = {
    recipe: RecipeData;
};

/**
 * Resolve an explicitly supplied local return target without allowing an
 * external origin to become a navigation destination.
 */
function returnTargetFromPageUrl(pageUrl: string): string | null {
    const url = new URL(pageUrl, 'http://miseledger.local');
    const returnTo = url.searchParams.get('return_to');

    if (
        returnTo === null ||
        !returnTo.startsWith('/') ||
        returnTo.startsWith('//')
    ) {
        return null;
    }

    return returnTo;
}

/**
 * Determine whether native history has a trustworthy same-origin predecessor.
 */
function canUseBrowserBack(): boolean {
    if (
        typeof window === 'undefined' ||
        window.history.length <= 1 ||
        document.referrer === ''
    ) {
        return false;
    }

    const navigationEntry = window.performance.getEntriesByType(
        'navigation',
    )[0] as PerformanceNavigationTiming | undefined;

    if (navigationEntry?.type === 'reload') {
        return false;
    }

    try {
        return new URL(document.referrer).origin === window.location.origin;
    } catch {
        return false;
    }
}

export default function EditRecipe({ recipe }: Props) {
    const currentUrl = usePage().url;

    const goBack = (isDirty: boolean): void => {
        if (isDirty && !window.confirm('Discard unsaved recipe changes?')) {
            return;
        }

        const returnTo = returnTargetFromPageUrl(currentUrl);

        if (returnTo !== null) {
            router.visit(returnTo, {
                replace: true,
            });

            return;
        }

        if (canUseBrowserBack()) {
            window.history.back();

            return;
        }

        router.visit(RecipeController.index().url, {
            replace: true,
        });
    };

    return (
        <>
            <Head title={`Edit ${recipe.name}`} />

            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <PageHeader
                    title="Edit recipe"
                    description={`${recipe.name} · stable identity metadata only. Yield, components, and formulation belong to recipe versions.`}
                />

                <div className="max-w-xl rounded-xl border border-border bg-card p-5 shadow-sm">
                    <Form
                        {...RecipeController.update.form(recipe.id)}
                        errorBag="editRecipe"
                        className="grid gap-5"
                    >
                        {({ processing, errors, isDirty }) => (
                            <>
                                <input
                                    type="hidden"
                                    name="return_to"
                                    value={currentUrl}
                                />

                                <Field
                                    id="code"
                                    label="Code"
                                    error={errors.code}
                                >
                                    <Input
                                        name="code"
                                        defaultValue={recipe.code}
                                        required
                                        disabled={processing}
                                    />
                                </Field>

                                <Field
                                    id="name"
                                    label="Name"
                                    error={errors.name}
                                >
                                    <Input
                                        name="name"
                                        defaultValue={recipe.name}
                                        required
                                        disabled={processing}
                                    />
                                </Field>

                                <Field
                                    id="type"
                                    label="Type"
                                    error={errors.type}
                                >
                                    <NativeSelect
                                        name="type"
                                        defaultValue={recipe.type}
                                        disabled={processing}
                                    >
                                        <option value="menu_item">
                                            Menu item
                                        </option>
                                        <option value="prepared_item">
                                            Prepared item
                                        </option>
                                        <option value="batch">Batch</option>
                                    </NativeSelect>
                                </Field>

                                <Field
                                    id="active"
                                    label="Status"
                                    error={errors.active}
                                >
                                    <NativeSelect
                                        name="active"
                                        defaultValue={recipe.active ? '1' : '0'}
                                        disabled={processing}
                                    >
                                        <option value="1">Active</option>
                                        <option value="0">Inactive</option>
                                    </NativeSelect>
                                </Field>

                                <InputError message={errors.return_to} />

                                <div className="flex flex-wrap items-center gap-2">
                                    <Button type="submit" disabled={processing}>
                                        {processing ? 'Saving…' : 'Save recipe'}
                                    </Button>

                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={processing}
                                        onClick={() => goBack(isDirty)}
                                    >
                                        Back
                                    </Button>

                                    {isDirty && (
                                        <span className="text-sm text-muted-foreground">
                                            Unsaved changes
                                        </span>
                                    )}
                                </div>
                            </>
                        )}
                    </Form>
                </div>
            </div>
        </>
    );
}

EditRecipe.layout = (page: Props) => ({
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
        {
            title: 'Recipes',
            href: RecipeController.index(),
        },
        {
            title: page.recipe.name,
            href: RecipeController.edit(page.recipe.id),
        },
    ],
});
