import { Head, Link } from '@inertiajs/react';
import {
    ArrowLeftRight,
    Bot,
    Boxes,
    ChevronRight,
    ClipboardCheck,
    CreditCard,
    History,
    LayoutGrid,
    NotebookText,
    PackageCheck,
    PackagePlus,
    Search,
    Settings,
    Trash2,
    Truck,
    Users,
    X,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useMemo, useState } from 'react';
import type { ReactNode } from 'react';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { index, show } from '@/routes/user-guide';
import { popularGuideTasks } from '@/user-guide/popular-tasks';
import { searchGuideTopics } from '@/user-guide/search';
import type { GuideModuleSlug } from '@/user-guide/types';
import { guideModules } from './content';

const guideModuleIcons = {
    'getting-started': PackagePlus,
    dashboard: LayoutGrid,
    'ai-assistant': Bot,
    inventory: Boxes,
    'stock-counts': ClipboardCheck,
    waste: Trash2,
    'stock-transfers': ArrowLeftRight,
    purchasing: Truck,
    recipes: NotebookText,
    reports: History,
    organization: Users,
    billing: CreditCard,
    settings: Settings,
} satisfies Record<GuideModuleSlug, LucideIcon>;

const popularTaskIcons = {
    'Receive Stock': PackageCheck,
    'Perform Stock Count': ClipboardCheck,
    'Record Waste': Trash2,
    'Transfer Stock': ArrowLeftRight,
} satisfies Record<(typeof popularGuideTasks)[number]['label'], LucideIcon>;

