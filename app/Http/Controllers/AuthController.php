<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\UserRepository;
use App\Domain\Data\UserRecord;
use App\Http\Requests\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        // Throttling lives in one place only: the `throttle:login` route
        // middleware (5 attempts / 15 minutes, keyed by login + IP). Counting
        // again here would halve the real allowance.
        if (! Auth::attempt($credentials)) {
            return back()
                ->withInput($request->only('login'))
                ->withErrors(['login' => 'Nieprawidłowy login lub hasło.']);
        }

        $request->session()->regenerate();

        $user = Auth::user();

        if ($user instanceof UserRecord) {
            $users->save($user->with(['last_login_at' => now()->utc()->format('Y-m-d H:i:s')]));
        }

        return redirect()->to($this->intendedUrl($request));
    }

    /**
     * `url.intended` comes from the request URL, so a spoofed Host header could
     * otherwise send the employee to an external site after logging in.
     */
    private function intendedUrl(Request $request): string
    {
        $fallback = route('services.queue');
        $intended = $request->session()->pull('url.intended');

        if (! is_string($intended) || $intended === '') {
            return $fallback;
        }

        $host = parse_url($intended, PHP_URL_HOST);

        if (! is_string($host) || mb_strtolower($host) !== mb_strtolower($request->getHost())) {
            return $fallback;
        }

        return $intended;
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
