/* Shared behaviour: live queue search, password reveal, card navigation,
   confirmation dialogs and the double submit guard. Loaded on every page as a
   separate file so the CSP stays free of inline JS.

   The queue is re-rendered by the live search, so click and submit handlers are
   delegated on the document: a card created a second ago behaves like the one
   that was in the first HTML. */
(function () {
    'use strict';

    var secrets = new WeakMap();

    function setupPasswordToggles() {
        document.addEventListener('click', function (event) {
            var target = event.target;

            if (!target || typeof target.closest !== 'function') {
                return;
            }

            var button = target.closest('[data-password-toggle]');

            if (!button) {
                return;
            }

            var field = document.getElementById(button.getAttribute('data-password-toggle') || '');

            if (!field) {
                return;
            }

            var revealed = button.getAttribute('aria-pressed') === 'true';

            if (revealed) {
                secrets.set(field, field.textContent || '');
                field.textContent = '••••••';
                button.setAttribute('aria-pressed', 'false');
                button.setAttribute('aria-label', 'Pokaż hasło do urządzenia');
                button.textContent = 'Pokaż';
            } else {
                field.textContent = secrets.get(field) || '';
                button.setAttribute('aria-pressed', 'true');
                button.setAttribute('aria-label', 'Ukryj hasło do urządzenia');
                button.textContent = 'Ukryj';
            }
        });
    }

    function setupConfirmations() {
        // One listener for static and re-rendered forms. A cancelled dialog must
        // not fall through to the submit guard, otherwise the button that was
        // just protected stays disabled and the action cannot be retried.
        document.addEventListener('submit', function (event) {
            var form = event.target;

            if (!form || form.nodeType !== 1 || typeof form.matches !== 'function') {
                return;
            }

            if (form.matches('form[data-confirm]')) {
                var message = form.getAttribute('data-confirm') || 'Kontynuować?';

                if (!window.confirm(message)) {
                    event.preventDefault();

                    return;
                }
            }

            if (form.matches('form[data-guard-submit]')) {
                var buttons = form.querySelectorAll('button[type="submit"]');

                window.setTimeout(function () {
                    buttons.forEach(function (button) {
                        button.disabled = true;
                    });
                }, 0);
            }
        });
    }

    function setupCardNavigation() {
        // The whole tile opens the edit form; links, buttons, form controls and
        // a text selection keep their normal behaviour.
        document.addEventListener('click', function (event) {
            if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey
                || event.shiftKey || event.altKey) {
                return;
            }

            var target = event.target;

            if (!target || typeof target.closest !== 'function') {
                return;
            }

            if (target.closest('a, button, input, select, textarea, label, summary, form')) {
                return;
            }

            var card = target.closest('[data-card-href]');

            if (!card) {
                return;
            }

            if (window.getSelection && String(window.getSelection()) !== '') {
                return;
            }

            window.location.assign(card.getAttribute('data-card-href'));
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

    /* Filter forms (queue stage strip, archive date range) submit on change, so
       the employee does not have to press a button after every click. Only
       pickers and radios submit immediately; a text field still needs its own
       button. Without JavaScript the <noscript> button is used. */
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

    /* One search field, at the top of the page. On the queue it filters the list
       while typing (debounced, no Enter needed); on every other screen Enter
       opens the queue with the same term. An empty field brings the normal queue
       back. */
    function setupLiveSearch() {
        var form = document.querySelector('form[data-live-search]');
        var results = document.querySelector('[data-queue-results]');

        if (!form || !results) {
            return;
        }

        var input = form.querySelector('input[name="q"]');
        var endpoint = form.getAttribute('data-results-url');
        var source = form.getAttribute('data-queue-url') || form.getAttribute('action') || '/';
        var status = form.getAttribute('data-status') || '';
        var filterTerm = document.querySelector('[data-queue-filter-term]');
        var filterReset = document.querySelector('[data-queue-filter-reset]');
        var live = document.getElementById('queue-search-status');

        if (!input || !endpoint) {
            return;
        }

        var timer = null;
        var controller = null;
        var lastTerm = null;
        var sequence = 0;

        function queryString(term) {
            var params = new URLSearchParams();

            if (term !== '') {
                params.set('q', term);
            }

            if (status !== '') {
                params.set('status', status);
            }

            return params.toString();
        }

        function pageUrl(term) {
            var query = queryString(term);

            return source + (query === '' ? '' : '?' + query);
        }

        function syncControls(term) {
            if (filterTerm) {
                filterTerm.value = term;
            }

            if (filterReset) {
                var base = filterReset.getAttribute('data-base-href') || source;
                filterReset.setAttribute('href', term === '' ? base : base + '?q=' + encodeURIComponent(term));
            }
        }

        function announce() {
            if (!live) {
                return;
            }

            var summary = results.querySelector('[data-queue-summary-text]');
            live.textContent = summary ? summary.textContent.replace(/\s+/g, ' ').trim() : 'Zaktualizowano listę.';
        }

        function load(term) {
            if (term === lastTerm) {
                return;
            }

            lastTerm = term;

            var current = ++sequence;
            var query = queryString(term);

            if (controller) {
                controller.abort();
            }

            controller = typeof AbortController === 'function' ? new AbortController() : null;
            results.setAttribute('aria-busy', 'true');

            fetch(endpoint + (query === '' ? '' : '?' + query), {
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                signal: controller ? controller.signal : undefined
            }).then(function (response) {
                if (!response.ok || response.headers.get('X-Queue-Results') !== '1') {
                    throw new Error('HTTP ' + response.status);
                }

                return response.text();
            }).then(function (html) {
                if (current !== sequence) {
                    return;
                }

                results.innerHTML = html;
                results.setAttribute('aria-busy', 'false');
                syncControls(term);
                announce();

                if (window.history && window.history.replaceState) {
                    window.history.replaceState(null, '', pageUrl(term));
                }
            }).catch(function (error) {
                if (error && error.name === 'AbortError') {
                    return;
                }

                lastTerm = null;
                results.setAttribute('aria-busy', 'false');

                if (live) {
                    live.textContent = 'Nie udało się odświeżyć listy. Spróbuj ponownie.';
                }
            });
        }

        function schedule() {
            var term = input.value.trim();

            // Keep the stage form and its reset link in step with the field even
            // before the debounced request lands, so a quick chip click cannot
            // submit a stale term.
            syncControls(term);
            window.clearTimeout(timer);
            timer = window.setTimeout(function () {
                load(term);
            }, 250);
        }

        input.addEventListener('input', schedule);
        input.addEventListener('change', schedule);
        input.addEventListener('search', schedule);

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            window.clearTimeout(timer);
            load(input.value.trim());
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        setupPasswordToggles();
        setupConfirmations();
        setupCardNavigation();
        setupOfflineState();
        setupLogoutCacheCleanup();
        setupAutoSubmit();
        setupLiveSearch();
    });
})();
