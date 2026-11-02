<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Release form (F-39). The release date is part of the form with "now" as the
 * default and a "not from the future" rule (defects D-11, D-56).
 */
class ReleaseServiceRequest extends FormRequest
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
            'released_at' => ['required', 'date'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'cost' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'seal_number' => ['nullable', 'string', 'max:50', 'regex:/^[A-Z0-9\-\/ ]*$/'],
            'device_password' => ['nullable', 'string', 'max:100'],
            'serial_number' => ['nullable', 'string', 'max:100'],
            'print_protocol' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'released_at.required' => 'Podaj datę wydania.',
            'released_at.date' => 'Data wydania musi być poprawną datą.',
            'price.required' => 'Podaj cenę dla klienta.',
            'price.numeric' => 'Cena musi być liczbą.',
            'cost.numeric' => 'Koszt części musi być liczbą.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (['price', 'cost'] as $field) {
            if ($this->has($field) && is_string($this->input($field))) {
                $value = str_replace([' ', "\u{00A0}", 'zł'], '', (string) $this->input($field));
                $normalized[$field] = str_replace(',', '.', $value);
            }
        }

        foreach (['notes', 'serial_number', 'device_password', 'seal_number'] as $field) {
            if ($this->has($field) && is_string($this->input($field))) {
                $value = trim((string) $this->input($field));
                $normalized[$field] = $field === 'seal_number' ? mb_strtoupper($value) : $value;
            }
        }

        if ($this->has('released_at') && is_string($this->input('released_at'))) {
            $normalized['released_at'] = ServiceRequest::toWallClock((string) $this->input('released_at'));
        }

        $normalized['print_protocol'] = $this->boolean('print_protocol');

        $this->merge($normalized);
    }

    /** Default value for the form: now, in local wall clock time. */
    public static function defaultReleasedAt(): string
    {
        return CarbonImmutable::now((string) config('app.display_timezone'))->format('Y-m-d\TH:i:s');
    }

    public function releasedAtIsFromTheFuture(): bool
    {
        $value = (string) $this->input('released_at', '');

        try {
            return CarbonImmutable::parse($value, (string) config('app.display_timezone'))->isFuture();
        } catch (\Throwable) {
            return false;
        }
    }
}
