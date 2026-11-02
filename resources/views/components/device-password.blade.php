@props(['password' => null, 'fieldId' => 'device-password'])

@if ($password === null || $password === '')
    <span class="service-meta">—</span>
@else
    <span class="d-inline-flex align-items-center gap-2 flex-wrap">
        <span id="{{ $fieldId }}" data-value="{{ $password }}" class="font-monospace">••••••</span>
        <button class="btn btn-sm btn-outline-secondary" type="button"
                data-password-toggle="{{ $fieldId }}" aria-pressed="false"
                aria-controls="{{ $fieldId }}">Pokaż</button>
    </span>
@endif
