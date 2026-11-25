/* Accessible photo lightbox (feature F-47, defect D-34).
   One dialog for the whole page, keyboard support (arrows, ESC), swipe on touch,
   focus trapped inside the dialog and returned to the thumbnail on close.

   The gallery is replaced in place by the async photo forms, so thumbnail clicks
   are delegated on the document and the group is read from the live DOM each time
   the dialog opens: a thumbnail created after a swap behaves like the first one.
   The dialog itself lives in the layout and is bound once. */
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

        return Array.prototype.slice.call(document.querySelectorAll('a[data-lightbox]'))
            .filter(function (candidate) {
                return (candidate.getAttribute('data-lightbox') || 'default') === group;
            });
    }

    function open(index, linkList, trigger) {
        if (!dialog || !image || index < 0 || !linkList[index]) {
            return;
        }

        links = linkList;
        current = index;
        lastFocused = trigger || document.activeElement;
        render();
        dialog.hidden = false;
        document.body.classList.add('overflow-hidden');

        if (closeButton) {
            closeButton.focus();
        }
    }

    function restoreFocus() {
        if (lastFocused && document.contains(lastFocused) && typeof lastFocused.focus === 'function') {
            lastFocused.focus();

            return;
        }

        // The thumbnail can be gone: the photo was deleted and the region swapped.
        var first = document.querySelector('a[data-lightbox]');

        if (first) {
            first.focus();
        }
    }

    function close() {
        if (!dialog) {
            return;
        }

        dialog.hidden = true;
        document.body.classList.remove('overflow-hidden');
        image.setAttribute('src', '');
        restoreFocus();
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

    function onClick(event) {
        // Without the layout dialog the thumbnails must keep navigating normally.
        if (!dialog || !image) {
            return;
        }

        var target = event.target;

        if (!target || typeof target.closest !== 'function') {
            return;
        }

        var link = target.closest('a[data-lightbox]');

        if (!link) {
            return;
        }

        var list = groupLinks(link);
        var index = list.indexOf(link);

        if (index < 0) {
            return;
        }

        event.preventDefault();
        open(index, list, link);
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

        document.addEventListener('click', onClick);

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
