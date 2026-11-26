@extends('layouts.app')

@section('title', 'Kolejka')

@php
    $allStatuses = \App\Domain\Enums\ServiceStatus::activeCases();
@endphp

@section('bar-title')
    <h1>Kolejka napraw</h1>
    <span class="app-bar__date eyebrow">Stan warsztatu · {{ now($timezone)->format('d.m.Y') }}</span>
@endsection

{{-- The stage filter is the second row of the top bar: the counts sit under the
     search, in the same box, instead of in a card of their own. It stays a
     region, so the server re-renders the counts, the hidden term and the reset
     link together with the list — the filter can never drift away from the list
     it produced. The chosen chip is a toggle: pressing it again drops the filter
     (`data-filter-toggle`, see app.js), so the reset link is not the only way
     out. Without JavaScript the radios still submit and the link clears the
     choice. --}}
@section('bar')
    <form class="app-bar__filter" method="get" action="{{ route('services.queue') }}"
          data-async-form data-async-on-change>
        <div data-async-region="queue-filter">
            <input type="hidden" name="q" value="{{ $term }}">

            <fieldset class="stat-strip">
                <legend class="visually-hidden">Pokaż zlecenia jednego etapu</legend>
                <div class="stat-strip__items">
                    @foreach ($allStatuses as $status)
                        <label class="stat stat--filter" data-stage="{{ $status->value }}">
                            <input class="stat__input" type="radio" name="status" value="{{ $status->value }}"
                                   data-filter-toggle @checked($activeFilter === $status)>
                            <span class="stat__body">
                                <span class="stat__value">{{ $counts[$status->value] ?? 0 }}</span>
                                <span class="stat__label"><span class="stat__label--full">{{ $status->label() }}</span><span class="stat__label--short" aria-hidden="true">{{ $status->shortLabel() }}</span></span>
                            </span>
                        </label>
                    @endforeach
                </div>

                @if ($activeFilter instanceof \App\Domain\Enums\ServiceStatus)
                    <a class="app-bar__reset" href="{{ route('services.queue', array_filter(['q' => $term])) }}"
                       data-async-link>Pokaż wszystkie</a>
                @endif
            </fieldset>

            <noscript>
                <button class="btn btn-sm btn-outline-secondary" type="submit">Pokaż tylko wybrany etap</button>
            </noscript>
        </div>
    </form>
@endsection

@section('content')
    <div class="queue-results" data-async-region="queue-results">
        @include('services._queue-results')
    </div>
@endsection
