@extends('layouts.app')

@section('title', 'Edycja '.$service->reference)

@section('content')
    <div class="page-head">
        <div class="page-head__main">
            <a class="link-back" href="{{ route('services.show', $service) }}">
                <span class="link-back__icon" aria-hidden="true"><x-icon name="arrow-right" :size="14" /></span>
                Karta zlecenia
            </a>
            <span class="eyebrow">Edycja zlecenia · {{ $service->reference }}</span>
            <h1 class="page-title">{{ $service->deviceModel }}</h1>
        </div>
        <div class="page-head__aside">
            <x-status-badge :status="$service->status" />
        </div>
    </div>

    <x-service-form
        :service="$service"
        :planned-at-input="$plannedAtInput"
        action="{{ route('services.update', $service) }}"
        method="PUT"
        submit-label="Zapisz zmiany" />
@endsection
