@extends('layouts.app')

@section('title', 'Instalacja')

@section('content')
    <h1 class="h5 mb-3">Instalacja aplikacji</h1>

    <div class="app-card">
        <div class="app-card-body">
            <p>
                Aktywny magazyn danych: <strong>{{ $result->driver }}</strong>
                @if ($result->schemaCreated)
                    <span class="badge text-bg-success">schemat gotowy</span>
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
        <div class="app-card-header border-top-0">
            <a class="btn btn-primary" href="{{ route('login') }}">Przejdź do logowania</a>
        </div>
    </div>
@endsection
