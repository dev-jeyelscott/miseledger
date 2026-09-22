import { Head, Link } from '@inertiajs/react';
import { FileText, Plus } from 'lucide-react';

import PlatformContentController from '@/actions/App/Http/Controllers/Platform/PlatformContentController';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';

type ContentPageSummary = {
    id: number;
    kind: 'marketing' | 'legal';
    key: string;
    slug: string;
    title: string;
    hasPublishedRevision: boolean;
    hasDraft: boolean;
    latestRevisionNumber: number | null;
    updatedAt: string | null;
};

type Props = {
    pages: ContentPageSummary[];
};

function PageStatusBadge({ page }: { page: ContentPageSummary }) {
    if (page.hasPublishedRevision) {
        return <StatusBadge label="Published" variant="success" />;
    }

    if (page.hasDraft) {
        return <StatusBadge label="Draft only" variant="warning" />;
    }

    return <StatusBadge label="No revisions" variant="neutral" />;
}

/** List every marketing/legal content page with its lifecycle summary. */
export default function PlatformContentIndex({ pages }: Props) {
    return (
        <>
            <Head title="Content" />

            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <PageHeader
                    title="Content"
                    description="Versioned marketing and legal Markdown. Release Notes and User Guide are separate, code-owned modules and are not managed here."
                    actions={
                        <Button asChild>
                            <Link href={PlatformContentController.create()}>
                                <Plus className="size-4" aria-hidden="true" />
                                New content page
                            </Link>
                        </Button>
                    }
                />

                <section
                    aria-label="Content pages"
                    className="overflow-hidden rounded-xl border border-border bg-card"
                >
                    {pages.length === 0 ? (
                        <EmptyState
                            className="px-4 py-12"
                            icon={FileText}
                            title="No content pages yet"
                            description="Create a marketing or legal draft to get started."
                        />
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[720px] text-sm">
                                <caption className="sr-only">
                                    Marketing and legal content pages
                                </caption>
                                <thead className="border-b border-border bg-muted/30 text-left text-xs text-muted-foreground">
                                    <tr>
                                        <th
                                            scope="col"
                                            className="px-4 py-3 font-medium"
                                        >
                                            Page
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-4 py-3 font-medium"
                                        >
                                            Kind
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-4 py-3 font-medium"
                                        >
                                            Status
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-4 py-3 font-medium"
                                        >
                                            Latest revision
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-4 py-3 font-medium"
                                        >
                                            Updated
                                        </th>
                                    </tr>
                                </thead>

                                <tbody className="divide-y divide-border">
                                    {pages.map((page) => (
                                        <tr
                                            key={page.id}
                                            className="hover:bg-muted/30"
                                        >
                                            <td className="px-4 py-3">
                                                <Link
                                                    href={PlatformContentController.show(
                                                        page.id,
                                                    )}
                                                    className="rounded-sm font-medium hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                                >
                                                    {page.title}
                                                </Link>
                                                <p className="mt-0.5 font-mono text-xs text-muted-foreground">
                                                    /{page.slug}
                                                </p>
                                            </td>

                                            <td className="px-4 py-3 capitalize">
                                                {page.kind}
                                            </td>

                                            <td className="px-4 py-3">
                                                <PageStatusBadge page={page} />
                                            </td>

                                            <td className="px-4 py-3 tabular-nums">
                                                {page.latestRevisionNumber ??
                                                    'None'}
                                            </td>

                                            <td className="px-4 py-3 text-muted-foreground">
                                                {page.updatedAt
                                                    ? new Date(
                                                          page.updatedAt,
                                                      ).toLocaleString()
                                                    : '—'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>
            </div>
        </>
    );
}
