@extends('layouts.app')

@section('title', $service->reference)

@section('content')
    @php
        $isFinal = $service->status->isFinal();
        $next = $service->status->nextStatus();
    @endphp

    <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
        <div>
            <h1 class="h5 mb-1">
                {{ $service->deviceModel }}
                @if ($service->isExpress)
                    <span class="badge text-bg-danger">EXPRESS</span>
                @endif
            </h1>
            <p class="service-meta mb-0">
                {{ $service->reference }}
                @if ($service->sealNumber !== null)
                    · plomba {{ $service->sealNumber }}
                @endif
                · przyjęto {{ \App\Domain\FieldLabels::formatDate($service->acceptedAt) }}
            </p>
        </div>
        <x-status-badge :status="$service->status" />
    </div>

    <div class="d-flex flex-wrap gap-2 mb-3">
        @if (! $isFinal && $next instanceof \App\Domain\Enums\ServiceStatus)
            @can('advance', $service)
                <form method="post" action="{{ route('services.advance', $service) }}" data-guard-submit>
                    @csrf
                    <button class="btn btn-primary" type="submit">{{ $service->status->advanceLabel() }} →</button>
                </form>
            @endcan
        @endif

        @can('update', $service)
            <a class="btn btn-outline-primary" href="{{ route('services.edit', $service) }}">Edytuj</a>
        @endcan

        @if ($canRelease)
            @can('release', $service)
                <a class="btn btn-success" href="{{ route('services.release', $service) }}">Wydaj laptopa</a>
            @endcan
        @endif

        @can('print', $service)
            <a class="btn btn-outline-secondary" href="{{ route('print.acceptance', $service) }}" target="_blank" rel="noopener">
                🖨 Potwierdzenie przyjęcia
            </a>
            @if ($service->status === \App\Domain\Enums\ServiceStatus::Released)
                <a class="btn btn-outline-secondary" href="{{ route('print.release', $service) }}" target="_blank" rel="noopener">
                    🖨 Protokół wydania
                </a>
            @endif
        @endcan
    </div>

    <div class="row g-3">
        <div class="col-12 col-lg-7">
            <section class="app-card mb-3" aria-labelledby="service-data">
                <div class="app-card-header">
                    <h2 class="h6 mb-0" id="service-data">Dane zlecenia</h2>
                    @if ($service->hasIssuedConfirmation())
                        <span class="badge text-bg-secondary">🖨 potwierdzenie wydane
                            {{ \App\Domain\FieldLabels::formatDate($service->confirmationIssuedAt) }}</span>
                    @endif
                </div>
                <div class="app-card-body">
                    <dl class="row definition-list mb-0">
                        <dt class="col-5 col-sm-4">Telefon</dt>
                        <dd class="col-7 col-sm-8">{{ $service->displayPhone() }}</dd>

                        <dt class="col-5 col-sm-4">Model</dt>
                        <dd class="col-7 col-sm-8">{{ $service->deviceModel }}</dd>

                        <dt class="col-5 col-sm-4">Numer seryjny</dt>
                        <dd class="col-7 col-sm-8">{{ $service->serialNumber ?? '—' }}</dd>

                        <dt class="col-5 col-sm-4">Usterka</dt>
                        <dd class="col-7 col-sm-8 text-pre-wrap">{{ $service->issue }}</dd>

                        <dt class="col-5 col-sm-4">Hasło do urządzenia</dt>
                        <dd class="col-7 col-sm-8 font-monospace">{{ $service->devicePassword ?? '—' }}</dd>

                        <dt class="col-5 col-sm-4">Termin realizacji</dt>
                        <dd class="col-7 col-sm-8">{{ \App\Domain\FieldLabels::formatDate($service->plannedAt) }}</dd>

                        <dt class="col-5 col-sm-4">Cena / koszt części</dt>
                        <dd class="col-7 col-sm-8">
                            <x-money :value="$service->price" /> / <x-money :value="$service->cost" />
                        </dd>

                        <dt class="col-5 col-sm-4">Uwagi</dt>
                        <dd class="col-7 col-sm-8 text-pre-wrap">{{ $service->notes ?? '—' }}</dd>

                        <dt class="col-5 col-sm-4">Plomba</dt>
                        <dd class="col-7 col-sm-8">{{ $service->sealNumber ?? '—' }}</dd>

                        @if ($service->releasedAt !== null)
                            <dt class="col-5 col-sm-4">Data wydania</dt>
                            <dd class="col-7 col-sm-8">{{ \App\Domain\FieldLabels::formatDate($service->releasedAt) }}</dd>
                        @endif

                        @if ($service->cancelledAt !== null)
                            <dt class="col-5 col-sm-4">Anulowane</dt>
                            <dd class="col-7 col-sm-8">
                                {{ \App\Domain\FieldLabels::formatDate($service->cancelledAt) }} —
                                {{ $service->cancellationReason ?? 'bez powodu' }}
                            </dd>
                        @endif
                    </dl>
                </div>
            </section>

            <section class="app-card mb-3" aria-labelledby="service-photos">
                <div class="app-card-header">
                    <h2 class="h6 mb-0" id="service-photos">Zdjęcia ({{ count($photos) }})</h2>
                </div>
                <div class="app-card-body">
                    <x-photo-gallery :photos="$photos" :service="$service" :editable="! $isFinal" />

                    @can('managePhotos', $service)
                        <form class="mt-3" method="post" action="{{ route('services.photos.store', $service) }}"
                              enctype="multipart/form-data" data-guard-submit data-offline-blocked>
                            @csrf
                            <label class="form-label" for="photos">Dodaj zdjęcia</label>
                            <div class="d-flex flex-wrap gap-2">
                                <input class="form-control" id="photos" name="photos[]" type="file" accept="image/*" multiple required>
                                <button class="btn btn-outline-primary" type="submit">Dodaj</button>
                            </div>
                        </form>
                    @endcan
                </div>
            </section>
        </div>

        <div class="col-12 col-lg-5">
            <section class="app-card mb-3" aria-labelledby="service-history">
                <div class="app-card-header">
                    <h2 class="h6 mb-0" id="service-history">Historia zmian</h2>
                    <span class="service-meta">{{ $events->total }} wpisów</span>
                </div>
                <div class="app-card-body">
                    @if ($events->isEmpty())
                        <p class="service-meta mb-0">Brak wpisów.</p>
                    @else
                        <ol class="history-list">
                            @foreach ($events->items as $event)
                                <li class="history-item">
                                    <p class="mb-1">
                                        <span class="badge text-bg-{{ $event->type->color() }}">{{ $event->type->label() }}</span>
                                        <span class="service-meta">{{ \App\Domain\FieldLabels::formatDate($event->createdAt) }}</span>
                                    </p>

                                    @if ($event->fromStatus !== null || $event->toStatus !== null)
                                        <p class="service-meta mb-1">
                                            {{ \App\Domain\Enums\ServiceStatus::tryFrom((string) $event->fromStatus)?->label() ?? ($event->fromStatus ?? '—') }}
                                            →
                                            {{ \App\Domain\Enums\ServiceStatus::tryFrom((string) $event->toStatus)?->label() ?? ($event->toStatus ?? '—') }}
                                        </p>
                                    @endif

                                    @if ($event->description !== null)
                                        <p class="mb-1">{{ $event->description }}</p>
                                    @endif

                                    @if ($event->hasChanges())
                                        <ul class="history-diff mb-0">
                                            @foreach ($event->changes as $field => $diff)
                                                <li>
                                                    <strong>{{ \App\Domain\FieldLabels::label((string) $field) }}:</strong>
                                                    {{ \App\Domain\FieldLabels::format((string) $field, $diff['from']) }}
                                                    →
                                                    {{ \App\Domain\FieldLabels::format((string) $field, $diff['to']) }}
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </li>
                            @endforeach
                        </ol>

                        @if ($events->total > $events->perPage)
                            <p class="service-meta mt-2 mb-0">
                                Pokazano {{ $events->count() }} z {{ $events->total }} wpisów.
                            </p>
                        @endif
                    @endif
                </div>
            </section>

            @can('cancel', $service)
                <section class="app-card" aria-labelledby="service-cancel">
                    <div class="app-card-header">
                        <h2 class="h6 mb-0" id="service-cancel">Anulowanie</h2>
                    </div>
                    <div class="app-card-body">
                        <form method="post" action="{{ route('services.cancel', $service) }}"
                              data-confirm="Na pewno anulować to zlecenie?" data-guard-submit>
                            @csrf
                            <label class="form-label" for="cancellation_reason">Powód anulowania</label>
                            <input class="form-control mb-2" id="cancellation_reason" name="cancellation_reason"
                                   type="text" maxlength="255" required>
                            <button class="btn btn-outline-danger" type="submit">Anuluj zlecenie</button>
                        </form>
                    </div>
                </section>
            @endcan
        </div>
    </div>
@endsection
