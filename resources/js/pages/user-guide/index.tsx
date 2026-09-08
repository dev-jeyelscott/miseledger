import { Head, Link } from '@inertiajs/react';
import { ArrowRight, BookOpen } from 'lucide-react';
import type { ReactNode } from 'react';
import { PageHeader } from '@/components/page-header';
import AppLayout from '@/layouts/app-layout';
import { index, show } from '@/routes/user-guide';
import { guideModules } from './content';

export default function UserGuideIndex() {
    return (
        <>
            <Head title="User Guide" />

            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <PageHeader
                    title="User Guide"
                    description="Learn how to use MiseLedger features."
                />

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
