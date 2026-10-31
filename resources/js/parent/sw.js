// Parent app service worker. Served by ParentAppController::serviceWorker(), which puts
// `const VERSION = '…'` above this file (it changes with every build).
//
// Only the app itself is cached: the /parent page and the built JS/CSS. Children, fees and
// notices (/api/v1) are never stored on the phone, so a shared or lost phone holds no school data.

const CACHE = `parent-app-${VERSION}`;

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE).then((cache) => cache.add('/parent')));
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    // Remove files from older builds.
    event.waitUntil(
        caches
            .keys()
            .then((keys) => Promise.all(keys.filter((key) => key.startsWith('parent-app-') && key !== CACHE).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);

    if (request.method !== 'GET' || url.origin !== self.location.origin || url.pathname.startsWith('/api/')) {
        return; // straight to the network
    }

    // The app page: the network first (fresh school name and colours), the cached copy offline.
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request)
                .then((response) => {
                    const copy = response.clone();
                    caches.open(CACHE).then((cache) => cache.put('/parent', copy));

                    return response;
                })
                .catch(() => caches.match('/parent')),
        );

        return;
    }

    // Built files have a hash in their name and never change: the cache first.
    if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/parent/icon-')) {
        event.respondWith(
            caches.match(request).then(
                (cached) =>
                    cached ??
                    fetch(request).then((response) => {
                        const copy = response.clone();
                        caches.open(CACHE).then((cache) => cache.put(request, copy));

                        return response;
                    }),
            ),
        );
    }
});

// Signing out empties the cache too (the app sends this message).
self.addEventListener('message', (event) => {
    if (event.data === 'sign-out') {
        event.waitUntil(caches.keys().then((keys) => Promise.all(keys.filter((key) => key.startsWith('parent-app-')).map((key) => caches.delete(key)))));
    }
});
