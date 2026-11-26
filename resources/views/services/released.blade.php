@extends('layouts.app')

@section('title', 'Wydane')

@section('content')
    <div class="page-head">
        <div class="page-head__main">
            <h1 class="page-title">Wydane zlecenia</h1>
        </div>
    </div>

    {{-- The shared async layer swaps the two regions below in place. The toolbar
         is a region as well: after "Wyczyść zakres" the date fields must come back
         empty from the server instead of keeping a date the list no longer uses.
         The form only submits on `change`, i.e. once the picker has committed, so
         the field is never replaced in the middle of typing. --}}
    <form class="app-card mb-3" method="get" action="{{ route('services.released') }}"
          data-async-form data-async-on-change>
        <div class="app-card-body" data-async-region="released-filter">
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
                    <a class="btn btn-link" href="{{ route('services.released') }}" data-async-link>Wyczyść zakres</a>
                @endif
            </div>
        </div>
    </form>

    <div data-async-region="released-results">
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
    </div>
@endsection
