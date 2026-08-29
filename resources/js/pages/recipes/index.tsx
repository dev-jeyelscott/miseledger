import { Form, Head, Link, useForm, usePage } from '@inertiajs/react';
import {
    Boxes,
    CheckCircle2,
    ChevronDown,
    ChevronUp,
    ChevronsUpDown,
    ClipboardList,
    Coins,
    Filter,
    NotebookText,
    PackageCheck,
    Pencil,
    Plus,
    RotateCcw,
    Search,
} from 'lucide-react';
import { useState } from 'react';

import RecipeController from '@/actions/App/Http/Controllers/Recipes/RecipeController';
import RecipeCostController from '@/actions/App/Http/Controllers/Recipes/RecipeCostController';
import { DashboardMetricCard } from '@/components/dashboard/dashboard-metric-card';
import { EmptyState } from '@/components/empty-state';
import { FilterToolbar } from '@/components/filter-toolbar';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { PaginationControls } from '@/components/pagination-controls';
import { StatusBadge } from '@/components/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { dashboard } from '@/routes';

type RecipeType = 'menu_item' | 'prepared_item' | 'batch';
type RecipeActivity = 'all' | 'active' | 'inactive';
type RecipeSort = 'name' | 'type' | 'activity' | 'updated_at';
type SortDirection = 'asc' | 'desc';

type RecipeRow = {
    id: number;
    code: string;
    name: string;
    type: RecipeType;
    active: boolean;
    versionCount: number;
    publishedVersionCount: number;
    draftVersionCount: number;
    latestVersionNumber: number | null;
    updatedAt: string | null;
};

type RecipeSummary = {
    totalCount: number;
    activeCount: number;
    menuItemCount: number;
    preparedItemCount: number;
    batchCount: number;
};

type Pagination = {
    currentPage: number;
    from: number | null;
    lastPage: number;
    nextPageUrl: string | null;
    perPage: number;
    previousPageUrl: string | null;
    to: number | null;
    total: number;
};

type Filters = {
    search: string | null;
    type: 'all' | RecipeType;
    activity: RecipeActivity;
    sort: RecipeSort;
    direction: SortDirection;
    perPage: number;
};

type Props = {
    rows: RecipeRow[];
    pagination: Pagination;
    summary: RecipeSummary;
    filters: Filters;
    canManage: boolean;
    canViewCosts: boolean;
    timezone: string;
};

type RecipeFormData = {
    code: string;
    name: string;
    type: RecipeType;
    active: boolean;
    return_to: string;
};

type RecipeIdentityDialogProps = {
    recipe: RecipeRow | null;
    returnTo: string;
    onClose: () => void;
};

const typeLabels: Record<RecipeType, string> = {
    menu_item: 'Menu item',
    prepared_item: 'Prepared item',
    batch: 'Batch',
};

/** Format persisted timestamps in the active organization's configured timezone. */
function formatUpdatedAt(value: string | null, timezone: string): string {
    if (value === null) {
        return 'Not recorded';
    }

    return new Intl.DateTimeFormat('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        timeZone: timezone,
    }).format(new Date(value));
}

/**
 * Render recipe identity type using deliberate categorical color. This is a
 * design-system exception: recipe type is a meaningful, stable classification
 * rather than a lifecycle status, so it keeps its own color vocabulary.
 */
function TypeBadge({ type }: { type: RecipeType }) {
    const classes: Record<RecipeType, string> = {
        menu_item:
            'border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-900 dark:bg-blue-950/40 dark:text-blue-300',
        prepared_item:
            'border-violet-200 bg-violet-50 text-violet-700 dark:border-violet-900 dark:bg-violet-950/40 dark:text-violet-300',
        batch: 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-300',
    };

    return (
        <Badge variant="outline" className={classes[type]}>
            {typeLabels[type]}
        </Badge>
    );
}

/** Render recipe master activity with the shared semantic status vocabulary. */
function ActivityBadge({ active }: { active: boolean }) {
    return active ? (
        <StatusBadge label="Active" variant="success" />
    ) : (
        <StatusBadge label="Inactive" variant="neutral" />
    );
}

