import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import { entries } from '@/release-notes';
import type { ReleaseNoteType } from '@/release-notes/types';
import { index } from '@/routes/release-notes';

const typeLabels: Record<ReleaseNoteType, string> = {
    new: 'New',
    improved: 'Improved',
    fixed: 'Fixed',
};

const typeVariants: Record<
    ReleaseNoteType,
    'default' | 'secondary' | 'outline'
> = {
    new: 'default',
    improved: 'secondary',
    fixed: 'outline',
};

export default function ReleaseNotesIndex() {
    return (
        <>
            <Head title="Release Notes" />

            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <PageHeader
                    title="Release Notes"
                    description="Stay updated with the latest changes and improvements to MiseLedger."
                />

                {entries.length > 0 ? (
                    <section className="space-y-4">
                        {entries.map((entry) => (
                            <article
                                key={entry.id}
                                className="rounded-xl border border-border bg-card p-5 shadow-sm sm:p-6"
                            >
                                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div className="min-w-0 flex-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <h2 className="text-lg font-semibold tracking-tight">
                                                {entry.title}
                                            </h2>
                                            <Badge
                                                variant={
                                                    typeVariants[entry.type]
                                                }
                                            >
                                                {typeLabels[entry.type]}
                                            </Badge>
                                        </div>
                                        <time
                                            dateTime={entry.publishedOn}
                                            className="mt-1 block text-sm text-muted-foreground"
                                        >
                                            {new Date(
                                                entry.publishedOn,
                                            ).toLocaleDateString('en-US', {
                                                year: 'numeric',
                                                month: 'long',
                                                day: 'numeric',
                                            })}
                                        </time>
                                    </div>
                                </div>

                                <p className="mt-3 text-sm leading-6">
                                    {entry.summary}
                                </p>

                                {entry.details && (
                                    <p className="mt-3 text-sm leading-6 text-muted-foreground">
                                        {entry.details}
                                    </p>
                                )}
                            </article>
                        ))}
                    </section>
                ) : (
                    <div className="rounded-xl border border-border bg-card p-5 sm:p-6">
                        <p className="text-sm font-semibold">
                            No release notes yet
                        </p>
                        <p className="mt-1 text-sm leading-6 text-muted-foreground">
                            Check back soon for updates about MiseLedger.
                        </p>
                    </div>
                )}
            </div>
        </>
    );
}

ReleaseNotesIndex.layout = (page: ReactNode) => (
    <AppLayout
        breadcrumbs={[
            {
                title: 'Release Notes',
                href: index(),
            },
        ]}
    >
        {page}
    </AppLayout>
);
