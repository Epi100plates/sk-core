@extends('layouts.app')

@section('title', 'Kolejka')

@section('content')
    <div class="app-card mb-3">
        <div class="app-card-header">
            <h1 class="h5 mb-0">Kolejka napraw</h1>
            <a class="btn btn-sm btn-success" href="{{ route('services.create') }}">+ Przyjmij laptopa</a>
        </div>
        <div class="app-card-body">
            <p class="service-meta mb-2">
                @foreach (\App\Domain\Enums\ServiceStatus::cases() as $status)
                    <span class="me-3">
                        {{ $status->label() }}:
                        <strong>{{ $counts[$status->value] ?? 0 }}</strong>
                    </span>
                @endforeach
            </p>

            <form method="get" action="{{ route('services.queue') }}" class="d-flex flex-wrap gap-2 align-items-end">
                <fieldset class="border-0 p-0 m-0">
                    <legend class="form-label mb-1">Filtruj po statusie</legend>
                    <div class="d-flex flex-wrap gap-3">
                        @foreach (\App\Domain\Enums\ServiceStatus::activeCases() as $status)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="status[]"
                                       id="status-{{ $status->value }}" value="{{ $status->value }}"
                                       @checked(in_array($status->value, $activeFilter, true))>
                                <label class="form-check-label" for="status-{{ $status->value }}">{{ $status->label() }}</label>
                            </div>
                        @endforeach
                    </div>
                </fieldset>
                <button class="btn btn-sm btn-outline-primary" type="submit">Zastosuj filtr</button>
                @if ($activeFilter !== [])
                    <a class="btn btn-sm btn-link" href="{{ route('services.queue') }}">Wyczyść filtr</a>
                @endif
            </form>
        </div>
    </div>

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
        <section class="mt-4" aria-labelledby="recently-released">
            <h2 class="h6" id="recently-released">Ostatnio wydane</h2>
            <p class="service-meta">Zlecenia zakończone — bez akcji, tylko podgląd.</p>
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
            <a class="btn btn-sm btn-link mt-2" href="{{ route('services.released') }}">Całe archiwum wydanych →</a>
        </section>
    @endif
@endsection
