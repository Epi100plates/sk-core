@extends('layouts.app')

@section('title', 'Wydane')

@section('content')
    <h1 class="h5 mb-3">Archiwum wydanych</h1>

    <form class="app-card mb-3" method="get" action="{{ route('services.released') }}">
        <div class="app-card-body">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-md-4">
                    <label class="form-label" for="from">Od</label>
                    <input class="form-control" id="from" name="from" type="date" value="{{ $from }}">
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label" for="to">Do</label>
                    <input class="form-control" id="to" name="to" type="date" value="{{ $to }}">
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label" for="q">Szukaj</label>
                    <input class="form-control" id="q" name="q" type="search" value="{{ $term }}"
                           placeholder="telefon, plomba, model…">
                </div>
                <div class="col-12 d-flex gap-2">
                    <button class="btn btn-primary" type="submit">Filtruj</button>
                    <a class="btn btn-link" href="{{ route('services.released') }}">Wyczyść</a>
                </div>
            </div>
        </div>
    </form>

    @if ($page->isEmpty())
        <x-empty-state title="Brak wydanych zleceń" message="Zmień zakres dat albo wyczyść filtr." />
    @else
        @foreach ($grouped as $day => $services)
            <h2 class="h6 mt-4">
                {{ $day === 'brak-daty' ? 'Bez daty wydania' : \App\Domain\FieldLabels::formatDate($day.' 00:00:00') }}
            </h2>
            <div class="list-group mb-2">
                @foreach ($services as $service)
                    <a class="list-group-item list-group-item-action d-flex flex-wrap justify-content-between gap-2"
                       href="{{ route('services.show', $service) }}">
                        <span>
                            {{ $service->deviceModel }}
                            <span class="service-meta">· {{ $service->reference }} · {{ $service->displayPhone() }}</span>
                        </span>
                        <span class="service-meta">
                            <x-money :value="$service->price" />
                            · {{ \App\Domain\FieldLabels::formatDate($service->releasedAt) }}
                        </span>
                    </a>
                @endforeach
            </div>
        @endforeach

        <x-pagination :page="$page" />
    @endif
@endsection
