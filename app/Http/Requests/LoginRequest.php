<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
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
            'login' => ['required', 'string', 'max:50'],
            'password' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * One generic message for every failure — a wrong login and a wrong password
     * must not be distinguishable (defect D-07, account enumeration).
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'login.required' => 'Nieprawidłowy login lub hasło.',
            'password.required' => 'Nieprawidłowy login lub hasło.',
        ];
    }
}
