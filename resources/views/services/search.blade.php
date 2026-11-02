@extends('layouts.app')

@section('title', 'Szukaj')

@section('content')
    <h1 class="h5 mb-3">Szukaj zlecenia</h1>

    <form class="app-card mb-3" method="get" action="{{ route('services.search') }}">
        <div class="app-card-body">
            <label class="form-label" for="q">Telefon, plomba, model, numer seryjny lub usterka</label>
            <div class="d-flex flex-wrap gap-2">
                <input class="form-control" id="q" name="q" type="search" value="{{ $term }}"
                       minlength="3" placeholder="np. 505 123 456 albo PLO-001-2024" required>
                <button class="btn btn-primary" type="submit">Szukaj</button>
            </div>
            <p class="service-meta mb-0 mt-2">Wystarczą 3 znaki. Szukamy także wśród wydanych zleceń.</p>
        </div>
    </form>

    @if ($page === null)
        <x-empty-state
            title="Wpisz co najmniej 3 znaki"
            message="Szukamy po fragmencie numeru telefonu, plombie, modelu, numerze seryjnym i usterce." />
    @elseif ($page->isEmpty())
        <x-empty-state
            title="Nie znaleziono zleceń"
            message="Sprawdź inny format numeru telefonu (np. +48 505 123 456) albo numer plomby."
            action-label="Przyjmij nowe zlecenie"
            :action-url="route('services.create')" />
    @else
        <p class="service-meta">Znaleziono {{ $page->total }} zleceń dla „{{ $term }}”.</p>

        @foreach ($page->items as $service)
            <x-service-card :service="$service" />
        @endforeach

        <x-pagination :page="$page" />
    @endif
@endsection
