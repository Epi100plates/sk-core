@props(['password' => null, 'fieldId' => 'device-password'])

@if ($password === null || $password === '')
    <span class="service-meta">—</span>
@else
    {{-- Visible by default: the workshop needs the password while working, and
         hiding it is one click for the moment a customer stands at the counter. --}}
    <span class="d-inline-flex align-items-center gap-2 flex-wrap">
        <span id="{{ $fieldId }}" class="font-monospace">{{ $password }}</span>
        <button class="btn btn-sm btn-outline-secondary" type="button"
                data-password-toggle="{{ $fieldId }}" aria-pressed="true"
                aria-controls="{{ $fieldId }}" aria-label="Ukryj hasło do urządzenia">Ukryj</button>
    </span>
@endif
