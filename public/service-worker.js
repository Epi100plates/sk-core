/* Studio-Komp Serwis — service worker (feature F-43/F-44, decision D5).
 *
 * Only the shell is cached: CSS, JS, icons and the offline page. Every request
 * that could carry customer data (HTML pages, JSON, photos) is network-only, so
 * the old defect D-28 — a cache full of phone numbers and laptop passwords that
 * survived logout — cannot happen again.
 *
 * The cache name contains ASSET_VERSION, parsed from the registration URL, so a
 * deployment invalidates the cache without editing this file.
 */
'use strict';

var params = new URL(self.location.href).searchParams;
var version = params.get('v') || '1';
var CACHE_NAME = 'serwis-' + version;

var PRECACHE_URLS = [
    '/offline',
    '/css/bootstrap.min.css',
    '/css/app.css',
    '/css/print.css',
    '/fonts/schibsted-grotesk-latin.woff2',
    '/fonts/schibsted-grotesk-latin-ext.woff2',
    '/fonts/ibm-plex-mono-latin-500.woff2',
    '/js/bootstrap.bundle.min.js',
    '/js/app.js',
    '/js/theme.js',
    '/js/lightbox.js',
    '/js/pwa.js',
    '/js/print.js',
    '/icons/icon-96.png',
    '/icons/icon-192.png',
    '/icons/icon-512.png'
];

self.addEventListener('install', function (event) {
    event.waitUntil(
        caches.open(CACHE_NAME).then(function (cache) {
            return Promise.all(
                PRECACHE_URLS.map(function (url) {
                    return cache.add(url).catch(function () {
                        /* A missing optional asset must never break the install. */
                    });
                })
            );
        }).then(function () {
            return self.skipWaiting();
        })
    );
});

self.addEventListener('activate', function (event) {
    event.waitUntil(
        caches.keys().then(function (keys) {
            return Promise.all(
                keys.map(function (key) {
                    if (key !== CACHE_NAME) {
                        return caches.delete(key);
                    }

                    return Promise.resolve();
                })
            );
        }).then(function () {
            return self.clients.claim();
        })
    );
});

self.addEventListener('fetch', function (event) {
    var request = event.request;

    if (request.method !== 'GET') {
        return; // writes always go to the network (decision D5: no offline queue)
    }

    var url = new URL(request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    // Network-only for anything that may contain customer data.
    var isData = url.pathname.indexOf('/photos/') === 0
        || url.pathname === '/'
        || url.pathname.indexOf('/queue') === 0
        || url.pathname.indexOf('/services') === 0
        || url.pathname.indexOf('/released') === 0
        || url.pathname.indexOf('/admin') === 0
        || url.pathname.indexOf('/manifest') === 0;

    if (isData) {
        event.respondWith(
            fetch(request).catch(function () {
                if (request.mode === 'navigate') {
                    return caches.match('/offline');
                }

                return new Response('', { status: 503, statusText: 'Offline' });
            })
        );

        return;
    }

    var isShellAsset = url.pathname.indexOf('/css/') === 0
        || url.pathname.indexOf('/fonts/') === 0
        || url.pathname.indexOf('/js/') === 0
        || url.pathname.indexOf('/icons/') === 0
        || url.pathname === '/offline';

    if (!isShellAsset) {
        return;
    }

    event.respondWith(
        caches.match(request).then(function (cached) {
            return cached || fetch(request).then(function (response) {
                if (response.ok) {
                    var copy = response.clone();
                    caches.open(CACHE_NAME).then(function (cache) {
                        cache.put(request, copy);
                    });
                }

                return response;
            });
        })
    );
});

self.addEventListener('message', function (event) {
    if (event.data && event.data.type === 'CLEAR_CACHES') {
        caches.keys().then(function (keys) {
            keys.forEach(function (key) {
                if (key.indexOf('serwis-') === 0) {
                    caches.delete(key);
                }
            });
        });
    }
});
