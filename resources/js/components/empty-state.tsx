import type { ComponentProps, ReactNode } from 'react';
import type { LucideIcon } from 'lucide-react';

import { cn } from '@/lib/utils';

type EmptyStateProps = ComponentProps<'div'> & {
    action?: ReactNode;
    description: ReactNode;
    icon?: LucideIcon;
    title: ReactNode;
};

/** Render a reusable in-surface empty state with optional icon and next action. */
function EmptyState({
    action,
    className,
    description,
    icon: Icon,
    title,
    ...props
}: EmptyStateProps) {
    return (
        <div
            data-slot="empty-state"
            className={cn(
                'flex flex-col items-center justify-center text-center',
                className,
            )}
            {...props}
        >
            {Icon ? (
                <div className="mb-3 flex size-10 items-center justify-center rounded-full bg-muted text-muted-foreground">
                    <Icon className="size-5" aria-hidden="true" />
                </div>
            ) : null}

            <h3 className="font-medium text-foreground">{title}</h3>
            <div className="mt-1 max-w-lg text-sm text-muted-foreground">
                {description}
            </div>

            {action ? <div className="mt-4">{action}</div> : null}
        </div>
    );
}

export { EmptyState };
export type { EmptyStateProps };
