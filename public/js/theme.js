/* Tryb jasny/ciemny (2026-11-28).
 *
 * Ładowany synchronicznie w <head>, żeby atrybut data-theme był ustawiony przed
 * pierwszym malowaniem — bez migotania. CSP aplikacji jest ścisłe (script-src
 * 'self'), dlatego to osobny plik, a nie skrypt inline.
 *
 * Zapisany wybór (localStorage, klucz sk-theme) ma pierwszeństwo przed
 * preferencją systemową; bez zapisu motyw idzie za systemem i reaguje na jego
 * zmianę. Paleta i reguły: public/css/app.css, blok :root[data-theme='dark'].
 */
(function () {
    'use strict';

    var STORAGE_KEY = 'sk-theme';
    var root = document.documentElement;
    var media = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;

    function readStored() {
        try {
            var value = window.localStorage.getItem(STORAGE_KEY);

            return value === 'dark' || value === 'light' ? value : null;
        } catch (error) {
            // Tryb prywatny: motyw działa do końca sesji.
            return null;
        }
    }

    function writeStored(theme) {
        try {
            window.localStorage.setItem(STORAGE_KEY, theme);
        } catch (error) {
            // Bez zapisu — wybór obowiązuje do końca sesji.
        }
    }

    /* Ustawia motyw i doprowadza do niego wszystkie zależne elementy: pasek
       przeglądarki (meta theme-color) oraz stan przełącznika (aria-pressed,
       etykieta i podpowiedź), żeby czytnik ekranu mówił prawdę. */
    function apply(theme) {
        var dark = theme === 'dark';

        if (dark) {
            root.setAttribute('data-theme', 'dark');
            root.setAttribute('data-bs-theme', 'dark');
        } else {
            root.setAttribute('data-theme', 'light');
            root.setAttribute('data-bs-theme', 'light');
        }

        var meta = document.querySelector('meta[name="theme-color"]');

        if (meta) {
            var color = meta.getAttribute(dark ? 'data-theme-color-dark' : 'data-theme-color-light');

            if (color) {
                meta.setAttribute('content', color);
            }
        }

        var toggles = document.querySelectorAll('[data-theme-toggle]');

        for (var i = 0; i < toggles.length; i++) {
            toggles[i].setAttribute('aria-pressed', dark ? 'true' : 'false');
            toggles[i].setAttribute('aria-label', dark ? 'Włącz tryb jasny' : 'Włącz tryb ciemny');
            toggles[i].setAttribute('title', dark ? 'Tryb jasny' : 'Tryb ciemny');
        }
    }

    function current() {
        return root.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
    }

    apply(readStored() || (media && media.matches ? 'dark' : 'light'));

    /* W <head> przycisk przełącznika jeszcze nie istnieje, więc stan etykiet
       ustawiamy ponownie po sparsowaniu dokumentu. */
    function syncToggles() {
        apply(current());
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', syncToggles);
    } else {
        syncToggles();
    }

    /* Delegacja na dokumencie: przełącznik działa też po podmianie fragmentu
       strony przez warstwę asynchroniczną (app.js). */
    document.addEventListener('click', function (event) {
        var target = event.target;

        if (!target || typeof target.closest !== 'function') {
            return;
        }

        var button = target.closest('[data-theme-toggle]');

        if (!button) {
            return;
        }

        event.preventDefault();

        var next = current() === 'dark' ? 'light' : 'dark';

        writeStored(next);
        apply(next);
    });

    if (media) {
        var followSystem = function (event) {
            if (readStored()) {
                return;
            }

            apply(event.matches ? 'dark' : 'light');
        };

        if (typeof media.addEventListener === 'function') {
            media.addEventListener('change', followSystem);
        } else if (typeof media.addListener === 'function') {
            media.addListener(followSystem);
        }
    }
})();
