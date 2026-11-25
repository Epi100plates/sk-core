@extends('layouts.plain')

@section('title', 'Nie znaleziono strony')

@section('content')
    <div class="app-card offline-card">
        <div class="app-card-header">
            <span class="eyebrow">Nie znaleziono</span>
            <x-icon name="alert" :size="18" />
        </div>
        <div class="app-card-body">
            <h1 class="auth-title">Nie ma takiej strony ani zlecenia</h1>
            <p class="service-meta mt-2 mb-3">
                Adres jest nieaktualny albo zlecenie zostało usunięte. Wróć do kolejki i wyszukaj
                zlecenie po numerze telefonu lub numerze plomby.
            </p>
            <a class="btn btn-primary w-100" href="{{ url('/') }}">Przejdź do kolejki</a>
        </div>
    </div>
@endsection
