@extends('layouts.plain')

@section('title', 'Brak dostępu')

@section('content')
    <div class="app-card offline-card">
        <div class="app-card-header">
            <span class="eyebrow">Brak dostępu</span>
            <x-icon name="alert" :size="18" />
        </div>
        <div class="app-card-body">
            <h1 class="auth-title">Nie masz dostępu do tej sekcji</h1>
            <p class="service-meta mt-2 mb-3">
                Ta część aplikacji jest zarezerwowana dla administratora serwisu. Jeśli potrzebujesz
                dostępu, poproś go o zmianę roli na swoim koncie.
            </p>
            <a class="btn btn-primary w-100" href="{{ url('/') }}">Przejdź do kolejki</a>
        </div>
    </div>
@endsection
