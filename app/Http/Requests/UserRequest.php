<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Contracts\UserRepository;
use App\Domain\Data\UserRecord;
use App\Domain\Enums\Role;
use App\Rules\UniqueLogin;
use Illuminate\Foundation\Http\FormRequest;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $user = $this->route('user');
        $ignoreId = $user instanceof UserRecord ? $user->id : null;
        $creating = $this->isMethod('post');

        return [
            'name' => ['required', 'string', 'max:100'],
            'login' => [
                'required', 'string', 'max:50', 'regex:/^[a-zA-Z0-9._-]+$/',
                new UniqueLogin(app(UserRepository::class), $ignoreId),
            ],
            'role' => ['required', 'string', 'in:'.implode(',', array_keys(Role::options()))],
            'password' => [$creating ? 'required' : 'nullable', 'string', 'min:12', 'confirmed'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Podaj imię lub nazwę konta.',
            'login.required' => 'Podaj login.',
            'login.regex' => 'Login może zawierać tylko litery, cyfry, kropkę, myślnik i podkreślenie.',
            'role.required' => 'Wybierz rolę.',
            'role.in' => 'Wybierz jedną z ról.',
            'password.required' => 'Podaj hasło startowe.',
            'password.min' => 'Hasło musi mieć co najmniej 12 znaków.',
            'password.confirmed' => 'Podane hasła nie są identyczne.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'login' => is_string($this->input('login')) ? trim((string) $this->input('login')) : null,
            'name' => is_string($this->input('name')) ? trim((string) $this->input('name')) : null,
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
