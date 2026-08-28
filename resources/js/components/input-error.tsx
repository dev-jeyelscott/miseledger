import type { HTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

/** Render a semantic validation message that can be associated with its form control. */
export default function InputError({
    message,
    className = '',
    ...props
}: HTMLAttributes<HTMLParagraphElement> & { message?: string }) {
    return message ? (
        <p {...props} className={cn('text-sm text-destructive', className)}>
            {message}
        </p>
    ) : null;
}
