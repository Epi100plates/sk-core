@extends('layouts.app')

@section('title', 'Instalacja')

@section('content')
    <div class="page-head">
        <div class="page-head__main">
            <span class="eyebrow">Instalacja</span>
            <h1 class="page-title">Aplikacja zainicjowana</h1>
            <p class="page-head__meta">Jednorazowy ekran instalacji — po ustawieniu konta możesz go wyłączyć.</p>
        </div>
    </div>

    <div class="app-card">
        <div class="app-card-body">
            <p>
                Aktywny magazyn danych: <strong>{{ $result->driver }}</strong>
                @if ($result->schemaCreated)
                    <span class="badge text-bg-info">schemat gotowy</span>
                @endif
            </p>

            @if ($result->adminAlreadyExisted)
                <div class="alert alert-info mb-0">
                    Aplikacja była już zainicjowana — żadne konto nie zostało zmienione.
                </div>
            @elseif ($result->createdAdmin())
                <div class="alert alert-warning">
                    <p class="fw-semibold mb-1">Zapisz te dane teraz — hasło nie będzie pokazane ponownie.</p>
                    <p class="mb-1">Login: <strong class="font-monospace">{{ $result->adminLogin }}</strong></p>
                    <p class="mb-0">Hasło: <strong class="font-monospace">{{ $result->adminPassword }}</strong></p>
                </div>
            @endif

            <p class="service-meta mb-0">
                Po instalacji ustaw <code>INSTALL_TOKEN=</code> (puste) w pliku <code>.env</code>,
                aby ta strona przestała istnieć.
            </p>
        </div>
        <div class="app-card-footer">
            <a class="btn btn-primary" href="{{ route('login') }}">Przejdź do logowania</a>
        </div>
    </div>
@endsection
