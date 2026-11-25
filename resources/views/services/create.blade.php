@extends('layouts.app')

@section('title', 'Przyjęcie laptopa')

@section('content')
    <div class="page-head">
        <div class="page-head__main">
            <span class="eyebrow">Nowe zlecenie</span>
            <h1 class="page-title">Przyjęcie laptopa</h1>
            <p class="page-head__meta">Telefon, model i usterka są obowiązkowe.</p>
        </div>
    </div>

    <x-service-form
        :service="null"
        :planned-at-input="$defaultPlannedAt"
        action="{{ route('services.store') }}"
        method="POST"
        submit-label="Przyjmij zlecenie" />
@endsection
