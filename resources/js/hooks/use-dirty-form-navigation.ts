import { router } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';

/** Guard unsaved Inertia form changes during GET visits, browser exits, and explicit back actions. */
export function useDirtyFormNavigation(discardMessage: string) {
    const [isDirty, setIsDirty] = useState(false);
    const allowNextNavigation = useRef(false);

    useEffect(() => {
        if (!isDirty) {
            return;
        }

        const removeBeforeListener = router.on('before', (event) => {
            if (event.detail.visit.method !== 'get') {
                return;
            }

            if (allowNextNavigation.current) {
                allowNextNavigation.current = false;

                return;
            }

            return window.confirm(discardMessage);
        });

        const handleBeforeUnload = (event: BeforeUnloadEvent) => {
            event.preventDefault();
            event.returnValue = '';
        };

        window.addEventListener('beforeunload', handleBeforeUnload);

        return () => {
            removeBeforeListener();
            window.removeEventListener('beforeunload', handleBeforeUnload);
        };
    }, [discardMessage, isDirty]);

    const confirmNavigation = useCallback((): boolean => {
        if (isDirty && !window.confirm(discardMessage)) {
            return false;
        }

        if (isDirty) {
            allowNextNavigation.current = true;
            setIsDirty(false);
        }

        return true;
    }, [discardMessage, isDirty]);

    return {
        confirmNavigation,
        isDirty,
        setIsDirty,
    };
}
