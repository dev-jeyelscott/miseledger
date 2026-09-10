import { Link } from '@inertiajs/react';
import { CheckCircle2, Copy } from 'lucide-react';
import { useState } from 'react';
import { AppLayout } from '@/components/app-layout';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
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
    attachments: Attachment[];
    created_at: string;
}

interface Props {
    report: Report;
}

export default function ShowProblemReport({ report }: Props) {
    const [copied, setCopied] = useState(false);

    const handleCopyReference = () => {
        navigator.clipboard.writeText(report.reference);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    return (
        <AppLayout>
            <div className="space-y-6 max-w-2xl">
                <div className="text-center space-y-4">
                    <div className="flex justify-center">
                        <CheckCircle2 className="h-12 w-12 text-green-600" />
                    </div>
                    <PageHeader
                        title="Report Submitted Successfully"
                        description="Thank you for helping us improve MiseLedger"
                    />
                </div>

                <Alert>
                    <AlertDescription>
                        We've received your report and will review it shortly.
                    </AlertDescription>
                </Alert>

                <div className="space-y-4 bg-gray-50 rounded-lg p-6">
                    <div className="space-y-2">
                        <h3 className="font-semibold text-gray-900">Reference Number</h3>
                        <div className="flex items-center gap-2">
                            <code className="text-lg font-mono bg-white px-3 py-2 rounded border border-gray-200 flex-1">
                                {report.reference}
                            </code>
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={handleCopyReference}
                            >
                                {copied ? (
                                    <span className="text-green-600">Copied!</span>
                                ) : (
                                    <>
                                        <Copy className="h-4 w-4 mr-2" />
                                        Copy
                                    </>
                                )}
                            </Button>
                        </div>
                        <p className="text-sm text-gray-600">
                            Keep this reference number for your records. You can use it to track your report.
                        </p>
                    </div>
                </div>

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
                    <div className="space-y-2">
                        <h3 className="font-semibold text-gray-900">
                            Attachments ({report.attachments.length})
                        </h3>
                        <ul className="space-y-1">
                            {report.attachments.map((attachment) => (
                                <li
                                    key={attachment.id}
                                    className="text-sm text-gray-600 flex items-center gap-2"
                                >
                                    <span className="inline-block w-1 h-1 bg-gray-400 rounded-full" />
                                    {attachment.original_name}
                                </li>
                            ))}
                        </ul>
                    </div>
                )}

                <div className="flex gap-4 pt-4">
                    <Link href="/dashboard">
                        <Button>Back to Dashboard</Button>
                    </Link>
                </div>
            </div>
        </AppLayout>
    );
}
