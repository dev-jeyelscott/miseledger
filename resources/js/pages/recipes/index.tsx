import { Form, Head, Link } from '@inertiajs/react';
import RecipeController from '@/actions/App/Http/Controllers/Recipes/RecipeController';
import RecipeCostController from '@/actions/App/Http/Controllers/Recipes/RecipeCostController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import type { RecipeData } from '@/types';

type Props = {
    recipes: RecipeData[];
    canManage: boolean;
    canViewCosts: boolean;
};

const typeLabels: Record<RecipeData['type'], string> = {
    menu_item: 'Menu item',
    prepared_item: 'Prepared item',
    batch: 'Batch',
};

export default function RecipesIndex({
    recipes,
    canManage,
    canViewCosts,
}: Props) {
    return (
        <>
            <Head title="Recipes" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div>
                    <h1 className="text-2xl font-semibold">Recipes</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Stable recipe identities for menu items, prepared items,
                        and batches.
                    </p>
                </div>

                <div
                    className={
                        canManage
                            ? 'grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px]'
                            : ''
                    }
                >
                    <div className="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                        <div className="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                            {recipes.length === 0 ? (
                                <div className="px-5 py-10 text-center text-sm text-muted-foreground">
                                    No recipes have been created.
                                </div>
                            ) : (
                                recipes.map((recipe) => (
                                    <div
                                        key={recipe.id}
                                        className="flex items-center justify-between gap-4 px-5 py-4"
                                    >
                                        <div>
                                            <p className="font-medium">
                                                {recipe.name}
                                            </p>
                                            <p className="mt-1 text-sm text-muted-foreground">
                                                {recipe.code} &middot;{' '}
                                                {typeLabels[recipe.type]}{' '}
                                                &middot;{' '}
                                                {recipe.active
                                                    ? 'Active'
                                                    : 'Inactive'}
                                            </p>
                                        </div>

                                        <div className="flex items-center gap-2">
                                            {canViewCosts && (
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    asChild
                                                >
                                                    <Link
                                                        href={RecipeCostController.show(
                                                            recipe.id,
                                                        )}
                                                    >
                                                        Cost
                                                    </Link>
                                                </Button>
                                            )}

                                            {canManage && (
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    asChild
                                                >
                                                    <Link
                                                        href={RecipeController.edit(
                                                            recipe.id,
                                                        )}
                                                    >
                                                        Edit
                                                    </Link>
                                                </Button>
                                            )}
                                        </div>
                                    </div>
                                ))
                            )}
                        </div>
                    </div>

                    {canManage && (
                        <div className="h-fit rounded-xl border border-sidebar-border/70 p-5 dark:border-sidebar-border">
                            <h2 className="mb-5 font-medium">Create recipe</h2>

                            <Form
                                {...RecipeController.store.form()}
                                className="space-y-5"
                                resetOnSuccess
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <input
                                            type="hidden"
                                            name="active"
                                            value="1"
                                        />

                                        <div className="grid gap-2">
                                            <Label htmlFor="code">Code</Label>
                                            <Input
                                                id="code"
                                                name="code"
                                                required
                                                placeholder="RCP-00001"
                                            />
                                            <InputError message={errors.code} />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="name">Name</Label>
                                            <Input
                                                id="name"
                                                name="name"
                                                required
                                                placeholder="Cheeseburger"
                                            />
                                            <InputError message={errors.name} />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="type">Type</Label>
                                            <select
                                                id="type"
                                                name="type"
                                                defaultValue="menu_item"
                                                className="h-9 rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                            >
                                                <option value="menu_item">
                                                    Menu item
                                                </option>
                                                <option value="prepared_item">
                                                    Prepared item
                                                </option>
                                                <option value="batch">
                                                    Batch
                                                </option>
                                            </select>
                                            <InputError message={errors.type} />
                                        </div>

                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            Create recipe
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}

RecipesIndex.layout = {
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
