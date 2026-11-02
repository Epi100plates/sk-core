<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Login throttling (defect D-07)
    |--------------------------------------------------------------------------
    |
    | 5 attempts per 15 minutes for one login + IP address. The values are
    | configurable so browser tests can sign in repeatedly; production keeps the
    | defaults from section 5.4 of the plan.
    |
    */

    'login_attempts' => (int) env('LOGIN_ATTEMPTS', 5),

    'login_decay_minutes' => (int) env('LOGIN_DECAY_MINUTES', 15),

    /*
    |--------------------------------------------------------------------------
    | Mutation throttling
    |--------------------------------------------------------------------------
    |
    | 60 writes per minute for a signed-in employee. A real shift never comes
    | close; the limit only stops a runaway script.
    |
    */

    'mutation_attempts_per_minute' => (int) env('MUTATION_ATTEMPTS', 60),
];
