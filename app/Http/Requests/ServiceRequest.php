<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\BusinessDays;
use App\Domain\Enums\ServiceStatus;
use App\Domain\ValueObjects\Phone;
use App\Rules\PhoneNumber;
use App\Support\Photos\PhotoStore;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Acceptance form (F-07…F-18) and, through `sometimes` rules, the edit form.
 *
 * Validation is shared on purpose: the same rules protect `POST /services` and
 * `PUT /services/{id}`, so the two screens cannot drift apart (defect D-41).
 */
class ServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // authorization happens through the route middleware and policies
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $creating = $this->isMethod('post');
        $required = $creating ? 'required' : 'sometimes';

        return [
            'phone' => [$required, 'string', 'max:30', new PhoneNumber],
            'device_model' => [$required, 'string', 'max:100'],
            'issue' => [$required, 'string', 'max:5000'],
            'serial_number' => ['nullable', 'string', 'max:100'],
            'device_password' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'is_express' => ['nullable', 'boolean'],
            // Deadline: either a preset/own number of days (`planned_days`, turned
            // into `planned_at` below) or an explicit date. Editing keeps both
            // optional, because a partial edit payload must not touch the promise.
            'planned_days' => ['nullable', 'integer', 'between:1,365'],
            'planned_at' => $creating
                ? ['required_without:planned_days', 'date']
                : ['nullable', 'date'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'cost' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'seal_number' => ['nullable', 'string', 'max:50', 'regex:/^[A-Z0-9\-\/ ]*$/'],
            // Every status is accepted here on purpose: choosing `released` or
            // `cancelled` from the edit form is answered by the workflow with a
            // readable 422, not by a generic validation message (F-35).
            'status' => ['nullable', 'string', 'in:'.implode(',', array_keys(ServiceStatus::options()))],
            'photos' => ['nullable', 'array', 'max:'.PhotoStore::MAX_FILES],
            'photos.*' => ['image', 'mimes:jpeg,jpg,png,webp,gif', 'max:'.PhotoStore::MAX_BYTES],
            'print_confirmation' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.required' => 'Numer telefonu jest wymagany.',
            'device_model.required' => 'Model urządzenia jest wymagany.',
            'issue.required' => 'Opis usterki jest wymagany.',
            'planned_at.required' => 'Termin realizacji jest wymagany.',
            'planned_at.date' => 'Termin realizacji musi być poprawną datą.',
            'planned_days.integer' => 'Liczba dni terminu musi być liczbą całkowitą.',
            'planned_days.between' => 'Termin realizacji można ustawić od 1 do 365 dni.',
            'price.numeric' => 'Cena musi być liczbą.',
            'cost.numeric' => 'Koszt części musi być liczbą.',
            'seal_number.regex' => 'Numer plomby może zawierać tylko litery, cyfry, myślnik i ukośnik.',
            'photos.max' => 'Można dodać maksymalnie '.PhotoStore::MAX_FILES.' zdjęć.',
            'photos.*.image' => 'Zdjęcie musi być plikiem graficznym (JPEG, PNG, WebP, GIF).',
            'photos.*.max' => 'Zdjęcie może mieć maksymalnie 10 MB.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (['phone', 'device_model', 'issue', 'serial_number', 'device_password', 'notes', 'seal_number'] as $field) {
            if ($this->has($field) && is_string($this->input($field))) {
                $value = trim((string) $this->input($field));
                $normalized[$field] = $field === 'seal_number' ? mb_strtoupper($value) : $value;
            }
        }

        foreach (['price', 'cost'] as $field) {
            if ($this->has($field) && is_string($this->input($field))) {
                $value = str_replace([' ', "\u{00A0}", 'zł'], '', (string) $this->input($field));
                $normalized[$field] = str_replace(',', '.', $value);
            }
        }

        if ($this->has('planned_at') && is_string($this->input('planned_at'))) {
            $normalized['planned_at'] = self::toWallClock((string) $this->input('planned_at'));
        }

        // A preset (1/2/3/7) or the customer's own number of days replaces whatever
        // the hidden `planned_at` field still carried: the shop promise is "3 dni",
        // the exact hour is an implementation detail of the display (F-13).
        $days = $this->input('planned_days');

        if (is_numeric($days) && (int) $days >= 1 && (int) $days <= 365) {
            $normalized['planned_at'] = self::plannedAtFromDays((int) $days);
        }

        $normalized['is_express'] = $this->boolean('is_express');

        if ($this->has('print_confirmation')) {
            $normalized['print_confirmation'] = $this->boolean('print_confirmation');
        }

        $this->merge($normalized);
    }

    /**
     * Converts `<input type="datetime-local">` (local wall clock) into a
     * timezone free "Y-m-d H:i:s" string. Interpreting it in the display timezone
     * happens exactly once, in the actions — this closes defects D-24/D-44/D-45
     * where the browser converted to UTC twice and moved the promised deadline.
     */
    public static function toWallClock(string $value): string
    {
        $timezone = (string) config('app.display_timezone');

        try {
            return CarbonImmutable::parse($value, $timezone)->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return $value;
        }
    }

    public static function fromRecordToInput(?CarbonInterface $value): string
    {
        if ($value === null) {
            return '';
        }

        $timezone = (string) config('app.display_timezone');
        $local = CarbonImmutable::instance($value)->timezone($timezone);

        // Seconds only when they are not zero: a promised date reads "16:00",
        // not "16:00:00", but a stored instant with seconds must still round
        // trip exactly, otherwise every save would "move" the deadline (D-44).
        return $local->second === 0
            ? $local->format('Y-m-d\TH:i')
            : $local->format('Y-m-d\TH:i:s');
    }

    /** Default planned date: the next working day at 15:00 local time (F-13). */
    public static function defaultPlannedAt(): string
    {
        $timezone = (string) config('app.display_timezone');

        return BusinessDays::add(CarbonImmutable::now($timezone), 1)->setTime(15, 0)->format('Y-m-d\TH:i');
    }

    /**
     * Promised date for a preset/own number of days: `n` working days from today
     * at 15:00 local time, as a timezone free wall clock value.
     */
    public static function plannedAtFromDays(int $days): string
    {
        $timezone = (string) config('app.display_timezone');

        return BusinessDays::add(CarbonImmutable::now($timezone), $days)->setTime(15, 0)->format('Y-m-d\TH:i');
    }

    /** @return array<string, string> */
    public function validatedDetails(): array
    {
        /** @var array<string, string> $data */
        $data = $this->safe()->except(['photos', 'print_confirmation', 'planned_days']);

        return $data;
    }

    /**
     * Raw phone kept for the record, comparable form derived from it (D-20).
     */
    public function normalizedPhone(): ?string
    {
        return Phone::normalize((string) $this->input('phone', ''));
    }
}
