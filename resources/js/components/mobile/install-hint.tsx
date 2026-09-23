import { useEffect, useState } from 'react';

import { Button } from '@/components/ui/button';

type BeforeInstallPromptEvent = Event & {
    prompt: () => Promise<void>;
    userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
};

/**
 * Thin wrapper around the standard `beforeinstallprompt` event. Chrome/Edge
 * on Android fire it and often suppress their own banner; iOS Safari never
 * fires it at all, so this stays silent there and users rely on the native
 * Share > Add to Home Screen affordance instead.
 */
export function InstallHint() {
    const [deferredPrompt, setDeferredPrompt] =
        useState<BeforeInstallPromptEvent | null>(null);
    const [dismissed, setDismissed] = useState(false);

    useEffect(() => {
        const handler = (event: Event) => {
            event.preventDefault();
            setDeferredPrompt(event as BeforeInstallPromptEvent);
        };

        window.addEventListener('beforeinstallprompt', handler);

        return () => window.removeEventListener('beforeinstallprompt', handler);
    }, []);

    if (!deferredPrompt || dismissed) {
        return null;
    }

    return (
        <div className="flex items-center justify-between gap-3 border-b bg-muted px-4 py-2 text-sm">
            <span>Install MiseLedger for quicker access.</span>
            <div className="flex shrink-0 gap-2">
                <Button
                    size="sm"
                    onClick={() => {
                        void deferredPrompt.prompt();
                        void deferredPrompt.userChoice.finally(() =>
                            setDeferredPrompt(null),
                        );
                    }}
                >
                    Install app
                </Button>
                <Button
                    size="sm"
                    variant="ghost"
                    aria-label="Dismiss install hint"
                    onClick={() => setDismissed(true)}
                >
                    Dismiss
                </Button>
            </div>
        </div>
    );
}
