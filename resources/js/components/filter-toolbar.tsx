import type { ComponentProps } from 'react';

import { cn } from '@/lib/utils';

/** Render a bounded, responsive container for server-backed filter controls. */
function FilterToolbar({ className, ...props }: ComponentProps<'div'>) {
    return (
        <div
            data-slot="filter-toolbar"
            className={cn(
                'rounded-xl border border-border bg-card p-4 text-card-foreground',
                className,
            )}
            {...props}
        />
    );
}

export { FilterToolbar };
