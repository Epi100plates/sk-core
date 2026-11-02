<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\UserRepository;
use App\Domain\Data\UserRecord;
use App\Http\Requests\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

/**
 * Login and logout (F-01…F-05).
 *
 * Failures always produce the same generic message and the route is throttled
 * to 5 attempts / 15 minutes, so accounts cannot be enumerated (defect D-07).
 */
final class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(LoginRequest $request, UserRepository $users): RedirectResponse
    {
        $credentials = [
            'login' => (string) $request->validated('login'),
            'password' => (string) $request->validated('password'),
        ];

        $key = mb_strtolower($credentials['login']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            return back()
                ->withInput($request->only('login'))
                ->withErrors(['login' => 'Zbyt wiele prób logowania. Spróbuj ponownie za '.$seconds.' s.']);
        }

        if (! Auth::attempt($credentials)) {
            RateLimiter::hit($key, 15 * 60);

            return back()
                ->withInput($request->only('login'))
                ->withErrors(['login' => 'Nieprawidłowy login lub hasło.']);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        $user = Auth::user();

        if ($user instanceof UserRecord) {
            $users->save($user->with(['last_login_at' => now()->utc()->format('Y-m-d H:i:s')]));
        }

        return redirect()->intended(route('services.queue'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('login')
            ->with('status', 'Wylogowano.');
    }
}
