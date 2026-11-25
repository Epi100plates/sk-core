@extends('layouts.plain')

@section('title', 'Błąd aplikacji')

@section('content')
    <div class="app-card offline-card">
        <div class="app-card-header">
            <span class="eyebrow">Błąd aplikacji</span>
            <x-icon name="alert" :size="18" />
        </div>
        <div class="app-card-body">
            <h1 class="auth-title">Coś poszło nie tak</h1>
            <p class="service-meta mt-2 mb-3">
                Operacja nie została wykonana. Odśwież stronę i spróbuj ponownie; jeśli błąd wraca,
                zgłoś go administratorowi serwisu.
            </p>
            <a class="btn btn-primary w-100" href="{{ url('/') }}">Przejdź do kolejki</a>
        </div>
    </div>
@endsection
