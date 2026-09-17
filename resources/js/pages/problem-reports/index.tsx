import { Head, Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import type { ReactNode } from 'react';

import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { PaginationControls } from '@/components/pagination-controls';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

import { getProblemReportStatusPresentation } from './status';

interface Attachment {
    id: number;
    original_name: string;
}

interface Report {
    id: number;
    reference: string;
    title: string | null;
    description: string;
    status: string;
    attachments: Attachment[];
    created_at: string;
    updated_at: string;
    organization_name_snapshot: string | null;
}

interface PaginationData {
    data: Report[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
}

interface Props {
    reports: PaginationData;
}

/** Renders the authenticated user's submitted problem reports. */
export default function ProblemReportsIndex({ reports }: Props) {
    if (reports.data.length === 0) {
        return (
            <>
                <Head title="My Reports" />

                <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                    <PageHeader
                        title="My Reports"
                        description="Track your submitted problem reports"
                    />
                    <EmptyState
                        title="No reports yet"
                        description="You haven't submitted any problem reports."
                        action={
                            <Button asChild>
                                <Link href="/problem-reports/create">
                                    Submit a report
                                </Link>
                            </Button>
                        }
                    />
                </div>
            </>
        );
    }

    return (
        <>
            <Head title="My Reports" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="My Reports"
                    description="Track your submitted problem reports"
                    actions={
                        <Button asChild>
                            <Link href="/problem-reports/create">
                                Submit Report
                            </Link>
                        </Button>
                    }
                />

                <div className="space-y-4">
                    {reports.data.map((report) => {
                        const status = getProblemReportStatusPresentation(
                            report.status,
                        );

                        return (
                            <Link
                                key={report.id}
                                href={`/problem-reports/${report.reference}`}
                                className="block cursor-pointer rounded-lg focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background focus-visible:outline-none"
                            >
                                <div className="rounded-lg border border-border p-4 transition-colors hover:bg-muted/30">
                                    <div className="flex items-start justify-between gap-4">
                                        <div className="min-w-0 flex-1">
                                            <div className="mb-2 flex items-center gap-3">
                                                <code className="font-mono text-sm font-medium text-muted-foreground">
                                                    {report.reference}
                                                </code>
                                                <StatusBadge
                                                    label={status.label}
                                                    variant={status.variant}
                                                />
                                            </div>
                                            <h3 className="truncate text-base font-medium text-foreground">
                                                {report.title || 'No title'}
                                            </h3>
                                            <p className="mt-1 line-clamp-2 text-sm text-muted-foreground">
                                                {report.description}
                                            </p>
                                            <div className="mt-3 flex flex-wrap gap-4 text-xs text-muted-foreground">
                                                {report.attachments.length >
                                                    0 && (
                                                    <span>
                                                        {
                                                            report.attachments
                                                                .length
                                                        }{' '}
                                                        screenshot
                                                        {report.attachments
                                                            .length !== 1
                                                            ? 's'
                                                            : ''}
                                                    </span>
                                                )}
                                                <span>
                                                    Submitted{' '}
                                                    {new Date(
                                                        report.created_at,
                                                    ).toLocaleDateString(
                                                        'en-US',
                                                        {
                                                            month: 'short',
                                                            day: 'numeric',
                                                            year: 'numeric',
                                                        },
                                                    )}
                                                </span>
                                                {report.created_at !==
                                                    report.updated_at && (
                                                    <span>
                                                        Updated{' '}
                                                        {new Date(
                                                            report.updated_at,
                                                        ).toLocaleDateString(
                                                            'en-US',
                                                            {
                                                                month: 'short',
                                                                day: 'numeric',
                                                                year: 'numeric',
                                                            },
                                                        )}
                                                    </span>
                                                )}
                                            </div>
                                        </div>

                                        <ChevronRight
                                            className="mt-1 size-4 shrink-0 text-muted-foreground"
                                            aria-hidden="true"
                                        />
                                    </div>
                                </div>
                            </Link>
                        );
                    })}
                </div>

                <PaginationControls
                    currentPage={reports.current_page}
                    lastPage={reports.last_page}
                    from={reports.from}
                    to={reports.to}
                    total={reports.total}
                    previousPageUrl={reports.prev_page_url}
                    nextPageUrl={reports.next_page_url}
                    preserveScroll
                    preserveState
                    itemLabel="reports"
                />
            </div>
        </>
    );
}

/** Wraps the report index with the canonical application layout and breadcrumbs. */
ProblemReportsIndex.layout = (page: ReactNode) => (
    <AppLayout
        breadcrumbs={[
            {
                title: 'My Reports',
                href: '/problem-reports',
            } satisfies BreadcrumbItem,
        ]}
    >
        {page}
    </AppLayout>
);
