import type { ComponentProps, ReactNode } from 'react';

import { cn } from '@/lib/utils';

type PageHeaderProps = ComponentProps<'header'> & {
    actions?: ReactNode;
    description?: ReactNode;
    title: ReactNode;
};

/** Render a responsive page title, supporting copy, and action area. */
function PageHeader({
    actions,
    className,
    description,
    title,
    ...props
}: PageHeaderProps) {
    return (
        <header
            data-slot="page-header"
            className={cn(
                'flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between',
                className,
            )}
            {...props}
        >
            <div>
                <h1 className="text-2xl font-semibold tracking-tight">
                    {title}
                </h1>
                {description ? (
                    <p className="mt-1 text-sm text-muted-foreground">
                        {description}
                    </p>
                ) : null}
            </div>
            {actions ? (
                <div className="flex flex-wrap gap-2">{actions}</div>
            ) : null}
        </header>
    );
}

export { PageHeader };
export type { PageHeaderProps };
