@extends('layouts.app')

@section('title', 'Kolejka')

@section('content')
    @php
        $activeCount = 0;
        foreach (\App\Domain\Enums\ServiceStatus::activeCases() as $status) {
            $activeCount += $counts[$status->value] ?? 0;
        }
    @endphp

    <div class="page-head">
        <div class="page-head__main">
            <span class="eyebrow">Stan warsztatu · {{ now($timezone)->format('d.m.Y') }}</span>
            <h1 class="page-title">Kolejka napraw</h1>
            <p class="page-head__meta">
                {{ $activeCount }} aktywnych zleceń
                @if ($page->total > 0)
                    · pokazano {{ $page->from() }}–{{ $page->to() }} z {{ $page->total }}
                @endif
            </p>
        </div>
        <div class="page-head__aside">
            <a class="btn btn-primary" href="{{ route('services.create') }}">
                <x-icon name="plus" :size="16" /> Przyjmij laptopa
            </a>
        </div>
    </div>

    <form class="app-card mb-3" method="get" action="{{ route('services.queue') }}">
        <fieldset class="stat-strip">
            <legend class="visually-hidden">Filtruj kolejkę po statusie</legend>
            @foreach (\App\Domain\Enums\ServiceStatus::activeCases() as $status)
                <label class="stat stat--filter">
                    <input class="stat__input" type="checkbox" name="status[]"
                           value="{{ $status->value }}"
                           @checked(in_array($status->value, $activeFilter, true))>
                    <span class="stat__body">
                        <span class="stat__value">{{ $counts[$status->value] ?? 0 }}</span>
                        <span class="stat__label">{{ $status->label() }}</span>
                    </span>
                </label>
            @endforeach
        </fieldset>
        <div class="app-card-footer">
            @if ($activeFilter !== array_map(static fn (\App\Domain\Enums\ServiceStatus $status): string => $status->value, \App\Domain\Enums\ServiceStatus::activeCases()))
                <a class="btn btn-sm btn-link me-auto" href="{{ route('services.queue') }}">Wyczyść filtr</a>
            @else
                <span class="service-meta me-auto">Kliknij status, aby filtrować.</span>
            @endif
            <button class="btn btn-sm btn-outline-primary" type="submit">Zastosuj filtr</button>
        </div>
    </form>

    @if ($page->isEmpty())
        <x-empty-state
            title="Kolejka jest pusta"
            message="Nie ma zleceń spełniających wybrane kryteria. Przyjmij laptopa, aby zacząć." />
    @else
        @foreach ($page->items as $service)
            <x-service-card :service="$service" />
        @endforeach

        <x-pagination :page="$page" />
    @endif

    @if ($released !== [])
        <section aria-labelledby="recently-released">
            <div class="section-head">
                <div>
                    <span class="eyebrow">Archiwum</span>
                    <h2 id="recently-released">Ostatnio wydane</h2>
                </div>
                <a class="btn btn-sm btn-link" href="{{ route('services.released') }}">
                    Całe archiwum <x-icon name="arrow-right" :size="14" />
                </a>
            </div>
            <div class="list-group">
                @foreach ($released as $service)
                    <a class="list-group-item list-group-item-action d-flex flex-wrap justify-content-between gap-2"
                       href="{{ route('services.show', $service) }}">
                        <span>
                            {{ $service->deviceModel }}
                            <span class="service-meta">· {{ $service->reference }} · {{ $service->displayPhone() }}</span>
                        </span>
                        <span class="service-meta">
                            {{ \App\Domain\FieldLabels::formatDate($service->releasedAt) }}
                        </span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif
@endsection
