/* Opens the browser print dialog on the A4 documents (feature F-55).
   Triggered by `data-autoprint="true"` on <body> of the print layouts. */
(function () {
    'use strict';

    var button = document.querySelector('[data-print-trigger]');

    if (button) {
        button.addEventListener('click', function () {
            window.print();
        });
    }
})();
