<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * "Odrzuć" on the submissions inbox (2026-11-27). A short reason is mandatory so
 * a rejected submission always explains itself.
 */
class RejectIntakeRequest extends FormRequest
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
            'rejection_reason' => ['required', 'string', 'min:3', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rejection_reason.required' => 'Podaj powód odrzucenia.',
            'rejection_reason.min' => 'Powód odrzucenia musi mieć co najmniej 3 znaki.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'rejection_reason' => is_string($this->input('rejection_reason'))
                ? trim((string) $this->input('rejection_reason'))
                : null,
        ]);
    }

    public function reason(): string
    {
        return trim((string) $this->validated('rejection_reason'));
    }
}
