import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import type { ReactNode } from 'react';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { EmptyState } from '@/components/empty-state';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

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

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginationData {
    data: Report[];
    links: PaginationLink[];
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
}

interface Props {
    reports: PaginationData;
}

function getStatusBadgeVariant(status: string): 'default' | 'secondary' | 'destructive' | 'outline' {
    switch (status.toLowerCase()) {
        case 'submitted':
            return 'default';
        case 'in-review':
            return 'secondary';
        case 'in-progress':
            return 'secondary';
        case 'resolved':
            return 'outline';
        case 'closed':
            return 'outline';
        default:
            return 'default';
    }
}

function getStatusLabel(status: string): string {
    switch (status.toLowerCase()) {
        case 'submitted':
            return 'Submitted';
        case 'in-review':
            return 'In Review';
        case 'in-progress':
            return 'In Progress';
        case 'resolved':
            return 'Resolved';
        case 'closed':
            return 'Closed';
        default:
            return status;
    }
}

export default function ProblemReportsIndex({ reports }: Props) {
    if (reports.data.length === 0) {
        return (
            <div className="space-y-6">
                <PageHeader
                    title="My Reports"
                    description="Track your submitted problem reports"
                />
                <EmptyState
                    title="No reports yet"
                    description="You haven't submitted any problem reports."
                    action={{
                        label: 'Submit a report',
                        href: '/problem-reports/create',
                    }}
                />
            </div>
        );
    }

    return (
        <div className="space-y-6">
                <div className="flex justify-between items-center">
                    <PageHeader
                        title="My Reports"
                        description="Track your submitted problem reports"
                    />
                    <Link href="/problem-reports/create">
                        <Button>Submit Report</Button>
                    </Link>
                </div>

                <div className="space-y-4">
                    {reports.data.map((report) => (
                        <Link
                            key={report.id}
                            href={`/problem-reports/${report.reference}`}
                            className="block"
                        >
                            <div className="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition-colors">
                                <div className="flex justify-between items-start gap-4">
                                    <div className="flex-1 min-w-0">
                                        <div className="flex items-center gap-3 mb-2">
                                            <code className="text-sm font-mono font-medium text-gray-600">
                                                {report.reference}
                                            </code>
                                            <Badge variant={getStatusBadgeVariant(report.status)}>
                                                {getStatusLabel(report.status)}
                                            </Badge>
                                        </div>
                                        <h3 className="text-base font-medium text-gray-900 truncate">
                                            {report.title || 'No title'}
                                        </h3>
                                        <p className="text-sm text-gray-600 line-clamp-2 mt-1">
                                            {report.description}
                                        </p>
                                        <div className="flex flex-wrap gap-4 mt-3 text-xs text-gray-500">
                                            {report.attachments.length > 0 && (
                                                <span>{report.attachments.length} screenshot{report.attachments.length !== 1 ? 's' : ''}</span>
                                            )}
                                            <span>Submitted {new Date(report.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</span>
                                            {report.created_at !== report.updated_at && (
                                                <span>Updated {new Date(report.updated_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</span>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </Link>
                    ))}
                </div>

                {reports.meta.last_page > 1 && (
                    <div className="flex items-center justify-between pt-4 border-t">
                        <div className="text-sm text-gray-600">
                            Showing {(reports.meta.current_page - 1) * reports.meta.per_page + 1} to{' '}
                            {Math.min(reports.meta.current_page * reports.meta.per_page, reports.meta.total)} of{' '}
                            {reports.meta.total} reports
                        </div>
                        <div className="flex gap-2">
                            {reports.links.map((link, index) => {
                                if (!link.url) {
                                    return (
                                        <span
                                            key={index}
                                            className="px-3 py-1 text-sm text-gray-400"
                                        >
                                            {link.label.includes('Previous') ? <ChevronLeft className="h-4 w-4" /> : link.label.includes('Next') ? <ChevronRight className="h-4 w-4" /> : link.label}
                                        </span>
                                    );
                                }

                                return (
                                    <Link key={index} href={link.url}>
                                        <Button
                                            variant={link.active ? 'default' : 'outline'}
                                            size="sm"
                                        >
                                            {link.label.includes('Previous') ? (
                                                <ChevronLeft className="h-4 w-4" />
                                            ) : link.label.includes('Next') ? (
                                                <ChevronRight className="h-4 w-4" />
                                            ) : (
                                                link.label
                                            )}
                                        </Button>
                                    </Link>
                                );
                            })}
                        </div>
                    </div>
                )}
            </div>
    );
}

ProblemReportsIndex.layout = (page: ReactNode) => (
    <AppLayout
        breadcrumbs={[
            { title: 'My Reports', href: '/problem-reports' } satisfies BreadcrumbItem,
        ]}
    >
        {page}
    </AppLayout>
);