/** Render the search-first User Guide landing page while preserving existing guide search and navigation semantics. */
export default function UserGuideIndex() {
    const [query, setQuery] = useState('');
    const results = useMemo(
        () => searchGuideTopics(guideModules, query),
        [query],
    );
    const isSearching = query.trim() !== '';

    return (
        <>
            <Head title="User Guide" />

            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <PageHeader
                    title="User Guide"
                    description="Find clear instructions for using MiseLedger."
                />

                <section
                    aria-labelledby="guide-search-heading"
                    className="rounded-xl border border-border bg-card p-5 shadow-sm sm:p-6 lg:p-8"
                >
                    <div className="max-w-4xl">
                        <h2
                            id="guide-search-heading"
                            className="text-lg font-semibold tracking-tight"
                        >
                            Find answers fast
                        </h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Search by task, feature, field, or issue before
                            browsing the full guide.
                        </p>

                        <div className="mt-6">
                            <label
                                htmlFor="guide-search"
                                className="text-sm font-medium"
                            >
                                Search the User Guide
                            </label>

                            <div className="mt-2 flex flex-col gap-2 sm:flex-row">
                                <div className="relative flex-1">
                                    <Search
                                        className="pointer-events-none absolute top-1/2 left-3.5 size-5 -translate-y-1/2 text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                    <Input
                                        id="guide-search"
                                        value={query}
                                        onChange={(event) =>
                                            setQuery(event.target.value)
                                        }
                                        placeholder="Search topics, tasks, or fields"
                                        className="h-12 pl-11 text-base md:text-base"
                                    />
                                </div>

                                {isSearching ? (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => setQuery('')}
                                        className="h-12 w-full sm:w-auto sm:shrink-0"
                                    >
                                        <X aria-hidden="true" />
                                        Clear
                                    </Button>
                                ) : null}
                            </div>
                        </div>

                        <p className="mt-3 text-sm leading-6 text-muted-foreground">
                            Searches modules, tutorials, fields, and
                            troubleshooting across the full guide. Some features
                            depend on your plan or access level.
                        </p>
                    </div>
                </section>

                {isSearching ? (
                    <section
                        aria-labelledby="guide-search-results-heading"
                        className="space-y-3"
                    >
                        <div className="flex flex-wrap items-baseline justify-between gap-2">
                            <h2
                                id="guide-search-results-heading"
                                className="text-lg font-semibold tracking-tight"
                            >
                                Search results
                            </h2>
                            <p
                                className="text-sm text-muted-foreground"
                                aria-live="polite"
                            >
                                {results.length}{' '}
                                {results.length === 1 ? 'result' : 'results'}{' '}
                                found
                            </p>
                        </div>

                        {results.length > 0 ? (
                            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                {results.map((result) => (
                                    <Link
                                        key={`${result.module.slug}-${result.anchor ?? result.topic}`}
                                        href={`${show(result.module.slug).url}${result.anchor ? `#${result.anchor}` : ''}`}
                                        prefetch
                                        className="flex min-h-11 cursor-pointer items-center gap-4 rounded-xl border border-border bg-card p-4 hover:bg-muted/40 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background focus-visible:outline-none"
                                    >
                                        <div className="min-w-0 flex-1">
                                            <p className="text-xs font-medium text-muted-foreground">
                                                {result.module.title}
                                            </p>
                                            <h3 className="mt-1 text-sm font-semibold">
                                                {result.topic}
                                            </h3>
                                        </div>

                                        <ChevronRight
                                            className="size-4 shrink-0 text-muted-foreground"
                                            aria-hidden="true"
                                        />
                                    </Link>
                                ))}
                            </div>
                        ) : (
                            <div className="rounded-xl border border-border bg-card p-5">
                                <p className="text-sm font-semibold">
                                    No matches found
                                </p>
                                <p className="mt-1 text-sm leading-6 text-muted-foreground">
                                    Try a broader term or clear the search to
                                    browse all guide modules.
                                </p>
                            </div>
                        )}
                    </section>
                ) : (
                    <>
                        <section
                            aria-labelledby="popular-tasks-heading"
                            className="space-y-3"
                        >
                            <div>
                                <h2
                                    id="popular-tasks-heading"
                                    className="text-lg font-semibold tracking-tight"
                                >
                                    Popular Tasks
                                </h2>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Jump directly to common inventory workflows.
                                </p>
                            </div>

                            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                                {popularGuideTasks.map((task) => {
                                    const TaskIcon =
                                        popularTaskIcons[task.label];

                                    return (
                                        <Link
                                            key={`${task.module}-${task.tutorialId}`}
                                            href={`${show(task.module).url}#${task.tutorialId}`}
                                            prefetch
                                            className="flex min-h-16 cursor-pointer items-center gap-3 rounded-xl border border-border bg-card p-4 hover:bg-muted/40 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background focus-visible:outline-none"
                                        >
                                            <span className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground">
                                                <TaskIcon
                                                    className="size-5"
                                                    aria-hidden="true"
                                                />
                                            </span>

                                            <h3 className="min-w-0 flex-1 text-sm font-semibold">
                                                {task.label}
                                            </h3>

                                            <ChevronRight
                                                className="size-4 shrink-0 text-muted-foreground"
                                                aria-hidden="true"
                                            />
                                        </Link>
                                    );
                                })}
                            </div>
                        </section>

                        <section
                            aria-labelledby="browse-modules-heading"
                            className="space-y-3"
                        >
                            <div>
                                <h2
                                    id="browse-modules-heading"
                                    className="text-lg font-semibold tracking-tight"
                                >
                                    Browse by Module
                                </h2>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Explore every current MiseLedger guide area.
                                </p>
                            </div>

                            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                                {guideModules.map((module) => {
                                    const ModuleIcon =
                                        guideModuleIcons[module.slug];

                                    return (
                                        <Link
                                            key={module.slug}
                                            href={show(module.slug)}
                                            prefetch
                                            className="flex h-full min-w-0 cursor-pointer items-start gap-4 rounded-xl border border-border bg-card p-5 hover:bg-muted/40 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background focus-visible:outline-none"
                                        >
                                            <span className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground">
                                                <ModuleIcon
                                                    className="size-5"
                                                    aria-hidden="true"
                                                />
                                            </span>

                                            <div className="min-w-0 flex-1">
                                                <h3 className="font-semibold">
                                                    {module.title}
                                                </h3>
                                                <p className="mt-1 text-sm leading-6 text-muted-foreground">
                                                    {module.description}
                                                </p>
                                            </div>

                                            <ChevronRight
                                                className="mt-1 size-4 shrink-0 text-muted-foreground"
                                                aria-hidden="true"
                                            />
                                        </Link>
                                    );
                                })}
                            </div>
                        </section>
                    </>
                )}
            </div>
        </>
    );
}

/** Preserve the authenticated application shell and canonical User Guide breadcrumb. */
UserGuideIndex.layout = (page: ReactNode) => (
    <AppLayout
        breadcrumbs={[
            {
                title: 'User Guide',
                href: index(),
            },
        ]}
    >
        {page}
    </AppLayout>
);
