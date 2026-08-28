import * as React from 'react';

import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

type FieldControlProps = {
    'aria-describedby'?: string;
    'aria-invalid'?: boolean | 'false' | 'true';
    id?: string;
};

type FieldProps = Omit<React.ComponentProps<'div'>, 'children'> & {
    children: React.ReactElement<FieldControlProps>;
    error?: string;
    errorId?: string;
    helper?: React.ReactNode;
    helperId?: string;
    id: string;
    label: React.ReactNode;
    labelId?: string;
};

/** Compose a labeled native form control with optional helper and validation text. */
function Field({
    children,
    className,
    error,
    errorId,
    helper,
    helperId,
    id,
    label,
    labelId,
    ...props
}: FieldProps) {
    const controlId = children.props.id ?? id;
    const resolvedLabelId = labelId ?? `${id}-label`;
    const resolvedHelperId = helperId ?? `${id}-helper`;
    const resolvedErrorId = errorId ?? `${id}-error`;
    const describedBy = [
        children.props['aria-describedby'],
        helper ? resolvedHelperId : undefined,
        error ? resolvedErrorId : undefined,
    ]
        .filter(Boolean)
        .join(' ');

    return (
        <div
            data-slot="field"
            className={cn('grid gap-2', className)}
            {...props}
        >
            <Label id={resolvedLabelId} htmlFor={controlId}>
                {label}
            </Label>
            {React.cloneElement(children, {
                id: controlId,
                'aria-describedby': describedBy || undefined,
                'aria-invalid': error ? true : children.props['aria-invalid'],
            })}
            {helper ? (
                <p id={resolvedHelperId} className="text-sm text-muted-foreground">
                    {helper}
                </p>
            ) : null}
            <InputError id={resolvedErrorId} message={error} />
        </div>
    );
}

export { Field };
