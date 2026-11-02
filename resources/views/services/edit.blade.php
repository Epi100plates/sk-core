@extends('layouts.app')

@section('title', 'Edycja '.$service->reference)

@section('content')
    <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
        <h1 class="h5 mb-0">Edycja zlecenia {{ $service->reference }}</h1>
        <x-status-badge :status="$service->status" />
    </div>

    <x-service-form
        :service="$service"
        :planned-at-input="$plannedAtInput"
        :timezone="$timezone"
        action="{{ route('services.update', $service) }}"
        method="PUT"
        submit-label="Zapisz zmiany" />
@endsection
