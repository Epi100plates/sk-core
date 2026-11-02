<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Data\UserRecord;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * An account created by the installer or reset by an admin must choose a new
 * password before working (section 5.4, point 1).
 */
final class RequirePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof UserRecord && $user->mustChangePassword
            && ! $request->routeIs('account.password', 'account.password.update', 'logout')) {
            return redirect()
                ->route('account.password')
                ->with('warning', 'Ustaw nowe hasło, zanim zaczniesz pracę.');
        }

        return $next($request);
    }
}
