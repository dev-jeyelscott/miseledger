import { useCallback, useState } from 'react';

/** Keep a form dialog open when the user declines to discard dirty changes. */
export function useGuardedDialog(discardMessage: string) {
    const [open, setOpen] = useState(false);
    const [dirty, setDirty] = useState(false);

    const onOpenChange = useCallback(
        (nextOpen: boolean) => {
            if (!nextOpen && dirty && !window.confirm(discardMessage)) {
                return;
            }

            setOpen(nextOpen);

            if (!nextOpen) {
                setDirty(false);
            }
        },
        [dirty, discardMessage],
    );

    const markDirty = useCallback(() => {
        setDirty(true);
    }, []);

    const closeAfterSuccess = useCallback(() => {
        setDirty(false);
        setOpen(false);
    }, []);

    return {
        open,
        onOpenChange,
        markDirty,
        closeAfterSuccess,
    };
}
