/* Opens the browser print dialog on the A4 documents (feature F-55).
 *
 * The document itself carries `data-autoprint="true"`, so opening it from the
 * card starts printing immediately. `?print=0` suppresses that one time — the
 * automated browser tests use it to assert the page content without a dialog.
 */
(function () {
    'use strict';

    var button = document.querySelector('[data-print-trigger]');

    if (button) {
        button.addEventListener('click', function () {
            window.print();
        });
    }

    var params = new URLSearchParams(window.location.search);
    var autoprint = document.body.getAttribute('data-autoprint') === 'true';

    if (autoprint && params.get('print') !== '0') {
        window.print();
    }
})();
