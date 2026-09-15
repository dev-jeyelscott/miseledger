import { Link } from '@inertiajs/react';
import { Copy } from 'lucide-react';
import type { ReactNode } from 'react';
import { toast } from 'sonner';
import { PageHeader } from '@/components/page-header';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useClipboard } from '@/hooks/use-clipboard';
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

type ViewingContext = 'owner' | 'operator';

interface Props {
    report: Report;
    viewingContext: ViewingContext;
}

/** Resolves the breadcrumb trail and back destination for the current viewing context. */
function resolveNavigation(viewingContext: ViewingContext, report: Report) {
    if (viewingContext === 'operator') {
        return {
            breadcrumbs: [
                {
                    title: 'Platform Console',
                    href: '/admin',
                } satisfies BreadcrumbItem,
                {
                    title: report.reference,
                    href: `/problem-reports/${report.reference}/operator`,
                } satisfies BreadcrumbItem,
            ],
            backLabel: 'Back to Platform Console',
            backHref: '/admin',
        };
    }

    return {
        breadcrumbs: [
            {
                title: 'My Reports',
                href: '/problem-reports',
            } satisfies BreadcrumbItem,
            {
                title: report.reference,
                href: `/problem-reports/${report.reference}`,
            } satisfies BreadcrumbItem,
        ],
        backLabel: 'Back to My Reports',
        backHref: '/problem-reports',
    };
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
export default function ShowProblemReport({ report, viewingContext }: Props) {
    const [, copy] = useClipboard();
    const navigation = resolveNavigation(viewingContext, report);

    /** Copies the report reference to the user's clipboard and reports the outcome. */
    const handleCopyReference = async () => {
        const copied = await copy(report.reference);

        if (copied) {
            toast.success('Reference copied to clipboard');
        } else {
            toast.error('Could not copy reference to clipboard');
        }
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
                <Button asChild variant="outline">
                    <Link href={navigation.backHref}>
                        {viewingContext === 'operator'
                            ? 'Platform Console'
                            : 'My Reports'}
                    </Link>
                </Button>
            </div>

            <div className="space-y-4 rounded-lg bg-muted p-6">
                <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
                    <div>
                        <p className="text-xs font-semibold text-muted-foreground uppercase">
                            Reference
                        </p>
                        <div className="mt-2 flex items-center gap-2">
                            <code className="font-mono text-sm font-medium">
                                {report.reference}
                            </code>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="size-11"
                                onClick={handleCopyReference}
                                aria-label="Copy reference"
                            >
                                <Copy
                                    className="h-3 w-3 text-muted-foreground"
                                    aria-hidden="true"
                                />
                            </Button>
                        </div>
                    </div>
                    <div>
                        <p className="text-xs font-semibold text-muted-foreground uppercase">
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
                        <p className="text-xs font-semibold text-muted-foreground uppercase">
                            Submitted
                        </p>
                        <p className="mt-2 text-sm text-foreground">
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
                            <p className="text-xs font-semibold text-muted-foreground uppercase">
                                Updated
                            </p>
                            <p className="mt-2 text-sm text-foreground">
                                {new Date(report.updated_at).toLocaleDateString(
                                    'en-US',
                                    {
                                        month: 'short',
                                        day: 'numeric',
                                        year: 'numeric',
                                    },
                                )}
                            </p>
                        </div>
                    )}
                </div>
            </div>

            <Alert>
                <AlertDescription>
                    Status updates may take up to one hour to appear.
                </AlertDescription>
            </Alert>

            {report.organization_name_snapshot && (
                <div className="space-y-2">
                    <h3 className="font-semibold text-foreground">
                        Organization
                    </h3>
                    <p className="text-muted-foreground">
                        {report.organization_name_snapshot}
                    </p>
                </div>
            )}

            {report.title && (
                <div className="space-y-2">
                    <h3 className="font-semibold text-foreground">Title</h3>
                    <p className="text-muted-foreground">{report.title}</p>
                </div>
            )}

            <div className="space-y-2">
                <h3 className="font-semibold text-foreground">Description</h3>
                <p className="whitespace-pre-wrap text-muted-foreground">
                    {report.description}
                </p>
            </div>

            {report.attachments.length > 0 && (
                <div className="space-y-4">
                    <h3 className="font-semibold text-foreground">
                        Screenshots ({report.attachments.length})
                    </h3>
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                        {report.attachments.map((attachment) => (
                            <a
                                key={attachment.id}
                                href={getAttachmentUrl(attachment)}
                                className="block overflow-hidden rounded-lg border border-border transition-colors hover:border-muted-foreground"
                                download={attachment.original_name}
                            >
                                <img
                                    src={getAttachmentUrl(attachment)}
                                    alt={attachment.original_name}
                                    className="h-auto w-full"
                                />
                                <div className="bg-card p-2">
                                    <p className="truncate text-xs text-muted-foreground">
                                        {attachment.original_name}
                                    </p>
                                </div>
                            </a>
                        ))}
                    </div>
                </div>
            )}

            <div className="flex gap-4 border-t pt-4">
                <Button asChild variant="outline">
                    <Link href={navigation.backHref}>
                        {navigation.backLabel}
                    </Link>
                </Button>
            </div>
        </div>
    );
}

/** Wraps the report detail page in the application layout and breadcrumbs. */
ShowProblemReport.layout = (
    page: ReactNode,
    { report, viewingContext }: Props,
) => (
    <AppLayout
        breadcrumbs={resolveNavigation(viewingContext, report).breadcrumbs}
    >
        {page}
    </AppLayout>
);
