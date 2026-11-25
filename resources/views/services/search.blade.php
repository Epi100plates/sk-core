@extends('layouts.app')

@section('title', 'Szukaj')

@section('content')
    <div class="page-head">
        <div class="page-head__main">
            <span class="eyebrow">Szukaj w zleceniach</span>
            <h1 class="page-title">Wyszukiwanie</h1>
            <p class="page-head__meta">
                Telefon, plomba, model, numer seryjny albo usterka — wystarczą 3 znaki. Szukamy także w archiwum wydanych.
            </p>
        </div>
    </div>

    <form class="app-card mb-3" method="get" action="{{ route('services.search') }}" role="search">
        <div class="app-card-body">
            <label class="form-label" for="q">Szukana fraza</label>
            <div class="toolbar">
                <input class="form-control" id="q" name="q" type="search" value="{{ $term }}"
                       minlength="3" placeholder="np. 505 123 456 albo PLO-001-2024" required>
                <button class="btn btn-primary" type="submit">
                    <x-icon name="search" :size="15" /> Szukaj
                </button>
            </div>
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
        <p class="service-meta">
            Znaleziono {{ $page->total }} zleceń dla „{{ $term }}”.
        </p>

        @foreach ($page->items as $service)
            <x-service-card :service="$service" />
        @endforeach

        <x-pagination :page="$page" />
    @endif
@endsection
