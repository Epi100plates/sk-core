@extends('layouts.app')

@section('title', 'Przyjęcie laptopa')
@section('own-errors', '1')

@section('content')
    <x-service-form
        :service="null"
        :planned-at-input="$defaultPlannedAt"
        action="{{ route('services.store') }}"
        method="POST"
        submit-label="Przyjmij zlecenie" />
@endsection
