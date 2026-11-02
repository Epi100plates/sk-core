/* Shared behaviour: password reveal, confirmation dialogs, double submit guard.
   Loaded on every page as a separate file so the CSP stays free of inline JS. */
(function () {
    'use strict';

    function setupPasswordToggles() {
        document.querySelectorAll('[data-password-toggle]').forEach(function (button) {
            button.addEventListener('click', function () {
                var targetId = button.getAttribute('data-password-toggle');
                var target = document.getElementById(targetId || '');

                if (!target) {
                    return;
                }

                var hidden = target.getAttribute('data-value');
                var revealed = button.getAttribute('aria-pressed') === 'true';

                if (revealed) {
                    target.textContent = '••••••';
                    button.setAttribute('aria-pressed', 'false');
                    button.textContent = 'Pokaż';
                } else {
                    target.textContent = hidden || '';
                    button.setAttribute('aria-pressed', 'true');
                    button.textContent = 'Ukryj';
                }
            });
        });
    }

    function setupConfirmations() {
        document.querySelectorAll('form[data-confirm]').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                var message = form.getAttribute('data-confirm') || 'Kontynuować?';

                if (!window.confirm(message)) {
                    event.preventDefault();
                }
            });
        });
    }

    function setupDoubleSubmitGuard() {
        document.querySelectorAll('form[data-guard-submit]').forEach(function (form) {
            form.addEventListener('submit', function () {
                var buttons = form.querySelectorAll('button[type="submit"]');

                window.setTimeout(function () {
                    buttons.forEach(function (button) {
                        button.disabled = true;
                    });
                }, 0);
            });
        });
    }

    function setupOfflineState() {
        var banner = document.getElementById('offline-banner');

        function update() {
            var offline = !navigator.onLine;

            if (banner) {
                banner.classList.toggle('d-none', !offline);
            }

            document.querySelectorAll('form[data-offline-blocked]').forEach(function (form) {
                form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(function (control) {
                    control.disabled = offline;
                });
            });

            document.body.classList.toggle('is-offline', offline);
        }

        window.addEventListener('online', update);
        window.addEventListener('offline', update);
        update();
    }

    function setupLogoutCacheCleanup() {
        document.querySelectorAll('form[data-clear-cache-on-submit]').forEach(function (form) {
            form.addEventListener('submit', function () {
                if (!('caches' in window)) {
                    return;
                }

                caches.keys().then(function (keys) {
                    keys.forEach(function (key) {
                        caches.delete(key);
                    });
                });
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        setupPasswordToggles();
        setupConfirmations();
        setupDoubleSubmitGuard();
        setupOfflineState();
        setupLogoutCacheCleanup();
    });
})();
