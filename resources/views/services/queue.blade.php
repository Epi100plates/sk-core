@extends('layouts.app')

@section('title', 'Kolejka')

@section('content')
    @php
        $allStatuses = \App\Domain\Enums\ServiceStatus::activeCases();
    @endphp

    <div class="page-head">
        <div class="page-head__main">
            <span class="eyebrow">Stan warsztatu · {{ now($timezone)->format('d.m.Y') }}</span>
            <h1 class="page-title">Kolejka napraw</h1>
        </div>
    </div>

    {{-- One stage at a time: no choice means the whole working queue. The strip is
         a region, so the server re-renders the counts, the hidden term and the
         reset link together with the list — the filter can never drift away from
         the list it produced. Without JavaScript the radios still submit and the
         link clears the choice. --}}
    <form class="app-card mb-3" method="get" action="{{ route('services.queue') }}"
          data-async-form data-async-on-change>
        <div data-async-region="queue-filter">
            <input type="hidden" name="q" value="{{ $term }}">

            <fieldset class="stat-strip">
                <legend class="visually-hidden">Pokaż zlecenia jednego etapu</legend>
                @foreach ($allStatuses as $status)
                    <label class="stat stat--filter" data-stage="{{ $status->value }}">
                        <input class="stat__input" type="radio" name="status" value="{{ $status->value }}"
                               @checked($activeFilter === $status)>
                        <span class="stat__body">
                            <span class="stat__value">{{ $counts[$status->value] ?? 0 }}</span>
                            <span class="stat__label">{{ $status->label() }}</span>
                        </span>
                    </label>
                @endforeach
            </fieldset>

            @if ($activeFilter instanceof \App\Domain\Enums\ServiceStatus)
                <div class="app-card-footer">
                    <a class="btn btn-sm btn-link" href="{{ route('services.queue', array_filter(['q' => $term])) }}"
                       data-async-link>Pokaż wszystkie</a>
                </div>
            @endif

            <noscript>
                <div class="app-card-footer">
                    <button class="btn btn-sm btn-outline-primary" type="submit">Pokaż tylko wybrany etap</button>
                </div>
            </noscript>
        </div>
    </form>

    <div class="queue-results" data-async-region="queue-results">
        @include('services._queue-results')
    </div>
@endsection
