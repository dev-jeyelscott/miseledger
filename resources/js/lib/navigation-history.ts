import { router } from '@inertiajs/react';

type PendingNavigation = {
    replace: boolean;
};

let initialized = false;
let entries: string[] = [];
let currentIndex = 0;
let pendingNavigation: PendingNavigation | null = null;

/** Return the current browser URL without coupling to Inertia's history state. */
function currentBrowserUrl(): string {
    return `${window.location.pathname}${window.location.search}${window.location.hash}`;
}

/** Normalize an Inertia page or visit URL to the same-origin path form we track. */
function normalizeUrl(value: string | URL): string {
    const url =
        value instanceof URL
            ? value
            : new URL(value.toString(), window.location.origin);

    return `${url.pathname}${url.search}${url.hash}`;
}

/**
 * Track successful in-app Inertia navigation so Back buttons can distinguish
 * real previous app entries from direct, refreshed, or externally-entered pages.
 */
export function initializeNavigationHistory(): void {
    if (initialized || typeof window === 'undefined') {
        return;
    }

    initialized = true;
    entries = [currentBrowserUrl()];
    currentIndex = 0;

    router.on('start', (event) => {
        pendingNavigation = {
            replace: event.detail.visit.replace,
        };
    });

    router.on('navigate', (event) => {
        const url = normalizeUrl(event.detail.page.url);

        if (pendingNavigation !== null) {
            const { replace } = pendingNavigation;
            pendingNavigation = null;

            if (url === entries[currentIndex]) {
                return;
            }

            if (replace) {
                entries[currentIndex] = url;

                return;
            }

            entries = entries.slice(0, currentIndex + 1);
            entries.push(url);
            currentIndex = entries.length - 1;

            return;
        }

        if (url === entries[currentIndex]) {
            return;
        }

        if (currentIndex > 0 && entries[currentIndex - 1] === url) {
            currentIndex -= 1;

            return;
        }

        if (
            currentIndex < entries.length - 1 &&
            entries[currentIndex + 1] === url
        ) {
            currentIndex += 1;

            return;
        }

        // A non-adjacent history jump cannot be proven safely after the fact.
        entries = [url];
        currentIndex = 0;
    });

    router.on('finish', (event) => {
        if (!event.detail.visit.completed) {
            pendingNavigation = null;
        }
    });
}

/**
 * Navigate to the actual previous in-app entry when known, otherwise replace
 * the current entry with the supplied canonical fallback.
 */
export function navigateToPreviousPage(fallbackUrl: string): void {
    if (typeof window === 'undefined') {
        return;
    }

    initializeNavigationHistory();

    if (currentIndex > 0) {
        window.history.back();

        return;
    }

    router.visit(fallbackUrl, {
        replace: true,
    });
}
