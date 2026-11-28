<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Submissions from the public site form (owner request 2026-11-27)
    |--------------------------------------------------------------------------
    |
    | The public CMS (sk.mati-it.pl) owns the `tickets` table for the site form.
    | After storing a submission it posts the ticket here, signed with a shared
    | HMAC secret. The app then shows it in the "Zgłoszenia" inbox under "Wydane".
    |
    | An empty secret turns the endpoint off with 503 integration_disabled: the
    | app works as before, nothing is accepted from outside.
    |
    */

    'secret' => env('INTAKE_SYNC_SECRET', ''),

    // Refuse a body bigger than this before decoding it (bytes).
    'max_body' => (int) env('INTAKE_SYNC_MAX_BODY', 20000),

    // Accepted difference between the sender's timestamp and our clock (seconds).
    'clock_skew' => (int) env('INTAKE_SYNC_CLOCK_SKEW', 300),

    // Signed deliveries per minute per caller address.
    'rate_limit' => (int) env('INTAKE_SYNC_RATE_LIMIT', 60),

];
