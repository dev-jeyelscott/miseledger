/** Register the install-only, no-fetch-handler service worker scoped to `/mobile/`. */
export function registerMobileServiceWorker(): void {
    if (!('serviceWorker' in navigator)) {
        return;
    }

    void navigator.serviceWorker.register('/mobile-sw.js', {
        scope: '/mobile/',
    });
}
