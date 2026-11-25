@extends('layouts.plain')

@section('title', 'Sesja wygasła')

@section('content')
    <div class="app-card offline-card">
        <div class="app-card-header">
            <span class="eyebrow">Sesja wygasła</span>
            <x-icon name="clock" :size="18" />
        </div>
        <div class="app-card-body">
            <h1 class="auth-title">Zaloguj się ponownie</h1>
            <p class="service-meta mt-2 mb-3">
                Formularz był otwarty zbyt długo, więc aplikacja nie przyjęła zmiany. Zaloguj się
                ponownie — poprzednio wpisane dane nie zostały zapisane.
            </p>
            <a class="btn btn-primary w-100" href="{{ route('login') }}">Przejdź do logowania</a>
        </div>
    </div>
@endsection
