@extends('layouts.app')

@section('title', 'Wydane')

@section('content')
    <div class="page-head">
        <div class="page-head__main">
            <h1 class="page-title">Wydane zlecenia</h1>
            <p class="page-head__meta">
                Zakończone naprawy — podgląd i wydruk protokołu.
            </p>
        </div>
    </div>

    <form class="app-card mb-3" method="get" action="{{ route('services.released') }}" data-auto-submit>
        <div class="app-card-body">
            <div class="toolbar">
                <div class="field-group">
                    <label class="form-label" for="from">Od</label>
                    <input class="form-control" id="from" name="from" type="date" value="{{ $from }}">
                </div>
                <div class="field-group">
                    <label class="form-label" for="to">Do</label>
                    <input class="form-control" id="to" name="to" type="date" value="{{ $to }}">
                </div>
                <button class="btn btn-primary" type="submit">Pokaż</button>
                @if ($from !== '' || $to !== '')
                    <a class="btn btn-link" href="{{ route('services.released') }}">Wyczyść zakres</a>
                @endif
            </div>
        </div>
    </form>

    @if ($page->isEmpty())
        <x-empty-state title="Brak wydanych zleceń" message="Zmień zakres dat albo wyczyść filtr." />
    @else
        @foreach ($grouped as $day => $services)
            <div class="section-head">
                <h2>
                    {{ $day === 'brak-daty' ? 'Bez daty wydania' : \App\Domain\FieldLabels::formatDay($day.' 00:00:00') }}
                </h2>
                <span class="service-meta">
                    {{ \App\Domain\FieldLabels::plural(count($services), 'zlecenie', 'zlecenia', 'zleceń') }}
                </span>
            </div>
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
