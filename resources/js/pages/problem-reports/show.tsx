import { Link } from '@inertiajs/react';
import { Copy } from 'lucide-react';
import type { ReactNode } from 'react';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Alert, AlertDescription } from '@/components/ui/alert';
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

interface Props {
    report: Report;
}

/** Maps a problem report status to the existing Badge visual variant. */
function getStatusBadgeVariant(
    status: string,
): 'default' | 'secondary' | 'destructive' | 'outline' {
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

/** Converts the stored problem report status into its user-facing label. */
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

/** Renders the details and attachments for one problem report. */
export default function ShowProblemReport({ report }: Props) {
    /** Copies the report reference to the user's clipboard. */
    const handleCopyReference = () => {
        void navigator.clipboard.writeText(report.reference);
    };

    /** Builds the authenticated attachment download URL for the report. */
    const getAttachmentUrl = (attachment: Attachment) => {
        return `/problem-reports/${report.reference}/attachments/${attachment.id}`;
    };

    return (
        <div className="max-w-4xl space-y-6">
            <div className="flex items-start justify-between gap-4">
                <PageHeader
                    title={report.title || 'Problem Report'}
                    description={report.reference}
                />
                <Link href="/problem-reports">
                    <Button variant="outline">My Reports</Button>
                </Link>
            </div>

            <div className="space-y-4 rounded-lg bg-gray-50 p-6">
                <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
                    <div>
                        <p className="text-xs font-semibold text-gray-500 uppercase">
                            Reference
                        </p>
                        <div className="mt-2 flex items-center gap-2">
                            <code className="font-mono text-sm font-medium">
                                {report.reference}
                            </code>
                            <button
                                onClick={handleCopyReference}
                                className="rounded p-1 hover:bg-gray-200"
                                title="Copy reference"
                            >
                                <Copy className="h-3 w-3 text-gray-600" />
                            </button>
                        </div>
                    </div>
                    <div>
                        <p className="text-xs font-semibold text-gray-500 uppercase">
                            Status
                        </p>
                        <div className="mt-2">
                            <Badge
                                variant={getStatusBadgeVariant(report.status)}
                            >
                                {getStatusLabel(report.status)}
                            </Badge>
                        </div>
                    </div>
                    <div>
                        <p className="text-xs font-semibold text-gray-500 uppercase">
                            Submitted
                        </p>
                        <p className="mt-2 text-sm text-gray-900">
                            {new Date(report.created_at).toLocaleDateString(
                                'en-US',
                                {
                                    month: 'short',
                                    day: 'numeric',
                                    year: 'numeric',
                                },
                            )}
                        </p>
                    </div>
                    {report.created_at !== report.updated_at && (
                        <div>
                            <p className="text-xs font-semibold text-gray-500 uppercase">
                                Updated
                            </p>
                            <p className="mt-2 text-sm text-gray-900">
                                {new Date(
                                    report.updated_at,
                                ).toLocaleDateString('en-US', {
                                    month: 'short',
                                    day: 'numeric',
                                    year: 'numeric',
                                })}
                            </p>
                        </div>
                    )}
                </div>
            </div>

            <Alert>
                <AlertDescription>
                    Remote status changes can take up to one hour to appear
                    after synchronization is implemented.
                </AlertDescription>
            </Alert>

            {report.organization_name_snapshot && (
                <div className="space-y-2">
                    <h3 className="font-semibold text-gray-900">
                        Organization
                    </h3>
                    <p className="text-gray-700">
                        {report.organization_name_snapshot}
                    </p>
                </div>
            )}

            {report.title && (
                <div className="space-y-2">
                    <h3 className="font-semibold text-gray-900">Title</h3>
                    <p className="text-gray-700">{report.title}</p>
                </div>
            )}

            <div className="space-y-2">
                <h3 className="font-semibold text-gray-900">Description</h3>
                <p className="whitespace-pre-wrap text-gray-700">
                    {report.description}
                </p>
            </div>

            {report.attachments.length > 0 && (
                <div className="space-y-4">
                    <h3 className="font-semibold text-gray-900">
                        Screenshots ({report.attachments.length})
                    </h3>
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                        {report.attachments.map((attachment) => (
                            <a
                                key={attachment.id}
                                href={getAttachmentUrl(attachment)}
                                className="block overflow-hidden rounded-lg border border-gray-200 transition-colors hover:border-gray-300"
                                download={attachment.original_name}
                            >
                                <img
                                    src={getAttachmentUrl(attachment)}
                                    alt={attachment.original_name}
                                    className="h-auto w-full"
                                />
                                <div className="bg-white p-2">
                                    <p className="truncate text-xs text-gray-600">
                                        {attachment.original_name}
                                    </p>
                                </div>
                            </a>
                        ))}
                    </div>
                </div>
            )}

            <div className="flex gap-4 border-t pt-4">
                <Link href="/problem-reports">
                    <Button variant="outline">
                        Back to My Reports
                    </Button>
                </Link>
            </div>
        </div>
    );
}

/** Wraps the report detail page in the application layout and breadcrumbs. */
ShowProblemReport.layout = (
    page: ReactNode,
    { report }: { report: Report },
) => (
    <AppLayout
        breadcrumbs={[
            {
                title: 'My Reports',
                href: '/problem-reports',
            } satisfies BreadcrumbItem,
            {
                title: report.reference,
                href: `/problem-reports/${report.reference}`,
            } satisfies BreadcrumbItem,
        ]}
    >
        {page}
    </AppLayout>
);
