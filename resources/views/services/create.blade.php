@extends('layouts.app')

@section('title', 'Przyjęcie laptopa')

@section('content')
    <h1 class="h5 mb-3">Przyjęcie laptopa</h1>

    <x-service-form
        :service="null"
        :planned-at-input="$defaultPlannedAt"
        :timezone="$timezone"
        action="{{ route('services.store') }}"
        method="POST"
        submit-label="Przyjmij zlecenie" />
@endsection
