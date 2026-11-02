<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChangePasswordRequest extends FormRequest
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
        return [
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', 'min:12', 'confirmed', 'different:current_password'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'current_password.required' => 'Podaj obecne hasło.',
            'current_password.current_password' => 'Obecne hasło jest nieprawidłowe.',
            'password.required' => 'Podaj nowe hasło.',
            'password.min' => 'Nowe hasło musi mieć co najmniej 12 znaków.',
            'password.confirmed' => 'Podane hasła nie są identyczne.',
            'password.different' => 'Nowe hasło musi różnić się od obecnego.',
        ];
    }
}