/** Summarize persisted version coverage without choosing an arbitrary version. */
function VersionCoverage({ row }: { row: RecipeRow }) {
    if (row.versionCount === 0) {
        return <span className="text-muted-foreground">No versions</span>;
    }

    const details = [
        row.publishedVersionCount > 0
            ? `${row.publishedVersionCount} published`
            : null,
        row.draftVersionCount > 0 ? `${row.draftVersionCount} draft` : null,
    ]
        .filter((value): value is string => value !== null)
        .join(' · ');

    return (
        <div>
            <div className="font-medium tabular-nums">
                {row.latestVersionNumber === null
                    ? `${row.versionCount} versions`
                    : `v${row.latestVersionNumber}`}
            </div>
            <div className="mt-0.5 text-xs text-muted-foreground">
                {details || `${row.versionCount} total`}
            </div>
        </div>
    );
}

/** Render the current table sort direction. */
function SortIndicator({
    active,
    direction,
}: {
    active: boolean;
    direction: SortDirection;
}) {
    if (!active) {
        return <ChevronsUpDown className="size-3.5" aria-hidden="true" />;
    }

    return direction === 'asc' ? (
        <ChevronUp className="size-3.5" aria-hidden="true" />
    ) : (
        <ChevronDown className="size-3.5" aria-hidden="true" />
    );
}

/**
 * Create or update one stable recipe identity without leaving the index.
 * Version composition and costing remain separate workflows.
 */
