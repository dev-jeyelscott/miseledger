import { Link } from '@inertiajs/react';
import { Copy } from 'lucide-react';
import { useState } from 'react';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Alert, AlertDescription } from '@/components/ui/alert';

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

function getStatusBadgeVariant(status: string): 'default' | 'secondary' | 'destructive' | 'outline' {
    switch (status.toLowerCase()) {
        case 'submitted':
            return 'default';
        case 'acknowledged':
            return 'secondary';
        case 'resolved':
            return 'outline';
        default:
            return 'default';
    }
}

function getStatusLabel(status: string): string {
    switch (status.toLowerCase()) {
        case 'submitted':
            return 'Submitted';
        case 'acknowledged':
            return 'Acknowledged';
        case 'resolved':
            return 'Resolved';
        default:
            return status;
    }
}

export default function ShowProblemReport({ report }: Props) {
    const [copied, setCopied] = useState(false);

    const handleCopyReference = () => {
        navigator.clipboard.writeText(report.reference);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    const getAttachmentUrl = (attachment: Attachment) => {
        return `/problem-reports/${report.reference}/attachments/${attachment.id}`;
    };

    return (
            <div className="space-y-6 max-w-4xl">
                <div className="flex justify-between items-start gap-4">
                    <PageHeader
                        title={report.title || 'Problem Report'}
                        description={report.reference}
                    />
                    <Link href="/problem-reports">
                        <Button variant="outline">My Reports</Button>
                    </Link>
                </div>

                <div className="space-y-4 bg-gray-50 rounded-lg p-6">
                    <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
                        <div>
                            <p className="text-xs font-semibold text-gray-500 uppercase">Reference</p>
                            <div className="flex items-center gap-2 mt-2">
                                <code className="text-sm font-mono font-medium">
                                    {report.reference}
                                </code>
                                <button
                                    onClick={handleCopyReference}
                                    className="p-1 hover:bg-gray-200 rounded"
                                    title="Copy reference"
                                >
                                    <Copy className="h-3 w-3 text-gray-600" />
                                </button>
                            </div>
                        </div>
                        <div>
                            <p className="text-xs font-semibold text-gray-500 uppercase">Status</p>
                            <div className="mt-2">
                                <Badge variant={getStatusBadgeVariant(report.status)}>
                                    {getStatusLabel(report.status)}
                                </Badge>
                            </div>
                        </div>
                        <div>
                            <p className="text-xs font-semibold text-gray-500 uppercase">Submitted</p>
                            <p className="text-sm text-gray-900 mt-2">
                                {new Date(report.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}
                            </p>
                        </div>
                        {report.created_at !== report.updated_at && (
                            <div>
                                <p className="text-xs font-semibold text-gray-500 uppercase">Updated</p>
                                <p className="text-sm text-gray-900 mt-2">
                                    {new Date(report.updated_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}
                                </p>
                            </div>
                        )}
                    </div>
                </div>

                <Alert>
                    <AlertDescription>
                        Remote status changes can take up to one hour to appear after synchronization is implemented.
                    </AlertDescription>
                </Alert>

                {report.organization_name_snapshot && (
                    <div className="space-y-2">
                        <h3 className="font-semibold text-gray-900">Organization</h3>
                        <p className="text-gray-700">{report.organization_name_snapshot}</p>
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
                    <p className="text-gray-700 whitespace-pre-wrap">{report.description}</p>
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
                                    className="block rounded-lg border border-gray-200 overflow-hidden hover:border-gray-300 transition-colors"
                                    download={attachment.original_name}
                                >
                                    <img
                                        src={getAttachmentUrl(attachment)}
                                        alt={attachment.original_name}
                                        className="w-full h-auto"
                                    />
                                    <div className="p-2 bg-white">
                                        <p className="text-xs text-gray-600 truncate">
                                            {attachment.original_name}
                                        </p>
                                    </div>
                                </a>
                            ))}
                        </div>
                    </div>
                )}

                <div className="flex gap-4 pt-4 border-t">
                    <Link href="/problem-reports">
                        <Button variant="outline">Back to My Reports</Button>
                    </Link>
                </div>
            </div>
    );
}
