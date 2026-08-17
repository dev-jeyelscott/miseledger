import { Form, Head, Link } from '@inertiajs/react';
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

export default function EditRecipe({ recipe }: Props) {
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
                        className="space-y-5"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="code">Code</Label>
                                    <Input
                                        id="code"
                                        name="code"
                                        defaultValue={recipe.code}
                                        required
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
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="type">Type</Label>
                                    <select
                                        id="type"
                                        name="type"
                                        defaultValue={recipe.type}
                                        className="h-9 rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
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
                                        className="h-9 rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                    >
                                        <option value="1">Active</option>
                                        <option value="0">Inactive</option>
                                    </select>
                                    <InputError message={errors.active} />
                                </div>

                                <div className="flex gap-2">
                                    <Button type="submit" disabled={processing}>
                                        Save recipe
                                    </Button>

                                    <Button variant="outline" asChild>
                                        <Link href={RecipeController.index()}>
                                            Back
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
