<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CancelServiceRequest extends FormRequest
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
            'cancellation_reason' => ['required', 'string', 'min:3', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cancellation_reason.required' => 'Podaj powód anulowania.',
            'cancellation_reason.min' => 'Powód anulowania musi mieć co najmniej 3 znaki.',
        ];
    }

    public function reason(): string
    {
        return trim((string) $this->validated('cancellation_reason'));
    }
}
