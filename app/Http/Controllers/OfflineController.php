<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\View\View;

/**
 * Offline shell (F-44). It contains no customer data at all — the service worker
 * only ever caches this page, the CSS and the JS (defect D-28: the old cache held
 * dashboard HTML with phone numbers and laptop passwords).
 */
final class OfflineController extends Controller
{
    public function __invoke(): View
    {
        return view('offline');
    }
}
