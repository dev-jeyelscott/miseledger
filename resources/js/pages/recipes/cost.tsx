import { Form, Head } from '@inertiajs/react';
import { Fragment } from 'react';
import RecipeController from '@/actions/App/Http/Controllers/Recipes/RecipeController';
import RecipeCostController from '@/actions/App/Http/Controllers/Recipes/RecipeCostController';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';

type Option = {
    id: number;
    name: string;
};

type RecipeComponentCost = {
    componentId: number;
    kind: 'inventory_item' | 'nested_recipe';
    name: string;
    sku: string | null;
    effectiveQuantity: string;
    unitSymbol: string;
    unitCost: string | null;
    extendedCost: string | null;
    status:
        | 'costed'
        | 'missing_location_cost'
        | 'nested_recipe_not_costed'
        | 'nested_recipe_incomplete';
    warning: string | null;
    nestedCost: RecipeCost | null;
};

type RecipeCost = {
    recipeVersionId: number;
    totalCost: string;
    complete: boolean;
    costPerOutputUnit: string | null;
    components: RecipeComponentCost[];
};

type Props = {
    recipe: {
        id: number;
        code: string;
        name: string;
    };
    recipeVersion: {
        id: number;
        versionNumber: number;
        yieldQuantity: string;
        yieldUnitSymbol: string;
    } | null;
    asOf: string;
    currency: string;
    locationOptions: Option[];
    filters: {
        locationId: number | null;
    };
    cost: RecipeCost | null;
    error: string | null;
};

const formatDecimal = (value: string): string => {
    const [rawInteger, rawDecimal = ''] = value.trim().split('.');
    const groupedInteger = rawInteger.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    const decimal = rawDecimal.replace(/0+$/, '');

    return `${groupedInteger}${decimal === '' ? '' : `.${decimal}`}`;
};

const formatDateTime = (value: string): string =>
    new Date(value.replace(' ', 'T')).toLocaleString();

const ComponentRows = ({
    components,
    currency,
    depth,
}: {
    components: RecipeComponentCost[];
    currency: string;
    depth: number;
}) => (
    <>
        {components.map((component) => (
            <Fragment key={component.componentId}>
                <tr className="border-b align-top last:border-b-0">
                    <td
                        className="px-4 py-3"
                        style={{ paddingLeft: `${1 + depth}rem` }}
                    >
                        <div className="font-medium">{component.name}</div>
                        {component.sku && (
                            <div className="text-xs text-muted-foreground">
                                {component.sku}
                            </div>
                        )}
                        {component.kind === 'nested_recipe' && (
                            <div className="text-xs text-muted-foreground">
                                Nested recipe
                            </div>
                        )}
                    </td>
                    <td className="px-4 py-3">
                        {formatDecimal(component.effectiveQuantity)}{' '}
                        {component.unitSymbol}
                    </td>
                    <td className="px-4 py-3 text-right">
                        {component.unitCost === null
                            ? '—'
                            : `${currency} ${formatDecimal(component.unitCost)}`}
                    </td>
                    <td className="px-4 py-3 text-right font-medium">
                        {component.extendedCost === null
                            ? '—'
                            : `${currency} ${formatDecimal(component.extendedCost)}`}
                    </td>
                    <td className="px-4 py-3">
                        {component.warning ? (
                            <span className="text-destructive">
                                {component.warning}
                            </span>
                        ) : (
                            '—'
                        )}
                    </td>
                </tr>

                {component.nestedCost !== null && (
                    <ComponentRows
                        components={component.nestedCost.components}
                        currency={currency}
                        depth={depth + 1}
                    />
                )}
            </Fragment>
        ))}
    </>
);

