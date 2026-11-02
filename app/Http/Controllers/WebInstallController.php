<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Install\Installer;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Web installer for the shared host, where there is no SSH (F-52, decision D2).
 *
 * Without a configured `INSTALL_TOKEN` the route does not exist at all (404);
 * after installation the token must be emptied in `.env`.
 */
final class WebInstallController extends Controller
{
    public function __invoke(Request $request, Installer $installer): View
    {
        $token = (string) config('app.install_token', '');
        $given = (string) $request->query('token', '');

        abort_if($token === '' || ! hash_equals($token, $given), 404);

        return view('install.result', [
            'result' => $installer->install(),
        ]);
    }
}
