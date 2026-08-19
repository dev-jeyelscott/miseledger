import type { ComponentProps } from 'react';

import { Button } from '@/components/ui/button';
import { navigateToPreviousPage } from '@/lib/navigation-history';

type PreviousPageButtonProps = Omit<
    ComponentProps<typeof Button>,
    'asChild' | 'onClick' | 'type'
> & {
    fallback: string;
};

/** Render a Back/Cancel control that prefers real in-app browser history. */
export function PreviousPageButton({
    fallback,
    children = 'Back',
    ...props
}: PreviousPageButtonProps) {
    return (
        <Button
            type="button"
            {...props}
            onClick={() => navigateToPreviousPage(fallback)}
        >
            {children}
        </Button>
    );
}
