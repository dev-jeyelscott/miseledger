// Install-only service worker for the /mobile/ scope. Intentionally has no
// `fetch` handler: online-first, zero caching. See
// docs/mobile-pwa.md for the no-op-fallback contingency if a target
// Android WebView version refuses to install without a fetch listener.

self.addEventListener('install', () => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});
