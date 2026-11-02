/* Accessible photo lightbox (feature F-47, defect D-34).
   One dialog for the whole page, keyboard support (arrows, ESC), swipe on touch,
   focus trapped inside the dialog and returned to the thumbnail on close.
   Listeners are attached once, so refreshing the gallery cannot duplicate them. */
(function () {
    'use strict';

    var dialog = null;
    var image = null;
    var counter = null;
    var closeButton = null;
    var links = [];
    var current = 0;
    var lastFocused = null;
    var touchStartX = null;

    function groupLinks(link) {
        var group = link.getAttribute('data-lightbox') || 'default';

        return Array.prototype.slice.call(
            document.querySelectorAll('a[data-lightbox="' + group + '"]')
        );
    }

    function open(index, linkList) {
        if (!dialog || !image) {
            return;
        }

        links = linkList;
        current = index;
        lastFocused = document.activeElement;
        render();
        dialog.hidden = false;
        document.body.classList.add('overflow-hidden');

        if (closeButton) {
            closeButton.focus();
        }
    }

    function close() {
        if (!dialog) {
            return;
        }

        dialog.hidden = true;
        document.body.classList.remove('overflow-hidden');
        image.setAttribute('src', '');

        if (lastFocused && typeof lastFocused.focus === 'function') {
            lastFocused.focus();
        }
    }

    function render() {
        var link = links[current];

        if (!link || !image) {
            return;
        }

        image.setAttribute('src', link.getAttribute('href'));

        var alt = link.getAttribute('data-alt') || 'Zdjęcie zlecenia';
        image.setAttribute('alt', alt);

        if (counter) {
            counter.textContent = (current + 1) + ' / ' + links.length;
        }
    }

    function step(delta) {
        if (links.length < 2) {
            return;
        }

        current = (current + delta + links.length) % links.length;
        render();
    }

    function onKeydown(event) {
        if (!dialog || dialog.hidden) {
            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            close();
            return;
        }

        if (event.key === 'ArrowRight') {
            event.preventDefault();
            step(1);
            return;
        }

        if (event.key === 'ArrowLeft') {
            event.preventDefault();
            step(-1);
            return;
        }

        if (event.key === 'Tab') {
            var focusable = dialog.querySelectorAll('button, [href], [tabindex]:not([tabindex="-1"])');

            if (focusable.length === 0) {
                return;
            }

            var first = focusable[0];
            var last = focusable[focusable.length - 1];

            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        }
    }

    function setup() {
        dialog = document.getElementById('lightbox');

        if (!dialog) {
            return;
        }

        image = dialog.querySelector('[data-lightbox-image]');
        counter = dialog.querySelector('[data-lightbox-counter]');
        closeButton = dialog.querySelector('[data-lightbox-close]');

        document.querySelectorAll('a[data-lightbox]').forEach(function (link) {
            link.addEventListener('click', function (event) {
                event.preventDefault();
                var list = groupLinks(link);
                open(list.indexOf(link), list);
            });
        });

        dialog.querySelectorAll('[data-lightbox-close]').forEach(function (button) {
            button.addEventListener('click', close);
        });

        dialog.querySelectorAll('[data-lightbox-prev]').forEach(function (button) {
            button.addEventListener('click', function () {
                step(-1);
            });
        });

        dialog.querySelectorAll('[data-lightbox-next]').forEach(function (button) {
            button.addEventListener('click', function () {
                step(1);
            });
        });

        dialog.addEventListener('click', function (event) {
            if (event.target === dialog) {
                close();
            }
        });

        dialog.addEventListener('touchstart', function (event) {
            touchStartX = event.changedTouches[0].clientX;
        }, { passive: true });

        dialog.addEventListener('touchend', function (event) {
            if (touchStartX === null) {
                return;
            }

            var delta = event.changedTouches[0].clientX - touchStartX;
            touchStartX = null;

            if (Math.abs(delta) > 40) {
                step(delta < 0 ? 1 : -1);
            }
        }, { passive: true });

        document.addEventListener('keydown', onKeydown);
        dialog.hidden = true;
    }

    document.addEventListener('DOMContentLoaded', setup);
})();
