import { Head, Link } from '@inertiajs/react';
import { Copy, Download, ShieldCheck } from 'lucide-react';
import { toast } from 'sonner';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { useClipboard } from '@/hooks/use-clipboard';
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

interface Reporter {
    name: string;
    email: string;
}

type ViewingContext = 'owner' | 'operator';

/** Format an absolute server timestamp in the viewer's local timezone. */
function formatDateTime(value: string): string {
    return new Intl.DateTimeFormat(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        timeZoneName: 'short',
    }).format(new Date(value));
}

interface Props {
    report: Report;
    viewingContext: ViewingContext;
    reporter?: Reporter | null;
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

/** Renders the details and attachments for one problem report. */
export default function ShowProblemReport({
    report,
    viewingContext,
    reporter,
}: Props) {
    const [, copy] = useClipboard();
    const navigation = resolveNavigation(viewingContext, report);
    const status = getProblemReportStatusPresentation(report.status);

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
        return viewingContext === 'operator'
            ? `/problem-reports/${report.reference}/operator/attachments/${attachment.id}`
            : `/problem-reports/${report.reference}/attachments/${attachment.id}`;
    };

    return (
        <>
            <Head title={report.title || 'Problem Report'} />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={report.title || 'Problem Report'}
                    description={report.reference}
                    actions={
                        <Button asChild variant="outline">
                            <Link href={navigation.backHref}>
                                {viewingContext === 'operator'
                                    ? 'Platform Console'
                                    : 'My Reports'}
                            </Link>
                        </Button>
                    }
                />

                <div className="max-w-4xl space-y-6">
                    <div className="space-y-4 rounded-lg bg-muted p-6">
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-4">
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
                                    <StatusBadge
                                        label={status.label}
                                        variant={status.variant}
                                    />
                                </div>
                            </div>
                            <div>
                                <p className="text-xs font-semibold text-muted-foreground uppercase">
                                    Submitted
                                </p>
                                <p className="mt-2 text-sm text-foreground">
                                    {formatDateTime(report.created_at)}
                                </p>
                            </div>
                            {report.created_at !== report.updated_at && (
                                <div>
                                    <p className="text-xs font-semibold text-muted-foreground uppercase">
                                        Updated
                                    </p>
                                    <p className="mt-2 text-sm text-foreground">
                                        {formatDateTime(report.updated_at)}
                                    </p>
                                </div>
                            )}
                        </div>
                    </div>

                    {viewingContext === 'operator' && (
                        <Alert>
                            <ShieldCheck aria-hidden="true" />
                            <AlertTitle>Operator view</AlertTitle>
                            <AlertDescription>
                                You are viewing this report with
                                platform-administrator access.
                            </AlertDescription>
                        </Alert>
                    )}

                    {viewingContext === 'operator' && reporter && (
                        <div className="space-y-2">
                            <h3 className="font-semibold text-foreground">
                                Reporter
                            </h3>
                            <p className="text-muted-foreground">
                                {reporter.name} &middot; {reporter.email}
                            </p>
                        </div>
                    )}

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

                    <div className="space-y-2">
                        <h3 className="font-semibold text-foreground">
                            Description
                        </h3>
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
                                    <div
                                        key={attachment.id}
                                        className="overflow-hidden rounded-lg border border-border"
                                    >
                                        <div className="relative aspect-video overflow-hidden bg-muted">
                                            <img
                                                src={getAttachmentUrl(
                                                    attachment,
                                                )}
                                                alt={attachment.original_name}
                                                loading="lazy"
                                                className="h-full w-full object-cover"
                                            />
                                        </div>
                                        <div className="flex items-center justify-between gap-2 bg-card p-2">
                                            <p className="truncate text-xs text-muted-foreground">
                                                {attachment.original_name}
                                            </p>
                                            <Button
                                                asChild
                                                variant="ghost"
                                                size="sm"
                                                className="shrink-0 gap-1.5"
                                            >
                                                <a
                                                    href={getAttachmentUrl(
                                                        attachment,
                                                    )}
                                                    download={
                                                        attachment.original_name
                                                    }
                                                    aria-label={`Download ${attachment.original_name}`}
                                                >
                                                    <Download
                                                        className="h-3.5 w-3.5"
                                                        aria-hidden="true"
                                                    />
                                                    Download
                                                </a>
                                            </Button>
                                        </div>
                                    </div>
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
            </div>
        </>
    );
}

/** Wraps the report detail page in the application layout and breadcrumbs. */
ShowProblemReport.layout = (page: Props) => ({
    breadcrumbs: resolveNavigation(page.viewingContext, page.report)
        .breadcrumbs,
});
