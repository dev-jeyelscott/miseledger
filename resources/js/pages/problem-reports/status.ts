import type { StatusBadgeProps } from '@/components/status-badge';

interface ProblemReportStatusPresentation {
    label: string;
    variant: NonNullable<StatusBadgeProps['variant']>;
}

const problemReportStatusPresentation: Record<
    string,
    ProblemReportStatusPresentation
> = {
    submitted: { label: 'Submitted', variant: 'info' },
    'in-review': { label: 'In Review', variant: 'warning' },
    'in-progress': { label: 'In Progress', variant: 'info' },
    resolved: { label: 'Resolved', variant: 'success' },
    closed: { label: 'Closed', variant: 'neutral' },
};

/** Resolves the shared StatusBadge label and variant for a problem report status. */
function getProblemReportStatusPresentation(
    status: string,
): ProblemReportStatusPresentation {
    return (
        problemReportStatusPresentation[status.toLowerCase()] ?? {
            label: status,
            variant: 'neutral',
        }
    );
}

export { getProblemReportStatusPresentation };
export type { ProblemReportStatusPresentation };
