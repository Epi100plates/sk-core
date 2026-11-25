<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security headers required by the acceptance criteria (section 5.4, T7.1).
 *
 * The Content-Security-Policy is strict: there is no inline <script> and no
 * inline `style="..."` anywhere in the application (all CSS lives in
 * public/css, all JS in public/js), so `unsafe-inline` is never needed.
 */
final class AddSecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        return self::applyTo($next($request), $request);
    }

    /**
     * Also used by the exception handler (`$exceptions->respond`), because a
     * response rendered for a 404/419/429/500 never passes back through the
     * middleware stack.
     */
    public static function applyTo(Response $response, Request $request): Response
    {
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=(self)');
        $response->headers->set('Content-Security-Policy', implode('; ', [
            "default-src 'self'",
            "img-src 'self' data: blob:",
            "style-src 'self'",
            "script-src 'self'",
            "font-src 'self'",
            "connect-src 'self'",
            "object-src 'none'",
            "base-uri 'none'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]));

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
