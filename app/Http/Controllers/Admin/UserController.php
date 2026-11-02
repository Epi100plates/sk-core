<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Contracts\UserRepository;
use App\Domain\Data\UserRecord;
use App\Domain\Enums\Ability;
use App\Domain\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\UserRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * Account management for the admin role (F-06, defect D-25: a new employee used
 * to require hand written SQL). Employee and technician get a 403.
 */
final class UserController extends Controller
{
    public function index(UserRepository $users): View
    {
        $this->authorize('viewAny', UserRecord::class);

        $page = max(1, (int) request()->query('page', '1'));

        return view('admin.users.index', [
            'page' => $users->paginate(20, $page),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', UserRecord::class);

        return view('admin.users.form', [
            'user' => null,
            'roles' => Role::options(),
        ]);
    }

    public function store(UserRequest $request, UserRepository $users): RedirectResponse
    {
        $this->authorize('create', UserRecord::class);

        $user = UserRecord::fromArray([
            'name' => (string) $request->validated('name'),
            'login' => (string) $request->validated('login'),
            'password' => Hash::make((string) $request->validated('password')),
            'role' => (string) $request->validated('role'),
            'is_active' => $request->boolean('is_active'),
            'must_change_password' => true,
        ]);

        $users->save($user);

        return redirect()
            ->route('admin.users.index')
            ->with('status', 'Konto „'.$user->login.'” zostało utworzone.');
    }

    public function edit(UserRecord $user): View
    {
        $this->authorize('update', $user);

        return view('admin.users.form', [
            'user' => $user,
            'roles' => Role::options(),
        ]);
    }

    public function update(UserRequest $request, UserRecord $user, UserRepository $users): RedirectResponse
    {
        $this->authorize('update', $user);

        $changes = [
            'name' => (string) $request->validated('name'),
            'login' => (string) $request->validated('login'),
            'role' => (string) $request->validated('role'),
            'is_active' => $request->boolean('is_active'),
        ];

        $password = $request->validated('password');

        if (is_string($password) && $password !== '') {
            $changes['password'] = Hash::make($password);
            $changes['must_change_password'] = true;
        }

        $updated = $users->save($user->with($changes));

        return redirect()
            ->route('admin.users.index')
            ->with('status', 'Konto „'.$updated->login.'” zostało zaktualizowane.');
    }

    public function toggle(Request $request, UserRecord $user, UserRepository $users): RedirectResponse
    {
        $this->authorize('update', $user);

        $current = $request->user();

        if ($current instanceof UserRecord && $current->id === $user->id) {
            return back()->withErrors(['login' => 'Nie możesz zablokować własnego konta.']);
        }

        $updated = $users->save($user->with(['is_active' => ! $user->isActive]));

        return back()->with('status', $updated->isActive
            ? 'Konto „'.$updated->login.'” jest aktywne.'
            : 'Konto „'.$updated->login.'” zostało zablokowane.');
    }

    /**
     * Kept public for the Blade view: one place lists the rights of every role.
     *
     * @return array<string, array<string, bool>>
     */
    public static function abilityMatrix(): array
    {
        $matrix = [];

        foreach (Role::cases() as $role) {
            foreach (Ability::all() as $ability) {
                $matrix[$role->value][$ability] = $role->can($ability);
            }
        }

        return $matrix;
    }
}
