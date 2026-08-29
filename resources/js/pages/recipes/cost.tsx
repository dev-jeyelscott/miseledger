import { Form, Head } from '@inertiajs/react';
import { Fragment } from 'react';
import RecipeController from '@/actions/App/Http/Controllers/Recipes/RecipeController';
import RecipeCostController from '@/actions/App/Http/Controllers/Recipes/RecipeCostController';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Field } from '@/components/ui/field';
import { NativeSelect } from '@/components/ui/native-select';
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
    timezone: string;
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

/** Format the current-cost evaluation moment in the active organization timezone. */
const formatOrganizationDateTime = (value: string, timezone: string): string =>
    new Intl.DateTimeFormat('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        timeZone: timezone,
        timeZoneName: 'short',
    }).format(new Date(value));

/** Map one component's costing status to the shared semantic badge vocabulary. */
function componentStatusBadge(component: RecipeComponentCost) {
    switch (component.status) {
        case 'costed':
            return null;
        case 'missing_location_cost':
            return <StatusBadge label="Missing cost" variant="danger" />;
        case 'nested_recipe_not_costed':
            return <StatusBadge label="Not costed" variant="warning" />;
        case 'nested_recipe_incomplete':
            return <StatusBadge label="Incomplete" variant="warning" />;
    }
}

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
                <tr className="border-b border-border align-top last:border-b-0">
                    <td
                        className="px-4 py-3"
                        style={{ paddingLeft: `${1 + depth}rem` }}
                    >
                        <div className="flex flex-wrap items-center gap-2">
                            <span className="font-medium">
                                {component.name}
                            </span>
                            {componentStatusBadge(component)}
                        </div>
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
                    <td className="px-4 py-3 tabular-nums">
                        {formatDecimal(component.effectiveQuantity)}{' '}
                        {component.unitSymbol}
                    </td>
                    <td className="px-4 py-3 text-right tabular-nums">
                        {component.unitCost === null
                            ? 'Not recorded'
                            : `${currency} ${formatDecimal(component.unitCost)}`}
                    </td>
                    <td className="px-4 py-3 text-right font-medium tabular-nums">
                        {component.extendedCost === null
                            ? 'Not recorded'
                            : `${currency} ${formatDecimal(component.extendedCost)}`}
                    </td>
                    <td className="px-4 py-3">
                        {component.warning ? (
                            <span className="text-destructive">
                                {component.warning}
                            </span>
                        ) : (
                            'None'
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

/** Render one component and its nested components as mobile evidence cards. */
const ComponentCards = ({
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
                <article
                    className="rounded-lg border border-border bg-background p-4"
                    style={{ marginLeft: `${depth * 0.75}rem` }}
                >
                    <div className="flex items-start justify-between gap-3">
                        <div className="min-w-0">
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
                        </div>

                        {componentStatusBadge(component)}
                    </div>

                    <dl className="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                        <div>
                            <dt className="text-xs text-muted-foreground">
                                Quantity
                            </dt>
                            <dd className="tabular-nums">
                                {formatDecimal(component.effectiveQuantity)}{' '}
                                {component.unitSymbol}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs text-muted-foreground">
                                Unit cost
                            </dt>
                            <dd className="tabular-nums">
                                {component.unitCost === null
                                    ? 'Not recorded'
                                    : `${currency} ${formatDecimal(component.unitCost)}`}
                            </dd>
                        </div>
                        <div className="col-span-2">
                            <dt className="text-xs text-muted-foreground">
                                Extended cost
                            </dt>
                            <dd className="font-medium tabular-nums">
                                {component.extendedCost === null
                                    ? 'Not recorded'
                                    : `${currency} ${formatDecimal(component.extendedCost)}`}
                            </dd>
                        </div>
                        {component.warning && (
                            <div className="col-span-2">
                                <dt className="text-xs text-muted-foreground">
                                    Warning
                                </dt>
                                <dd className="text-destructive">
                                    {component.warning}
                                </dd>
                            </div>
                        )}
                    </dl>
                </article>

                {component.nestedCost !== null && (
                    <ComponentCards
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
    timezone,
    currency,
    locationOptions,
    filters,
    cost,
    error,
}: Props) {
    return (
        <>
            <Head title={`${recipe.name} cost`} />

            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <PageHeader
                    title={`${recipe.name} cost`}
                    description={`Current recipe cost breakdown as of ${formatOrganizationDateTime(asOf, timezone)}. This view reflects present location item costs only and makes no historical-cost claim.`}
                />

                {error && (
                    <div
                        role="alert"
                        className="rounded-xl border border-destructive/30 bg-destructive/10 p-5 text-sm text-destructive"
                    >
                        {error}
                    </div>
                )}

                {recipeVersion && (
                    <p className="text-sm text-muted-foreground">
                        Version {recipeVersion.versionNumber} · Yield{' '}
                        {formatDecimal(recipeVersion.yieldQuantity)}{' '}
                        {recipeVersion.yieldUnitSymbol}
                    </p>
                )}

                <Form
                    action={RecipeCostController.show(recipe.id).url}
                    method="get"
                >
                    {({ processing }) => (
                        <div className="flex flex-wrap items-end gap-4 rounded-xl border border-border bg-card p-5 shadow-sm">
                            <div className="w-full max-w-xs">
                                <Field id="location_id" label="Location">
                                    <NativeSelect
                                        name="location_id"
                                        defaultValue={
                                            filters.locationId?.toString() ?? ''
                                        }
                                    >
                                        <option value="">
                                            Select location
                                        </option>
                                        {locationOptions.map((option) => (
                                            <option
                                                key={option.id}
                                                value={option.id}
                                            >
                                                {option.name}
                                            </option>
                                        ))}
                                    </NativeSelect>
                                </Field>
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
                            <div className="rounded-xl border border-border bg-card p-5 shadow-sm">
                                <div className="text-sm text-muted-foreground">
                                    Total cost
                                </div>
                                <div className="mt-2 flex items-center gap-2 text-2xl font-semibold tabular-nums">
                                    {currency} {formatDecimal(cost.totalCost)}
                                    <StatusBadge
                                        label={
                                            cost.complete
                                                ? 'Complete'
                                                : 'Incomplete'
                                        }
                                        variant={
                                            cost.complete ? 'success' : 'danger'
                                        }
                                    />
                                </div>
                                {!cost.complete && (
                                    <p className="mt-2 text-sm text-destructive">
                                        One or more components are missing a
                                        cost. This total does not represent a
                                        fully costed recipe.
                                    </p>
                                )}
                            </div>

                            <div className="rounded-xl border border-border bg-card p-5 shadow-sm">
                                <div className="text-sm text-muted-foreground">
                                    Cost per output unit
                                </div>
                                <div className="mt-2 text-2xl font-semibold tabular-nums">
                                    {cost.costPerOutputUnit === null
                                        ? 'Not recorded'
                                        : `${currency} ${formatDecimal(cost.costPerOutputUnit)}`}
                                    {recipeVersion &&
                                        ` / ${recipeVersion.yieldUnitSymbol}`}
                                </div>
                            </div>
                        </div>

                        <div className="grid gap-3 md:hidden">
                            {cost.components.length === 0 ? (
                                <div className="rounded-xl border border-border bg-card p-6 text-center text-sm text-muted-foreground shadow-sm">
                                    This recipe version has no components.
                                </div>
                            ) : (
                                <ComponentCards
                                    components={cost.components}
                                    currency={currency}
                                    depth={0}
                                />
                            )}
                        </div>

                        <div className="hidden overflow-x-auto rounded-xl border border-border bg-card shadow-sm md:block">
                            <table className="w-full text-sm">
                                <thead className="border-b border-border text-left">
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
