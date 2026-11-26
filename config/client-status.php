<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Customer status page (CMS `sk.mati-it.pl`) — decision D19/0019
    |--------------------------------------------------------------------------
    |
    | The workshop app is the source of truth about an order; the CMS owns the
    | public status page, its tokens and the SMS queue. After every acceptance or
    | status change the app posts a signed snapshot to the CMS and stores the
    | returned link in `services.client_status_url`.
    |
    | An empty secret turns the integration off: orders are saved as usual, they
    | simply have no public link and nothing is sent anywhere.
    |
    */

    'enabled' => (bool) env('CLIENT_STATUS_ENABLED', false),

    // Base address only — the endpoint `/api/service-sync.php` is appended.
    'url' => env('CLIENT_STATUS_URL', 'https://sk.mati-it.pl'),

    // Shared HMAC secret. Never committed: `.env` only, empty by default.
    'secret' => env('CLIENT_STATUS_SECRET', ''),

    // Seconds; the sync never blocks a workshop action for longer than this.
    'timeout' => (int) env('CLIENT_STATUS_TIMEOUT', 5),

];