export default function RecipeCost({
    recipe,
    recipeVersion,
    asOf,
    currency,
    locationOptions,
    filters,
    cost,
    error,
}: Props) {
    return (
        <>
            <Head title={`${recipe.name} cost`} />

            <div className="flex flex-1 flex-col gap-8 p-4">
                <div>
                    <h1 className="text-2xl font-semibold">
                        {recipe.name} cost
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Current recipe cost breakdown as of{' '}
                        {formatDateTime(asOf)}. This view reflects present
                        location item costs only and makes no historical-cost
                        claim.
                    </p>
                </div>

                {error && (
                    <div className="rounded-xl border border-destructive/50 p-5 text-sm text-destructive">
                        {error}
                    </div>
                )}

                {recipeVersion && (
                    <p className="text-sm text-muted-foreground">
                        Version {recipeVersion.versionNumber} &middot; Yield{' '}
                        {formatDecimal(recipeVersion.yieldQuantity)}{' '}
                        {recipeVersion.yieldUnitSymbol}
                    </p>
                )}

                <Form
                    action={RecipeCostController.show(recipe.id).url}
                    method="get"
                >
                    {({ processing }) => (
                        <div className="flex flex-wrap items-end gap-4 rounded-xl border border-sidebar-border/70 p-5 dark:border-sidebar-border">
                            <div className="grid gap-2">
                                <Label>Location</Label>
                                <select
                                    name="location_id"
                                    defaultValue={
                                        filters.locationId?.toString() ?? ''
                                    }
                                    className="h-9 rounded-md border bg-background px-3 text-sm"
                                >
                                    <option value="">Select location</option>
                                    {locationOptions.map((option) => (
                                        <option
                                            key={option.id}
                                            value={option.id}
                                        >
                                            {option.name}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <Button type="submit" disabled={processing}>
                                View cost
                            </Button>
                        </div>
                    )}
                </Form>

                {cost !== null && (
                    <section className="grid gap-5">
                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="rounded-xl border border-sidebar-border/70 p-5 dark:border-sidebar-border">
                                <div className="text-sm text-muted-foreground">
                                    Total cost
                                </div>
                                <div className="mt-2 text-2xl font-semibold">
                                    {currency} {formatDecimal(cost.totalCost)}
                                </div>
                                {!cost.complete && (
                                    <p className="mt-2 text-sm text-destructive">
                                        Incomplete: one or more components are
                                        missing a cost.
                                    </p>
                                )}
                            </div>

                            <div className="rounded-xl border border-sidebar-border/70 p-5 dark:border-sidebar-border">
                                <div className="text-sm text-muted-foreground">
                                    Cost per output unit
                                </div>
                                <div className="mt-2 text-2xl font-semibold">
                                    {cost.costPerOutputUnit === null
                                        ? '—'
                                        : `${currency} ${formatDecimal(cost.costPerOutputUnit)}`}
                                    {recipeVersion &&
                                        ` / ${recipeVersion.yieldUnitSymbol}`}
                                </div>
                            </div>
                        </div>

                        <div className="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                            <table className="w-full text-sm">
                                <thead className="border-b text-left">
                                    <tr>
                                        <th className="px-4 py-3">Component</th>
                                        <th className="px-4 py-3">Quantity</th>
                                        <th className="px-4 py-3 text-right">
                                            Unit cost
                                        </th>
                                        <th className="px-4 py-3 text-right">
                                            Extended cost
                                        </th>
                                        <th className="px-4 py-3">Warning</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {cost.components.length === 0 ? (
                                        <tr>
                                            <td
                                                colSpan={5}
                                                className="px-4 py-8 text-center text-muted-foreground"
                                            >
                                                This recipe version has no
                                                components.
                                            </td>
                                        </tr>
                                    ) : (
                                        <ComponentRows
                                            components={cost.components}
                                            currency={currency}
                                            depth={0}
                                        />
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </section>
                )}

                {cost === null && !error && (
                    <p className="text-sm text-muted-foreground">
                        Select a location to view the current cost breakdown.
                    </p>
                )}
            </div>
        </>
    );
}

RecipeCost.layout = {
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
