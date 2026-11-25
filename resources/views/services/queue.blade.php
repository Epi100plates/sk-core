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

    {{-- One stage at a time: no choice means the whole working queue. Without
         JavaScript the radios still submit, and a link clears the choice. --}}
    <form class="app-card mb-3" method="get" action="{{ route('services.queue') }}"
          data-auto-submit data-queue-filter>
        <input type="hidden" name="q" value="{{ $term }}" data-queue-filter-term>

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
                   data-queue-filter-reset data-base-href="{{ route('services.queue') }}">Pokaż wszystkie</a>
            </div>
        @endif

        <noscript>
            <div class="app-card-footer">
                <button class="btn btn-sm btn-outline-primary" type="submit">Pokaż tylko wybrany etap</button>
            </div>
        </noscript>
    </form>

    <p class="visually-hidden" id="queue-search-status" role="status" aria-live="polite"></p>

    <div class="queue-results" data-queue-results>
        @include('services._queue-results')
    </div>
@endsection
