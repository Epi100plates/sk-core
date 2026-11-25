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

    /* Filter forms (queue status strip, released archive) submit on change, so
       the employee does not have to press "Zastosuj filtr" after every click.
       Only pickers and checkboxes submit immediately; a text field still needs
       its own submit button. Without JavaScript the <noscript> button is used. */
    function setupAutoSubmit() {
        document.querySelectorAll('form[data-auto-submit]').forEach(function (form) {
            form.addEventListener('change', function (event) {
                var target = event.target;

                if (!target || !target.matches('input[type="checkbox"], input[type="date"], input[type="radio"], select')) {
                    return;
                }

                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit();
                } else {
                    form.submit();
                }
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        setupPasswordToggles();
        setupConfirmations();
        setupDoubleSubmitGuard();
        setupOfflineState();
        setupLogoutCacheCleanup();
        setupAutoSubmit();
    });
})();
