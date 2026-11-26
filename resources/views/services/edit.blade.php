@extends('layouts.app')

@section('title', 'Edycja '.$service->reference)
@section('own-errors', '1')

@section('content')
    <x-service-form
        :service="$service"
        :planned-at-input="$plannedAtInput"
        action="{{ route('services.update', $service) }}"
        method="PUT"
        submit-label="Zapisz zmiany" />
@endsection
