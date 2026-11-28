<?php

declare(strict_types=1);

use App\Http\Controllers\Api\IntakeTicketsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Machine endpoints (no session, no CSRF)
|--------------------------------------------------------------------------
|
| Only the public CMS talks here. Everything the employee does lives in
| routes/web.php behind `auth`; these routes authenticate with an HMAC signature
| and carry their own rate limiter.
|
*/

Route::post('/intake-tickets', IntakeTicketsController::class)
    ->middleware('throttle:intake')
    ->name('api.intake-tickets');
