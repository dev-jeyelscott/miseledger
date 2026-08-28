import type { ComponentProps } from 'react';

import { cn } from '@/lib/utils';

type StatusBadgeProps = Omit<ComponentProps<'span'>, 'children'> & {
    label: string;
    variant?: 'neutral' | 'success' | 'warning' | 'info' | 'danger';
};

const statusBadgeVariants = {
    neutral: 'border-border bg-muted text-muted-foreground',
    success: 'border-success-border bg-success-subtle text-success-foreground',
    warning: 'border-warning-border bg-warning-subtle text-warning-foreground',
    info: 'border-info-border bg-info-subtle text-info-foreground',
    danger: 'border-destructive/30 bg-destructive/10 text-destructive',
};

/** Render a compact, text-bearing semantic status indicator. */
function StatusBadge({
    className,
    label,
    variant = 'neutral',
    ...props
}: StatusBadgeProps) {
    return (
        <span
            data-slot="status-badge"
            className={cn(
                'inline-flex w-fit shrink-0 items-center justify-center rounded-md border px-2 py-0.5 text-xs font-medium whitespace-nowrap',
                statusBadgeVariants[variant],
                className,
            )}
            {...props}
        >
            {label}
        </span>
    );
}

export { StatusBadge, statusBadgeVariants };
export type { StatusBadgeProps };
