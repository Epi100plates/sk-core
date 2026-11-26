/* Shared behaviour: in-place view updates, live queue search, password reveal,
   whole-tile navigation, confirmation dialogs and the double submit guard.
   Loaded on every page as a separate file so the CSP stays free of inline JS.

   Anything the server can re-render is updated in place instead of reloading the
   page: a form or link marked `data-async-form` / `data-async-link` fetches the
   real page, and every `[data-async-region]` that the response also contains is
   swapped. There is no second endpoint and no client-side copy of the data — the
   server HTML stays the only source of truth, so a filtered list looks exactly
   like the one a full reload would produce.

   Handlers are delegated on the document, because the elements that arrive with
   a swap must behave exactly like the ones in the first HTML. */
(function () {
    'use strict';

    /* The queue ignores a shorter term (QueueController::data()), so the address
       bar should not keep it either. */
    var MIN_TERM = 3;

    var secrets = new WeakMap();

    function closest(element, selector) {
        return element && typeof element.closest === 'function'
            ? element.closest(selector)
            : null;
    }

    function squash(value) {
        return String(value || '').replace(/\s+/g, ' ').trim();
    }

    function announce(message) {
        var status = document.getElementById('async-status');

        if (status) {
            status.textContent = message;
        }
    }

    /* ------------------------------------------------------------------ */
    /* Passwords                                                           */
    /* ------------------------------------------------------------------ */

    function setupPasswordToggles() {
        document.addEventListener('click', function (event) {
            var button = closest(event.target, '[data-password-toggle]');

            if (!button) {
                return;
            }

            var field = document.getElementById(button.getAttribute('data-password-toggle') || '');

            if (!field) {
                return;
            }

            if (button.getAttribute('aria-pressed') === 'true') {
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

    /* ------------------------------------------------------------------ */
    /* Confirmation dialogs and the double submit guard                    */
    /* ------------------------------------------------------------------ */

    function setupConfirmations() {
        // One listener for static and re-rendered forms. A cancelled dialog must
        // not fall through to the submit guard, otherwise the button that was
        // just protected stays disabled and the action cannot be retried. The
        // async handler below checks `defaultPrevented` for the same reason.
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

            if (form.matches('form[data-guard-submit]:not([data-async-form])')) {
                var buttons = form.querySelectorAll('button[type="submit"]');

                window.setTimeout(function () {
                    buttons.forEach(function (button) {
                        button.disabled = true;
                    });
                }, 0);
            }
        });
    }

    /* ------------------------------------------------------------------ */
    /* Whole tile navigation                                               */
    /* ------------------------------------------------------------------ */

    function setupCardNavigation() {
        // The tile opens the edit form; links, buttons, form controls and a text
        // selection keep their normal behaviour.
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

    /* ------------------------------------------------------------------ */
    /* Offline state and logout                                            */
    /* ------------------------------------------------------------------ */

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

    /* ------------------------------------------------------------------ */
    /* Async regions                                                       */
    /* ------------------------------------------------------------------ */

    var sequence = 0;
    var pendingRead = null;
    var writing = false;
    var flashTimer = null;

    /* Outermost regions only: a region nested in another one is replaced
       together with its parent and must not be swapped twice. */
    function regionElements() {
        return Array.prototype.filter.call(
            document.querySelectorAll('[data-async-region]'),
            function (element) {
                var parent = element.parentElement;

                return !parent || !parent.closest('[data-async-region]');
            }
        );
    }

    function regionName(element) {
        return element.getAttribute('data-async-region') || '';
    }

    function setBusy(busy) {
        regionElements().forEach(function (element) {
            element.setAttribute('aria-busy', busy ? 'true' : 'false');
        });
    }

    /* Swaps every region the response also contains; a region that only exists
       in the filtered state stays untouched. Returns the swapped names. */
    function swapRegions(source) {
        var swapped = [];

        regionElements().forEach(function (element) {
            var name = regionName(element);
            var incoming = source.querySelector('[data-async-region="' + name + '"]');

            if (!incoming) {
                return;
            }

            element.innerHTML = incoming.innerHTML;
            swapped.push(name);
        });

        return swapped;
    }

    function focusSnapshot() {
        var active = document.activeElement;

        if (!active || active === document.body) {
            return null;
        }

        return {
            name: active.name || '',
            id: active.id || '',
            value: typeof active.value === 'string' ? active.value : ''
        };
    }

    function restoreFocus(previous) {
        if (!previous) {
            return false;
        }

        var target = null;

        if (previous.name) {
            var value = previous.value === '' ? '' : '[value="' + previous.value.replace(/["\\]/g, '\\$&') + '"]';
            target = document.querySelector('[name="' + previous.name + '"]' + value);
        }

        if (!target && previous.id) {
            target = document.getElementById(previous.id);
        }

        if (!target) {
            return false;
        }

        if (document.activeElement !== target && typeof target.focus === 'function') {
            try {
                target.focus({ preventScroll: true });
            } catch (error) {
                target.focus();
            }
        }

        return true;
    }

    /* The clicked element is gone with the swap (a pagination link, a radio):
       land on the refreshed region so keyboard users do not drop to the top. */
    function focusRegion(name) {
        var region = document.querySelector('[data-async-region="' + name + '"]');

        if (!region) {
            return;
        }

        if (!region.hasAttribute('tabindex')) {
            region.setAttribute('tabindex', '-1');
        }

        try {
            region.focus({ preventScroll: true });
        } catch (error) {
            region.focus();
        }
    }

    /* What the live region should say after a swap: the confirmation the server
       sent, the queue summary, or a neutral confirmation. */
    function statusText() {
        var flash = document.querySelector('[data-async-region="flash"] .alert');

        if (flash) {
            return squash(flash.textContent);
        }

        var summary = document.querySelector('[data-queue-summary-text]');

        if (summary) {
            return squash(summary.textContent);
        }

        return 'Widok zaktualizowany.';
    }

    function scheduleFlashDismissal() {
        window.clearTimeout(flashTimer);

        flashTimer = window.setTimeout(function () {
            document.querySelectorAll('[data-async-region="flash"] .alert-success, [data-async-region="flash"] .alert-warning')
                .forEach(function (alert) {
                    alert.remove();
                });
        }, 6000);
    }

    function samePath(url) {
        try {
            return new URL(url, window.location.href).pathname === window.location.pathname;
        } catch (error) {
            return false;
        }
    }

    function reflectUrl(url) {
        if (!window.history || !window.history.replaceState) {
            return;
        }

        try {
            window.history.replaceState(window.history.state, '', url);
        } catch (error) {
            /* A cross-origin or malformed URL must not break the update. */
        }
    }

    /**
     * Fetches `url` and swaps the regions it contains.
     *
     * options.method  GET (default) or POST; a POST is never aborted, because the
     *                 server may already have applied it.
     * options.body    FormData for POST.
     * options.trigger the element that started the update; when the swap removes
     *                 it, focus moves into the refreshed region.
     */
    function update(url, options) {
        options = options || {};

        var method = (options.method || 'GET').toUpperCase();

        // A second write while the first is in flight would apply twice.
        if (method !== 'GET' && writing) {
            return Promise.resolve(false);
        }

        var current = ++sequence;
        var init = {
            method: method,
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'text/html'
            }
        };

        if (options.body) {
            init.body = options.body;
        }

        if (typeof AbortController === 'function') {
            if (pendingRead) {
                pendingRead.abort();
            }

            pendingRead = null;

            if (method === 'GET') {
                pendingRead = new AbortController();
                init.signal = pendingRead.signal;
            }
        }

        if (method !== 'GET') {
            writing = true;
        }

        setBusy(true);

        return fetch(url, init).then(function (response) {
            // A redirect to another screen (expired session, 419, login) cannot
            // be rendered in place.
            if (response.redirected && !samePath(response.url)) {
                window.location.assign(response.url);

                return null;
            }

            if (response.status === 401 || response.status === 419) {
                window.location.reload();

                return null;
            }

            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }

            return response.text().then(function (html) {
                return { html: html, url: response.url || url };
            });
        }).then(function (result) {
            if (!result || current !== sequence) {
                return false;
            }

            var source = new DOMParser().parseFromString(result.html, 'text/html');
            var previous = focusSnapshot();
            var trigger = options.trigger || null;
            var swapped = swapRegions(source);

            if (swapped.length === 0) {
                // Nothing to swap (a login form, an error page): navigate.
                window.location.assign(result.url);

                return false;
            }

            if (!restoreFocus(previous) && trigger && !document.contains(trigger)) {
                focusRegion(swapped[0]);
            }

            reflectUrl(result.url);
            scheduleFlashDismissal();
            announce(statusText());

            if (options.scroll) {
                var region = document.querySelector('[data-async-region="' + swapped[0] + '"]');

                if (region) {
                    region.scrollIntoView({ block: 'start', behavior: 'auto' });
                }
            }

            return true;
        }).catch(function (error) {
            if (error && error.name === 'AbortError') {
                return false;
            }

            announce('Nie udało się odświeżyć widoku. Spróbuj ponownie.');

            return false;
        }).then(function (result) {
            // `finally`, written out so the file keeps working without it.
            if (current === sequence) {
                setBusy(false);
            }

            if (method !== 'GET') {
                writing = false;
            }

            return result;
        });
    }

    function pageUrlFor(form) {
        var params = new URLSearchParams();

        new FormData(form).forEach(function (value, key) {
            if (typeof value === 'string' && value !== '') {
                params.append(key, value);
            }
        });

        var action = form.getAttribute('action') || window.location.pathname;
        var query = params.toString();

        return query === '' ? action : action + '?' + query;
    }

    function setupAsyncLinks() {
        document.addEventListener('click', function (event) {
            if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey
                || event.shiftKey || event.altKey) {
                return;
            }

            var link = closest(event.target, 'a[data-async-link]');

            if (!link) {
                return;
            }

            if (link.target && link.target !== '_self') {
                return;
            }

            var href = link.getAttribute('href') || '';

            if (href === '' || href.charAt(0) === '#') {
                return;
            }

            event.preventDefault();

            update(link.href, {
                trigger: link,
                scroll: link.hasAttribute('data-async-scroll')
            });
        });
    }

    function setupAsyncForms() {
        document.addEventListener('submit', function (event) {
            var form = event.target;

            if (!form || form.nodeType !== 1 || typeof form.matches !== 'function') {
                return;
            }

            if (!form.matches('form[data-async-form]')) {
                return;
            }

            // A declined confirmation already cancelled the submit.
            if (event.defaultPrevented) {
                return;
            }

            event.preventDefault();

            var buttons = form.querySelectorAll('button[type="submit"]');

            buttons.forEach(function (button) {
                button.disabled = true;
            });

            var method = (form.getAttribute('method') || 'GET').toUpperCase();

            var request = method === 'GET'
                ? update(pageUrlFor(form), { trigger: form })
                : update(form.getAttribute('action') || window.location.href, {
                    method: 'POST',
                    body: new FormData(form),
                    trigger: form
                });

            request.then(function () {
                // The swap may have replaced the buttons; the old ones are only
                // re-enabled when they are still in the document.
                buttons.forEach(function (button) {
                    button.disabled = false;
                });
            });
        });
    }

    /* A filter strip submits itself when a chip, a select or a date changes, so
       the employee does not have to press a button after every click. Text
       fields keep their own button (and the <noscript> one without JS). */
    function setupAsyncChanges() {
        document.addEventListener('change', function (event) {
            var field = event.target;

            if (!field || typeof field.matches !== 'function') {
                return;
            }

            if (!field.matches('input[type="checkbox"], input[type="date"], input[type="radio"], select')) {
                return;
            }

            var form = closest(field, 'form[data-async-form][data-async-on-change]');

            if (!form) {
                return;
            }

            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
            }
        });
    }

    /* A filter chip is a toggle: the same press that turns a stage on turns it
       off again. A radio cannot be switched off by pressing it once more, so the
       chip that matches the address bar drops its value instead of re-sending
       it — the bar is the record of what is on, because every in-place update
       replaces it (reflectUrl). */
    function clearFilterByChip(chip) {
        var url = new URL(window.location.href);

        if (!chip.name || url.searchParams.get(chip.name) !== chip.value) {
            return false;
        }

        url.searchParams.delete(chip.name);
        url.searchParams.delete('page');

        update(url.toString(), { trigger: chip });

        return true;
    }

    function setupFilterToggles() {
        document.addEventListener('click', function (event) {
            var chip = closest(event.target, 'input[type="radio"][data-filter-toggle]');

            if (chip) {
                clearFilterByChip(chip);
            }
        });

        /* Space on a radio that is already chosen fires no click (Chrome), so
           the keyboard needs the same press handled here — otherwise a filter
           could be switched on but never off without reaching for the link. */
        document.addEventListener('keydown', function (event) {
            if (event.repeat || event.key !== ' ') {
                return;
            }

            var chip = closest(event.target, 'input[type="radio"][data-filter-toggle]');

            if (!chip || !chip.checked) {
                return;
            }

            if (clearFilterByChip(chip)) {
                event.preventDefault();
            }
        });
    }

    /* One search field, at the top of the page. On the queue it filters the list
       while typing (debounced, no Enter needed); on every other screen Enter
       opens the queue with the same term. An empty field brings the normal queue
       back. The stage filter is taken from the address bar, so a chip chosen a
       moment ago is never lost. */
    function setupLiveSearch() {
        var form = document.querySelector('form[data-live-search]');
        var results = document.querySelector('[data-async-region="queue-results"]');

        if (!form || !results) {
            return;
        }

        var input = form.querySelector('input[name="q"]');

        if (!input) {
            return;
        }

        var timer = null;
        var lastUrl = null;

        function targetUrl() {
            var term = input.value.trim();

            if (term.length < MIN_TERM) {
                term = '';
            }

            var url = new URL(window.location.href);

            if (term === '') {
                url.searchParams.delete('q');
            } else {
                url.searchParams.set('q', term);
            }

            url.searchParams.delete('page');

            return url.toString();
        }

        function load() {
            var url = targetUrl();

            if (url === lastUrl) {
                return;
            }

            lastUrl = url;

            update(url, { trigger: input }).then(function (applied) {
                // A failed request must be retryable with the same phrase.
                if (!applied) {
                    lastUrl = null;
                }
            });
        }

        function schedule() {
            // Keep the hidden term of the stage form in step with the field even
            // before the debounced request lands: a chip clicked in that window
            // would otherwise submit the previous phrase. A term the server would
            // ignore (shorter than MIN_TERM) is dropped, so the address bar never
            // claims a search that did not happen.
            var hidden = document.querySelector('[data-async-region="queue-filter"] input[name="q"]');

            if (hidden) {
                var typed = input.value.trim();
                hidden.value = typed.length >= MIN_TERM ? typed : '';
            }

            window.clearTimeout(timer);
            timer = window.setTimeout(load, 250);
        }

        input.addEventListener('input', schedule);
        input.addEventListener('change', schedule);
        input.addEventListener('search', schedule);

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            window.clearTimeout(timer);
            load();
        });
    }

    /* ------------------------------------------------------------------ */
    /* Deadline picker                                                     */
    /* ------------------------------------------------------------------ */

    /* The server accepts two fields: `planned_days` (1–365, counted from today at
       15:00) or `planned_at`. The radio group only decides which one travels, so
       the hidden `planned_days` input keeps a usable value and "inna data" is the
       only mode that switches it off. Without this script the default preset is
       still submitted, which is why every panel is rendered open from the server. */
    function setupDeadline() {
        var root = document.querySelector('[data-deadline]');

        if (!root) {
            return;
        }

        var daysField = root.querySelector('[data-deadline-days]');
        var numberField = root.querySelector('[data-deadline-number]');
        var dateField = root.querySelector('input[name="planned_at"]');
        var daysPanel = root.querySelector('[data-deadline-panel="days"]');
        var datePanel = root.querySelector('[data-deadline-panel="date"]');
        var preview = root.querySelector('[data-deadline-preview]');
        var modes = root.querySelectorAll('[data-deadline-mode]');

        if (!daysField || !numberField || !dateField) {
            return;
        }

        var weekdays = ['niedz', 'pon', 'wt', 'śr', 'czw', 'pt', 'sob'];

        function pad(value) {
            return value < 10 ? '0' + value : String(value);
        }

        function format(date) {
            return weekdays[date.getDay()] + ' ' + pad(date.getDate()) + '.' + pad(date.getMonth() + 1)
                + ', ' + pad(date.getHours()) + ':' + pad(date.getMinutes());
        }

        function clampDays(value) {
            var days = parseInt(value, 10);

            if (!isFinite(days)) {
                return 2;
            }

            return Math.min(365, Math.max(1, days));
        }

        function selectedMode() {
            var mode = null;

            Array.prototype.forEach.call(modes, function (radio) {
                if (radio.checked) {
                    mode = radio.value;
                }
            });

            return mode;
        }

        function previewFromDays(days) {
            if (!preview) {
                return;
            }

            var date = new Date();
            date.setDate(date.getDate() + days);
            date.setHours(15, 0, 0, 0);
            preview.textContent = format(date);
        }

        function previewFromValue(value) {
            if (!preview) {
                return;
            }

            var match = /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})/.exec(value || '');

            if (!match) {
                preview.textContent = '—';

                return;
            }

            preview.textContent = format(new Date(
                Number(match[1]), Number(match[2]) - 1, Number(match[3]),
                Number(match[4]), Number(match[5])
            ));
        }

        function sync() {
            var mode = selectedMode() || '2';
            var byDate = mode === 'date';
            var byNumber = mode === 'custom';

            if (daysPanel) {
                daysPanel.hidden = !byNumber;
            }

            if (datePanel) {
                datePanel.hidden = !byDate;
            }

            if (byDate) {
                daysField.disabled = true;
                dateField.disabled = false;
                dateField.required = true;
                previewFromValue(dateField.value);

                return;
            }

            daysField.disabled = false;
            dateField.disabled = true;
            dateField.required = false;

            var days = byNumber ? clampDays(numberField.value) : clampDays(mode);

            if (!byNumber) {
                numberField.value = days;
            }

            daysField.value = days;
            previewFromDays(days);
        }

        Array.prototype.forEach.call(modes, function (radio) {
            radio.addEventListener('change', sync);
        });

        numberField.addEventListener('input', function () {
            if (selectedMode() !== 'custom') {
                return;
            }

            daysField.value = clampDays(numberField.value);
            previewFromDays(clampDays(numberField.value));
        });

        numberField.addEventListener('change', function () {
            if (selectedMode() !== 'custom') {
                return;
            }

            numberField.value = clampDays(numberField.value);
            daysField.value = numberField.value;
            previewFromDays(clampDays(numberField.value));
        });

        dateField.addEventListener('input', function () {
            if (selectedMode() === 'date') {
                previewFromValue(dateField.value);
            }
        });

        sync();
    }

    /* ------------------------------------------------------------------ */
    /* Copy to clipboard                                                   */
    /* ------------------------------------------------------------------ */

    /* `data-copy-value` carries the text, `data-copy-target` names the field to
       read it from when the value is already on screen. No inline JS: the strict
       CSP leaves this file as the only place behaviour can live. */
    function copyText(text) {
        if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
            return navigator.clipboard.writeText(text);
        }

        return new Promise(function (resolve, reject) {
            var field = document.createElement('textarea');
            field.value = text;
            field.setAttribute('readonly', 'readonly');
            field.style.position = 'fixed';
            field.style.top = '-1000px';
            document.body.appendChild(field);
            field.select();

            try {
                document.execCommand('copy') ? resolve() : reject(new Error('copy failed'));
            } catch (error) {
                reject(error);
            } finally {
                document.body.removeChild(field);
            }
        });
    }

    function setupCopyButtons() {
        document.addEventListener('click', function (event) {
            var button = closest(event.target, '[data-copy-value], [data-copy-target]');

            if (!button) {
                return;
            }

            var text = button.getAttribute('data-copy-value');
            var targetId = button.getAttribute('data-copy-target');

            if (!text && targetId) {
                var field = document.getElementById(targetId);
                text = field ? (typeof field.value === 'string' && field.value !== ''
                    ? field.value
                    : field.textContent) : '';
            }

            if (!text) {
                return;
            }

            event.preventDefault();

            copyText(text).then(function () {
                announce('Skopiowano link do schowka.');
                var label = button.getAttribute('data-copy-label') || squash(button.textContent);

                button.setAttribute('data-copy-label', label);
                button.textContent = 'Skopiowano';

                window.setTimeout(function () {
                    button.textContent = button.getAttribute('data-copy-label');
                }, 2200);
            }).catch(function () {
                announce('Nie udało się skopiować — zaznacz link i skopiuj go ręcznie.');
            });
        });
    }

    /* A failed submit is announced at the top of the form, so put the keyboard
       there instead of leaving it on a field that may be off screen. */
    function focusErrorSummary() {
        var summary = document.querySelector('[data-error-summary]');

        if (!summary || !summary.hasAttribute('tabindex')) {
            return;
        }

        try {
            summary.focus({ preventScroll: false });
        } catch (error) {
            summary.focus();
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        setupPasswordToggles();
        setupConfirmations();
        setupCardNavigation();
        setupOfflineState();
        setupLogoutCacheCleanup();
        setupAsyncLinks();
        setupAsyncForms();
        setupAsyncChanges();
        setupFilterToggles();
        setupLiveSearch();
        setupDeadline();
        setupCopyButtons();
        focusErrorSummary();
        scheduleFlashDismissal();
    });
})();
