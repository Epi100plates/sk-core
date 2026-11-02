/* PWA wiring (features F-43, F-44; decisions D5/D12).
   - registers the hand written service worker with the asset version,
   - shows the offline banner and blocks writes while offline,
   - never reloads the page automatically on update (defect D-31),
   - clears Cache Storage on logout so no customer data stays behind (D-28). */
(function () {
    'use strict';

    var body = document.body;
    var version = body ? body.getAttribute('data-asset-version') || '1' : '1';

    function register() {
        if (!('serviceWorker' in navigator)) {
            return;
        }

        var url = '/service-worker.js?v=' + encodeURIComponent(version);
        var base = body ? body.getAttribute('data-base-url') : null;

        if (base) {
            url = base.replace(/\/$/, '') + url;
        }

        navigator.serviceWorker.register(url, { scope: '/' }).catch(function () {
            // Registration failures are not fatal: the app keeps working online.
        });
    }

    function cleanCaches() {
        if (!('caches' in window)) {
            return;
        }

        caches.keys().then(function (keys) {
            keys.forEach(function (key) {
                if (key.indexOf('serwis-') === 0) {
                    caches.delete(key);
                }
            });
        });
    }

    window.addEventListener('load', register);
    window.addEventListener('serwis:logout', cleanCaches);
})();