function RecipeIdentityDialog({
    recipe,
    returnTo,
    onClose,
}: RecipeIdentityDialogProps) {
    const isCreate = recipe === null;

    const form = useForm<RecipeFormData>({
        code: recipe?.code ?? '',
        name: recipe?.name ?? '',
        type: recipe?.type ?? 'menu_item',
        active: recipe?.active ?? true,
        return_to: returnTo,
    });

    const closeDialog = (): void => {
        if (form.processing) {
            return;
        }

        if (
            form.isDirty &&
            !window.confirm('Discard unsaved recipe changes?')
        ) {
            return;
        }

        form.reset();
        form.clearErrors();
        onClose();
    };

    const handleSuccess = (): void => {
        form.reset();
        form.clearErrors();
        onClose();
    };

    const submit = (): void => {
        if (recipe === null) {
            form.submit(RecipeController.store(), {
                preserveScroll: true,
                errorBag: 'createRecipe',
                onSuccess: handleSuccess,
            });

            return;
        }

        form.submit(RecipeController.update(recipe.id), {
            preserveScroll: true,
            errorBag: 'editRecipe',
            onSuccess: handleSuccess,
        });
    };

    return (
        <Dialog
            open
            onOpenChange={(open) => {
                if (!open) {
                    closeDialog();
                }
            }}
        >
            <DialogContent className="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>
                        {isCreate ? 'Create recipe' : 'Edit recipe'}
                    </DialogTitle>
                    <DialogDescription>
                        {isCreate
                            ? 'Create the stable recipe identity. Yield, components, and formulation belong to recipe versions.'
                            : 'Update recipe identity metadata without changing historical recipe versions.'}
                    </DialogDescription>
                </DialogHeader>

                <form
                    className="grid gap-5"
                    aria-busy={form.processing}
                    onSubmit={(event) => {
                        event.preventDefault();
                        submit();
                    }}
                >
                    <Field
                        id="recipe-dialog-code"
                        label="Code"
                        error={form.errors.code}
                    >
                        <Input
                            value={form.data.code}
                            required
                            autoFocus
                            autoComplete="off"
                            placeholder={isCreate ? 'RCP-00001' : undefined}
                            disabled={form.processing}
                            onChange={(event) =>
                                form.setData('code', event.target.value)
                            }
                        />
                    </Field>

                    <Field
                        id="recipe-dialog-name"
                        label="Name"
                        error={form.errors.name}
                    >
                        <Input
                            value={form.data.name}
                            required
                            autoComplete="off"
                            placeholder={isCreate ? 'Cheeseburger' : undefined}
                            disabled={form.processing}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                        />
                    </Field>

                    <Field
                        id="recipe-dialog-type"
                        label="Type"
                        error={form.errors.type}
                        helper="Stable identity classification. Yield and composition live on recipe versions."
                    >
                        <NativeSelect
                            value={form.data.type}
                            disabled={form.processing}
                            onChange={(event) =>
                                form.setData(
                                    'type',
                                    event.target.value as RecipeType,
                                )
                            }
                        >
                            <option value="menu_item">Menu item</option>
                            <option value="prepared_item">Prepared item</option>
                            <option value="batch">Batch</option>
                        </NativeSelect>
                    </Field>

                    {!isCreate && (
                        <Field
                            id="recipe-dialog-active"
                            label="Status"
                            error={form.errors.active}
                        >
                            <NativeSelect
                                value={form.data.active ? '1' : '0'}
                                disabled={form.processing}
                                onChange={(event) =>
                                    form.setData(
                                        'active',
                                        event.target.value === '1',
                                    )
                                }
                            >
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </NativeSelect>
                        </Field>
                    )}

                    <InputError message={form.errors.return_to} />

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={form.processing}
                            onClick={closeDialog}
                        >
                            Cancel
                        </Button>

                        <Button type="submit" disabled={form.processing}>
                            {isCreate && (
                                <Plus className="size-4" aria-hidden="true" />
                            )}
                            {!isCreate && (
                                <Pencil className="size-4" aria-hidden="true" />
                            )}
                            {form.processing
                                ? isCreate
                                    ? 'Creating…'
                                    : 'Saving…'
                                : isCreate
                                  ? 'Create recipe'
                                  : 'Save recipe'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

/** Render one recipe row as a mobile record card. */
function RecipeCard({
    row,
    canManage,
    canViewCosts,
    timezone,
    onEdit,
}: {
    row: RecipeRow;
    canManage: boolean;
    canViewCosts: boolean;
    timezone: string;
    onEdit: () => void;
}) {
    return (
        <article className="rounded-xl border border-border bg-card p-4 shadow-sm">
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <h3 className="font-medium">{row.name}</h3>
                    <p className="mt-0.5 font-mono text-xs text-muted-foreground">
                        {row.code}
                    </p>
                </div>

                <TypeBadge type={row.type} />
            </div>

            <dl className="mt-4 grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                <div>
                    <dt className="text-xs text-muted-foreground">Versions</dt>
                    <dd className="mt-0.5">
                        <VersionCoverage row={row} />
                    </dd>
                </div>

                <div>
                    <dt className="text-xs text-muted-foreground">Activity</dt>
                    <dd className="mt-0.5">
                        <ActivityBadge active={row.active} />
                    </dd>
                </div>

                <div className="col-span-2">
                    <dt className="text-xs text-muted-foreground">Updated</dt>
                    <dd className="mt-0.5">
                        {formatUpdatedAt(row.updatedAt, timezone)}
                    </dd>
                </div>
            </dl>

            {(canViewCosts || canManage) && (
                <div className="mt-4 flex gap-2 border-t border-border pt-3">
                    {canViewCosts && (
                        <Button
                            variant="outline"
                            size="sm"
                            className="flex-1"
                            asChild
                        >
                            <Link href={RecipeCostController.show(row.id)}>
                                <Coins className="size-4" aria-hidden="true" />
                                Cost
                            </Link>
                        </Button>
                    )}

                    {canManage && (
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="flex-1"
                            onClick={onEdit}
                        >
                            <Pencil className="size-4" aria-hidden="true" />
                            Edit
                        </Button>
                    )}
                </div>
            )}
        </article>
    );
}

/** Render the server-authoritative Recipes operational index. */
export default function RecipesIndex({
    rows,
    pagination,
    summary,
    filters,
    canManage,
    canViewCosts,
    timezone,
}: Props) {
    const currentUrl = usePage().url;
    const [createOpen, setCreateOpen] = useState(false);
    const [editingRecipe, setEditingRecipe] = useState<RecipeRow | null>(null);

    const activeFilterLabels = [
        filters.search === null ? null : `Search: ${filters.search}`,
        filters.type === 'all' ? null : `Type: ${typeLabels[filters.type]}`,
        filters.activity === 'all'
            ? null
            : `Activity: ${filters.activity === 'active' ? 'Active' : 'Inactive'}`,
    ].filter((label): label is string => label !== null);

    const hasFilters = activeFilterLabels.length > 0;

    const sortHref = (sort: RecipeSort): string => {
        const params = new URLSearchParams();
        const defaultDirection: SortDirection =
            sort === 'updated_at' || sort === 'activity' ? 'desc' : 'asc';
        const direction =
            filters.sort === sort
                ? filters.direction === 'asc'
                    ? 'desc'
                    : 'asc'
                : defaultDirection;

        if (filters.search !== null) {
            params.set('search', filters.search);
        }

        params.set('type', filters.type);
        params.set('activity', filters.activity);
        params.set('sort', sort);
        params.set('direction', direction);
        params.set('per_page', filters.perPage.toString());

        return `${RecipeController.index().url}?${params.toString()}`;
    };

    return (
        <>
            <Head title="Recipes" />

            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <PageHeader
                    title="Recipes"
                    description="Manage stable recipe identities and inspect version coverage before location-aware costing."
                    actions={
                        canManage ? (
                            <Button
                                type="button"
                                onClick={() => setCreateOpen(true)}
                            >
                                <Plus className="size-4" aria-hidden="true" />
                                New recipe
                            </Button>
                        ) : undefined
                    }
                />

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    <DashboardMetricCard
                        title="Total recipes"
                        value={summary.totalCount.toLocaleString()}
                        description="Stable recipe identities"
                        icon={NotebookText}
                        tone="blue"
                    />
                    <DashboardMetricCard
                        title="Active"
                        value={summary.activeCount.toLocaleString()}
                        description="Active recipe identities"
                        icon={CheckCircle2}
                        tone="emerald"
                    />
                    <DashboardMetricCard
                        title="Menu items"
                        value={summary.menuItemCount.toLocaleString()}
                        description="Menu-item recipe identities"
                        icon={PackageCheck}
                        tone="teal"
                    />
                    <DashboardMetricCard
                        title="Prepared items"
                        value={summary.preparedItemCount.toLocaleString()}
                        description="Reusable prepared recipes"
                        icon={ClipboardList}
                        tone="violet"
                    />
                    <DashboardMetricCard
                        title="Batches"
                        value={summary.batchCount.toLocaleString()}
                        description="Batch recipe identities"
                        icon={Boxes}
                        tone="amber"
                    />
                </div>

                <Form action={RecipeController.index().url} method="get">
                    {({ errors, processing }) => (
                        <FilterToolbar className="overflow-hidden p-0 shadow-sm">
                            <div className="grid gap-4 p-4 sm:grid-cols-2 xl:grid-cols-[minmax(240px,1.5fr)_0.85fr_0.85fr_0.65fr_auto]">
                                <input
                                    type="hidden"
                                    name="sort"
                                    value={filters.sort}
                                />
                                <input
                                    type="hidden"
                                    name="direction"
                                    value={filters.direction}
                                />

                                <Field
                                    id="search"
                                    label="Search"
                                    error={errors.search}
                                >
                                    <div className="relative">
                                        <Search
                                            className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                            aria-hidden="true"
                                        />
                                        <Input
                                            type="search"
                                            name="search"
                                            defaultValue={filters.search ?? ''}
                                            placeholder="Name or recipe code"
                                            className="pl-9"
                                            autoComplete="off"
                                        />
                                    </div>
                                </Field>

                                <Field
                                    id="type"
                                    label="Type"
                                    error={errors.type}
                                >
                                    <NativeSelect
                                        name="type"
                                        defaultValue={filters.type}
                                    >
                                        <option value="all">All types</option>
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
                                    id="activity"
                                    label="Activity"
                                    error={errors.activity}
                                >
                                    <NativeSelect
                                        name="activity"
                                        defaultValue={filters.activity}
                                    >
                                        <option value="all">
                                            All activity
                                        </option>
                                        <option value="active">Active</option>
                                        <option value="inactive">
                                            Inactive
                                        </option>
                                    </NativeSelect>
                                </Field>

                                <Field
                                    id="per_page"
                                    label="Rows"
                                    error={errors.per_page}
                                >
                                    <NativeSelect
                                        name="per_page"
                                        defaultValue={filters.perPage}
                                    >
                                        <option value="10">10</option>
                                        <option value="25">25</option>
                                        <option value="50">50</option>
                                    </NativeSelect>
                                </Field>

                                <div className="flex items-end gap-2 sm:col-span-2 xl:col-span-1">
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        className="flex-1 xl:flex-none"
                                    >
                                        <Filter
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        {processing ? 'Applying…' : 'Apply'}
                                    </Button>

                                    <Button
                                        variant="outline"
                                        className="flex-1 xl:flex-none"
                                        asChild
                                    >
                                        <Link href={RecipeController.index()}>
                                            <RotateCcw
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                            Reset
                                        </Link>
                                    </Button>
                                </div>
                            </div>

                            <div
                                className="flex flex-wrap items-center gap-2 border-t border-border px-4 py-3 text-sm text-muted-foreground"
                                aria-live="polite"
                            >
                                <Filter className="size-4" aria-hidden="true" />

                                {activeFilterLabels.length === 0 ? (
                                    <Badge variant="outline">
                                        Active filters: None
                                    </Badge>
                                ) : (
                                    activeFilterLabels.map((label) => (
                                        <Badge key={label} variant="outline">
                                            {label}
                                        </Badge>
                                    ))
                                )}

                                {hasFilters && (
                                    <Link
                                        href={RecipeController.index()}
                                        className="font-medium text-foreground underline-offset-4 hover:underline focus-visible:rounded-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                    >
                                        Clear all
                                    </Link>
                                )}
                            </div>
                        </FilterToolbar>
                    )}
                </Form>

                <section
                    className="grid gap-3 md:hidden"
                    aria-labelledby="recipes-cards-title"
                >
                    <h2 id="recipes-cards-title" className="sr-only">
                        Recipes
                    </h2>

                    {rows.length === 0 ? (
                        <div className="rounded-xl border border-border bg-card p-6 shadow-sm">
                            <EmptyState
                                icon={NotebookText}
                                title={
                                    summary.totalCount === 0
                                        ? 'No recipes have been created.'
                                        : 'No recipes match the current filters.'
                                }
                                description={
                                    summary.totalCount === 0
                                        ? 'Create a recipe to establish its stable identity before adding versions.'
                                        : 'Adjust or clear the filters to see other recipes.'
                                }
                            />
                        </div>
                    ) : (
                        rows.map((row) => (
                            <RecipeCard
                                key={row.id}
                                row={row}
                                canManage={canManage}
                                canViewCosts={canViewCosts}
                                timezone={timezone}
                                onEdit={() => setEditingRecipe(row)}
                            />
                        ))
                    )}
                </section>

                <section className="hidden min-w-0 overflow-hidden rounded-xl border border-border bg-card shadow-sm md:block">
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[820px] text-sm">
                            <thead className="border-b bg-muted/30 text-left text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                <tr>
                                    <th className="px-4 py-3">
                                        <Link
                                            href={sortHref('name')}
                                            className="inline-flex items-center gap-1.5 hover:text-foreground focus-visible:rounded-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                        >
                                            Recipe
                                            <SortIndicator
                                                active={filters.sort === 'name'}
                                                direction={filters.direction}
                                            />
                                        </Link>
                                    </th>
                                    <th className="px-4 py-3">
                                        <Link
                                            href={sortHref('type')}
                                            className="inline-flex items-center gap-1.5 hover:text-foreground focus-visible:rounded-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                        >
                                            Type
                                            <SortIndicator
                                                active={filters.sort === 'type'}
                                                direction={filters.direction}
                                            />
                                        </Link>
                                    </th>
                                    <th className="px-4 py-3">Versions</th>
                                    <th className="px-4 py-3">
                                        <Link
                                            href={sortHref('activity')}
                                            className="inline-flex items-center gap-1.5 hover:text-foreground focus-visible:rounded-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                        >
                                            Activity
                                            <SortIndicator
                                                active={
                                                    filters.sort === 'activity'
                                                }
                                                direction={filters.direction}
                                            />
                                        </Link>
                                    </th>
                                    <th className="px-4 py-3">
                                        <Link
                                            href={sortHref('updated_at')}
                                            className="inline-flex items-center gap-1.5 hover:text-foreground focus-visible:rounded-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                        >
                                            Updated
                                            <SortIndicator
                                                active={
                                                    filters.sort ===
                                                    'updated_at'
                                                }
                                                direction={filters.direction}
                                            />
                                        </Link>
                                    </th>
                                    <th className="px-4 py-3 text-right">
                                        Actions
                                    </th>
                                </tr>
                            </thead>

                            <tbody>
                                {rows.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="px-4 py-12">
                                            <EmptyState
                                                icon={NotebookText}
                                                title={
                                                    summary.totalCount === 0
                                                        ? 'No recipes have been created.'
                                                        : 'No recipes match the current filters.'
                                                }
                                                description={
                                                    summary.totalCount === 0
                                                        ? 'Create a recipe to establish its stable identity before adding versions.'
                                                        : 'Adjust or clear the filters to see other recipes.'
                                                }
                                            />
                                        </td>
                                    </tr>
                                ) : (
                                    rows.map((row) => (
                                        <tr
                                            key={row.id}
                                            className="border-b border-border last:border-b-0 hover:bg-muted/20"
                                        >
                                            <td className="px-4 py-3.5">
                                                <div className="font-medium">
                                                    {row.name}
                                                </div>
                                                <div className="mt-0.5 font-mono text-xs text-muted-foreground">
                                                    {row.code}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3.5">
                                                <TypeBadge type={row.type} />
                                            </td>
                                            <td className="px-4 py-3.5">
                                                <VersionCoverage row={row} />
                                            </td>
                                            <td className="px-4 py-3.5">
                                                <ActivityBadge
                                                    active={row.active}
                                                />
                                            </td>
                                            <td className="px-4 py-3.5 text-muted-foreground">
                                                {row.updatedAt === null ? (
                                                    'Not recorded'
                                                ) : (
                                                    <time
                                                        dateTime={row.updatedAt}
                                                    >
                                                        {formatUpdatedAt(
                                                            row.updatedAt,
                                                            timezone,
                                                        )}
                                                    </time>
                                                )}
                                            </td>
                                            <td className="px-4 py-3.5">
                                                <div className="flex justify-end gap-1.5">
                                                    {canViewCosts && (
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            asChild
                                                        >
                                                            <Link
                                                                href={RecipeCostController.show(
                                                                    row.id,
                                                                )}
                                                            >
                                                                <Coins
                                                                    className="size-4"
                                                                    aria-hidden="true"
                                                                />
                                                                Cost
                                                            </Link>
                                                        </Button>
                                                    )}

                                                    {canManage && (
                                                        <Button
                                                            type="button"
                                                            variant="outline"
                                                            size="sm"
                                                            onClick={() =>
                                                                setEditingRecipe(
                                                                    row,
                                                                )
                                                            }
                                                        >
                                                            <Pencil
                                                                className="size-4"
                                                                aria-hidden="true"
                                                            />
                                                            Edit
                                                        </Button>
                                                    )}

                                                    {!canViewCosts &&
                                                        !canManage && (
                                                            <span className="text-muted-foreground">
                                                                No actions
                                                            </span>
                                                        )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    <PaginationControls
                        currentPage={pagination.currentPage}
                        lastPage={pagination.lastPage}
                        from={pagination.from}
                        to={pagination.to}
                        total={pagination.total}
                        previousPageUrl={pagination.previousPageUrl}
                        nextPageUrl={pagination.nextPageUrl}
                        itemLabel="recipes"
                    />

                    <div className="border-t border-border bg-muted/20 px-4 py-3 text-xs text-muted-foreground">
                        Yield belongs to a specific recipe version. Current cost
                        remains location-specific and is opened through the
                        permitted Cost action instead of being estimated on this
                        index.
                    </div>
                </section>
            </div>

            {createOpen && (
                <RecipeIdentityDialog
                    recipe={null}
                    returnTo={currentUrl}
                    onClose={() => setCreateOpen(false)}
                />
            )}

            {editingRecipe !== null && (
                <RecipeIdentityDialog
                    key={editingRecipe.id}
                    recipe={editingRecipe}
                    returnTo={currentUrl}
                    onClose={() => setEditingRecipe(null)}
                />
            )}
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
