import { Head, Link } from '@inertiajs/react';
import { ArrowRight, BookOpen, Search, X } from 'lucide-react';
import { useMemo, useState } from 'react';
import type { ReactNode } from 'react';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { index, show } from '@/routes/user-guide';
import { searchGuideTopics } from '@/user-guide/search';
import { guideModules } from './content';

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
                    aria-label="Search the guide"
                    className="rounded-xl border border-border bg-card p-5 shadow-sm"
                >
                    <label
                        htmlFor="guide-search"
                        className="text-sm font-semibold"
                    >
                        Search the guide
                    </label>
                    <div className="mt-3 flex gap-2">
                        <div className="relative flex-1">
                            <Search
                                className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <Input
                                id="guide-search"
                                value={query}
                                onChange={(event) =>
                                    setQuery(event.target.value)
                                }
                                placeholder="Search topics, tasks, or fields"
                                className="pl-9"
                            />
                        </div>
                        {isSearching ? (
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setQuery('')}
                            >
                                <X aria-hidden="true" />
                                Clear
                            </Button>
                        ) : null}
                    </div>
                    <p className="mt-3 text-sm text-muted-foreground">
                        The guide covers the whole product. Some features depend
                        on your plan or access level.
                    </p>
                </section>

                {isSearching ? (
                    <section aria-label="Search results">
                        <p
                            className="mb-3 text-sm text-muted-foreground"
                            aria-live="polite"
                        >
                            {results.length}{' '}
                            {results.length === 1 ? 'topic' : 'topics'} found
                        </p>
                        {results.length > 0 ? (
                            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                {results.map((result) => (
                                    <Link
                                        key={`${result.module.slug}-${result.anchor ?? result.topic}`}
                                        href={`${show(result.module.slug).url}${result.anchor ? `#${result.anchor}` : ''}`}
                                        prefetch
                                        className="rounded-xl border border-border bg-card p-4 shadow-sm transition-colors hover:bg-muted/40 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                    >
                                        <p className="text-xs font-medium text-muted-foreground">
                                            {result.module.title}
                                        </p>
                                        <h2 className="mt-1 text-sm font-semibold">
                                            {result.topic}
                                        </h2>
                                    </Link>
                                ))}
                            </div>
                        ) : (
                            <p className="rounded-xl border border-border bg-card p-5 text-sm text-muted-foreground">
                                No guide topics match your search.
                            </p>
                        )}
                    </section>
                ) : (
                    <section aria-label="Guide topics">
                        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                            {guideModules.map((module) => (
                                <Link
                                    key={module.slug}
                                    href={show(module.slug)}
                                    prefetch
                                    className="group rounded-xl border border-border bg-card p-5 shadow-sm transition-colors hover:bg-muted/40 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                >
                                    <BookOpen
                                        className="size-5 text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                    <h2 className="mt-4 font-semibold">
                                        {module.title}
                                    </h2>
                                    <p className="mt-1 text-sm leading-6 text-muted-foreground">
                                        {module.description}
                                    </p>
                                    <span className="mt-4 inline-flex items-center gap-1 text-sm font-medium text-primary">
                                        Read guide
                                        <ArrowRight
                                            className="size-4 transition-transform group-hover:translate-x-0.5"
                                            aria-hidden="true"
                                        />
                                    </span>
                                </Link>
                            ))}
                        </div>
                    </section>
                )}
            </div>
        </>
    );
}

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
