import { Form, Head, router, usePage } from '@inertiajs/react';

import RecipeController from '@/actions/App/Http/Controllers/Recipes/RecipeController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div>
                    <h1 className="text-2xl font-semibold">Edit recipe</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {recipe.name}
                    </p>
                </div>

                <div className="max-w-xl rounded-xl border border-sidebar-border/70 p-5 dark:border-sidebar-border">
                    <Form
                        {...RecipeController.update.form(recipe.id)}
                        errorBag="editRecipe"
                        className="space-y-5"
                    >
                        {({ processing, errors, isDirty }) => (
                            <>
                                <input
                                    type="hidden"
                                    name="return_to"
                                    value={currentUrl}
                                />

                                <div className="grid gap-2">
                                    <Label htmlFor="code">Code</Label>
                                    <Input
                                        id="code"
                                        name="code"
                                        defaultValue={recipe.code}
                                        required
                                        disabled={processing}
                                        aria-invalid={
                                            errors.code ? true : undefined
                                        }
                                    />
                                    <InputError message={errors.code} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="name">Name</Label>
                                    <Input
                                        id="name"
                                        name="name"
                                        defaultValue={recipe.name}
                                        required
                                        disabled={processing}
                                        aria-invalid={
                                            errors.name ? true : undefined
                                        }
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="type">Type</Label>
                                    <select
                                        id="type"
                                        name="type"
                                        defaultValue={recipe.type}
                                        disabled={processing}
                                        aria-invalid={
                                            errors.type ? true : undefined
                                        }
                                        className="h-9 rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50"
                                    >
                                        <option value="menu_item">
                                            Menu item
                                        </option>
                                        <option value="prepared_item">
                                            Prepared item
                                        </option>
                                        <option value="batch">Batch</option>
                                    </select>
                                    <InputError message={errors.type} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="active">Status</Label>
                                    <select
                                        id="active"
                                        name="active"
                                        defaultValue={recipe.active ? '1' : '0'}
                                        disabled={processing}
                                        aria-invalid={
                                            errors.active ? true : undefined
                                        }
                                        className="h-9 rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50"
                                    >
                                        <option value="1">Active</option>
                                        <option value="0">Inactive</option>
                                    </select>
                                    <InputError message={errors.active} />
                                </div>

                                <InputError message={errors.return_to} />

                                <div className="flex gap-2">
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
                                </div>
                            </>
                        )}
                    </Form>
                </div>
            </div>
        </>
    );
}

EditRecipe.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
        {
            title: 'Recipes',
            href: RecipeController.index(),
        },
    ],
};
