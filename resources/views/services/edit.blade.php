@extends('layouts.app')

@section('title', 'Edycja '.$service->reference)

@section('content')
    <div class="page-head">
        <div class="page-head__main">
            <span class="eyebrow">Edycja zlecenia · {{ $service->reference }}</span>
            <h1 class="page-title">{{ $service->deviceModel }}</h1>
        </div>
        <div class="page-head__aside">
            <x-status-badge :status="$service->status" />
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('services.show', $service) }}">Karta zlecenia</a>
        </div>
    </div>

    <x-service-form
        :service="$service"
        :planned-at-input="$plannedAtInput"
        action="{{ route('services.update', $service) }}"
        method="PUT"
        submit-label="Zapisz zmiany" />
@endsection
