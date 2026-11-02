<?php

declare(strict_types=1);

use App\Domain\Exceptions\DataStoreCorruptedException;
use App\Domain\Exceptions\VersionConflictException;
use App\Http\Middleware\AddSecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            AddSecurityHeaders::class,
        ]);

        $middleware->redirectGuestsTo(fn (): string => route('login'));

        // Every mutation is CSRF protected; there are no exceptions (defect D-05).
        $middleware->validateCsrfTokens(except: []);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => $request->expectsJson() || $request->is('api/*'),
        );

        // Workflow violations and editing rules are business answers, not bugs:
        // 422 with a readable Polish message (F-24, F-33, F-35, F-56).
        $exceptions->render(function (DomainException $exception, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $exception->getMessage()], 422);
            }

            return response()->view('errors.domain', [
                'message' => $exception->getMessage(),
                'title' => 'Operacja niedozwolona',
            ], 422);
        });

        // Two people editing the same service: 409 and a hint to refresh (plan 5.7).
        $exceptions->render(function (VersionConflictException $exception, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $exception->getMessage()], 409);
            }

            return response()->view('errors.domain', [
                'message' => $exception->getMessage(),
                'title' => 'Konflikt wersji',
            ], 409);
        });

        // A broken JSON data file must never be silently overwritten.
        $exceptions->render(function (DataStoreCorruptedException $exception) {
            return response()->view('errors.domain', [
                'message' => $exception->getMessage(),
                'title' => 'Problem z plikiem danych',
            ], 500);
        });
    })->create();
