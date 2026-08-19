import { Form, Head, Link, useForm, usePage } from '@inertiajs/react';
import {
    Ban,
    Boxes,
    CheckCircle2,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
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
import InputError from '@/components/input-error';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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

const selectClassName =
    'h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs outline-none transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive aria-invalid:ring-[3px] aria-invalid:ring-destructive/20 disabled:cursor-not-allowed disabled:opacity-50';

/** Format persisted timestamps for compact operational scanning. */
function formatUpdatedAt(value: string | null): string {
    if (value === null) {
        return '—';
    }

    return new Intl.DateTimeFormat('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    }).format(new Date(value));
}

/** Render recipe identity type without implying version state. */
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

/** Render recipe master activity with text, icon, and color. */
function ActivityBadge({ active }: { active: boolean }) {
    if (!active) {
        return (
            <Badge variant="secondary">
                <Ban aria-hidden="true" />
                Inactive
            </Badge>
        );
    }

    return (
        <Badge
            variant="outline"
            className="border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300"
        >
            <CheckCircle2 aria-hidden="true" />
            Active
        </Badge>
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
                    className="space-y-5"
                    aria-busy={form.processing}
                    onSubmit={(event) => {
                        event.preventDefault();
                        submit();
                    }}
                >
                    <div className="grid gap-2">
                        <Label htmlFor="recipe-dialog-code">Code</Label>
                        <Input
                            id="recipe-dialog-code"
                            value={form.data.code}
                            required
                            autoFocus
                            autoComplete="off"
                            placeholder={isCreate ? 'RCP-00001' : undefined}
                            disabled={form.processing}
                            aria-invalid={form.errors.code ? true : undefined}
                            onChange={(event) =>
                                form.setData('code', event.target.value)
                            }
                        />
                        <InputError message={form.errors.code} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="recipe-dialog-name">Name</Label>
                        <Input
                            id="recipe-dialog-name"
                            value={form.data.name}
                            required
                            autoComplete="off"
                            placeholder={isCreate ? 'Cheeseburger' : undefined}
                            disabled={form.processing}
                            aria-invalid={form.errors.name ? true : undefined}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                        />
                        <InputError message={form.errors.name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="recipe-dialog-type">Type</Label>
                        <select
                            id="recipe-dialog-type"
                            value={form.data.type}
                            className={selectClassName}
                            disabled={form.processing}
                            aria-invalid={form.errors.type ? true : undefined}
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
                        </select>
                        <InputError message={form.errors.type} />
                    </div>

                    {!isCreate && (
                        <div className="grid gap-2">
                            <Label htmlFor="recipe-dialog-active">Status</Label>
                            <select
                                id="recipe-dialog-active"
                                value={form.data.active ? '1' : '0'}
                                className={selectClassName}
                                disabled={form.processing}
                                aria-invalid={
                                    form.errors.active ? true : undefined
                                }
                                onChange={(event) =>
                                    form.setData(
                                        'active',
                                        event.target.value === '1',
                                    )
                                }
                            >
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                            <InputError message={form.errors.active} />
                        </div>
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

/** Render the server-authoritative Recipes operational index. */
export default function RecipesIndex({
    rows,
    pagination,
    summary,
    filters,
    canManage,
    canViewCosts,
}: Props) {
    const currentUrl = usePage().url;
    const [createOpen, setCreateOpen] = useState(false);
    const [editingRecipe, setEditingRecipe] = useState<RecipeRow | null>(null);

    const hasFilters =
        filters.search !== null ||
        filters.type !== 'all' ||
        filters.activity !== 'all';

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
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Recipes
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Manage stable recipe identities and inspect version
                            coverage before location-aware costing.
                        </p>
                    </div>

                    {canManage && (
                        <Button
                            type="button"
                            onClick={() => setCreateOpen(true)}
                        >
                            <Plus className="size-4" aria-hidden="true" />
                            New recipe
                        </Button>
                    )}
                </div>

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

                <section className="min-w-0 overflow-hidden rounded-xl border border-sidebar-border/70 bg-card shadow-sm dark:border-sidebar-border">
                    <Form action={RecipeController.index().url} method="get">
                        {({ errors, processing }) => (
                            <div className="grid gap-4 border-b border-sidebar-border/70 p-4 sm:grid-cols-2 xl:grid-cols-[minmax(240px,1.5fr)_0.85fr_0.85fr_0.65fr_auto] dark:border-sidebar-border">
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

                                <div className="grid gap-2">
                                    <Label htmlFor="search">Search</Label>
                                    <div className="relative">
                                        <Search
                                            className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                            aria-hidden="true"
                                        />
                                        <Input
                                            id="search"
                                            type="search"
                                            name="search"
                                            defaultValue={filters.search ?? ''}
                                            placeholder="Name or recipe code"
                                            className="pl-9"
                                            autoComplete="off"
                                            aria-invalid={
                                                errors.search ? true : undefined
                                            }
                                        />
                                    </div>
                                    <InputError message={errors.search} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="type">Type</Label>
                                    <select
                                        id="type"
                                        name="type"
                                        defaultValue={filters.type}
                                        className={selectClassName}
                                        aria-invalid={
                                            errors.type ? true : undefined
                                        }
                                    >
                                        <option value="all">All types</option>
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
                                    <Label htmlFor="activity">Activity</Label>
                                    <select
                                        id="activity"
                                        name="activity"
                                        defaultValue={filters.activity}
                                        className={selectClassName}
                                        aria-invalid={
                                            errors.activity ? true : undefined
                                        }
                                    >
                                        <option value="all">
                                            All activity
                                        </option>
                                        <option value="active">Active</option>
                                        <option value="inactive">
                                            Inactive
                                        </option>
                                    </select>
                                    <InputError message={errors.activity} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="per_page">Rows</Label>
                                    <select
                                        id="per_page"
                                        name="per_page"
                                        defaultValue={filters.perPage}
                                        className={selectClassName}
                                        aria-invalid={
                                            errors.per_page ? true : undefined
                                        }
                                    >
                                        <option value="10">10</option>
                                        <option value="25">25</option>
                                        <option value="50">50</option>
                                    </select>
                                    <InputError message={errors.per_page} />
                                </div>

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

                                    {hasFilters && (
                                        <Button
                                            variant="outline"
                                            className="flex-1 xl:flex-none"
                                            asChild
                                        >
                                            <Link
                                                href={RecipeController.index()}
                                            >
                                                <RotateCcw
                                                    className="size-4"
                                                    aria-hidden="true"
                                                />
                                                Reset
                                            </Link>
                                        </Button>
                                    )}
                                </div>
                            </div>
                        )}
                    </Form>

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
                                        <td
                                            colSpan={6}
                                            className="px-4 py-12 text-center text-muted-foreground"
                                        >
                                            {summary.totalCount === 0
                                                ? 'No recipes have been created.'
                                                : 'No recipes match the current filters.'}
                                        </td>
                                    </tr>
                                ) : (
                                    rows.map((row) => (
                                        <tr
                                            key={row.id}
                                            className="border-b border-sidebar-border/60 last:border-b-0 hover:bg-muted/20 dark:border-sidebar-border"
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
                                                    '—'
                                                ) : (
                                                    <time
                                                        dateTime={row.updatedAt}
                                                    >
                                                        {formatUpdatedAt(
                                                            row.updatedAt,
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
                                                                —
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

                    <div className="flex flex-col gap-3 border-t border-sidebar-border/70 px-4 py-3 sm:flex-row sm:items-center sm:justify-between dark:border-sidebar-border">
                        <p className="text-xs text-muted-foreground">
                            {pagination.total === 0
                                ? '0 recipes'
                                : `Showing ${pagination.from ?? 0} to ${pagination.to ?? 0} of ${pagination.total.toLocaleString()} recipes`}
                        </p>

                        <div className="flex items-center gap-2">
                            <span className="text-xs text-muted-foreground">
                                Page {pagination.currentPage} of{' '}
                                {pagination.lastPage}
                            </span>

                            <Button
                                variant="outline"
                                size="icon"
                                disabled={pagination.previousPageUrl === null}
                                asChild={pagination.previousPageUrl !== null}
                            >
                                {pagination.previousPageUrl === null ? (
                                    <span>
                                        <ChevronLeft
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        <span className="sr-only">
                                            Previous page
                                        </span>
                                    </span>
                                ) : (
                                    <Link
                                        href={pagination.previousPageUrl}
                                        aria-label="Previous page"
                                    >
                                        <ChevronLeft
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                    </Link>
                                )}
                            </Button>

                            <Button
                                variant="outline"
                                size="icon"
                                disabled={pagination.nextPageUrl === null}
                                asChild={pagination.nextPageUrl !== null}
                            >
                                {pagination.nextPageUrl === null ? (
                                    <span>
                                        <ChevronRight
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        <span className="sr-only">
                                            Next page
                                        </span>
                                    </span>
                                ) : (
                                    <Link
                                        href={pagination.nextPageUrl}
                                        aria-label="Next page"
                                    >
                                        <ChevronRight
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                    </Link>
                                )}
                            </Button>
                        </div>
                    </div>

                    <div className="border-t border-sidebar-border/70 bg-muted/20 px-4 py-3 text-xs text-muted-foreground dark:border-sidebar-border">
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
