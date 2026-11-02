<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\UserRepository;
use App\Domain\Data\UserRecord;
use App\Http\Requests\ChangePasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * Changing your own password. Used right after the installer creates an account
 * with a random password and after an admin resets one (F-06, D-01).
 */
final class AccountController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $request->user();
        abort_unless($user instanceof UserRecord, 401);

        return view('account.password', [
            'user' => $user,
        ]);
    }

    public function update(ChangePasswordRequest $request, UserRepository $users): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof UserRecord, 401);

        $users->save($user->with([
            'password' => Hash::make((string) $request->validated('password')),
            'must_change_password' => false,
        ]));

        return redirect()
            ->route('services.queue')
            ->with('status', 'Hasło zostało zmienione.');
    }
}
