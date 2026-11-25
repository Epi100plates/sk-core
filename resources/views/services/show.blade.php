@extends('layouts.app')

@section('title', $service->reference)

@section('content')
    @php
        $isFinal = $service->status->isFinal();
        $next = $service->status->nextStatus();
        $readyForPickup = $service->status === \App\Domain\Enums\ServiceStatus::ReadyForPickup;
    @endphp

    <div class="page-head">
        <div class="page-head__main">
            <span class="eyebrow">
                Przyjęto {{ \App\Domain\FieldLabels::formatDate($service->acceptedAt) }}
                @if ($service->sealNumber !== null)
                    · plomba {{ $service->sealNumber }}
                @endif
            </span>
            <h1 class="page-title">
                {{ $service->deviceModel }}
                @if ($service->isExpress)
                    <span class="badge badge-express align-middle ms-1">Express</span>
                @endif
            </h1>
            <p class="page-head__meta">{{ $service->reference }} · {{ $service->displayPhone() }}</p>
        </div>
        <div class="page-head__aside">
            <x-status-badge :status="$service->status" />
        </div>
    </div>

    <div class="page-actions">
        @if (! $isFinal && $next instanceof \App\Domain\Enums\ServiceStatus)
            @can('advance', $service)
                <form method="post" action="{{ route('services.advance', $service) }}" data-guard-submit>
                    @csrf
                    <button class="btn btn-primary" type="submit">{{ $service->status->advanceLabel() }}</button>
                </form>
            @endcan
        @endif

        @if ($canRelease)
            @can('release', $service)
                <a class="btn {{ $readyForPickup ? 'btn-primary' : 'btn-outline-primary' }}"
                   href="{{ route('services.release', $service) }}">Wydaj laptopa</a>
            @endcan
        @endif

        @can('update', $service)
            <a class="btn btn-outline-primary" href="{{ route('services.edit', $service) }}">Edytuj</a>
        @endcan

        <span class="page-actions__grow"></span>

        @can('print', $service)
            <a class="btn btn-outline-secondary" href="{{ route('print.acceptance', $service) }}" target="_blank" rel="noopener">
                <x-icon name="print" :size="15" /> Potwierdzenie przyjęcia
            </a>
            @if ($service->status === \App\Domain\Enums\ServiceStatus::Released)
                <a class="btn btn-outline-secondary" href="{{ route('print.release', $service) }}" target="_blank" rel="noopener">
                    <x-icon name="print" :size="15" /> Protokół wydania
                </a>
            @endif
        @endcan
    </div>

    <div class="row g-3">
        <div class="col-12 col-lg-7">
            <section class="app-card mb-3" aria-labelledby="service-data">
                <div class="app-card-header">
                    <h2 class="h6" id="service-data">Dane zlecenia</h2>
                    @if ($service->hasIssuedConfirmation())
                        <span class="badge text-bg-secondary">
                            <x-icon name="print" :size="11" />
                            Potwierdzenie wydane
                            {{ \App\Domain\FieldLabels::formatDate($service->confirmationIssuedAt) }}
                        </span>
                    @endif
                </div>
                <div class="app-card-body">
                    <dl class="data-rows">
                        <div class="data-row">
                            <dt>Telefon</dt>
                            <dd>{{ $service->displayPhone() }}</dd>
                        </div>
                        <div class="data-row">
                            <dt>Model</dt>
                            <dd>{{ $service->deviceModel }}</dd>
                        </div>
                        @if ($service->serialNumber !== null)
                            <div class="data-row">
                                <dt>Numer seryjny</dt>
                                <dd>{{ $service->serialNumber }}</dd>
                            </div>
                        @endif
                        <div class="data-row">
                            <dt>Usterka</dt>
                            <dd class="text-pre-wrap">{{ $service->issue }}</dd>
                        </div>
                        @if ($service->devicePassword !== null)
                            <div class="data-row">
                                <dt>Hasło do urządzenia</dt>
                                <dd class="font-monospace">
                                    <x-device-password :password="$service->devicePassword" :field-id="'service-password'" />
                                </dd>
                            </div>
                        @endif
                        <div class="data-row">
                            <dt>Termin realizacji</dt>
                            <dd>{{ \App\Domain\FieldLabels::formatDate($service->plannedAt) }}</dd>
                        </div>
                        <div class="data-row">
                            <dt>Cena dla klienta</dt>
                            <dd><x-money :value="$service->price" /></dd>
                        </div>
                        <div class="data-row">
                            <dt>Koszt części</dt>
                            <dd><x-money :value="$service->cost" /></dd>
                        </div>
                        @if ($service->notes !== null)
                            <div class="data-row">
                                <dt>Uwagi</dt>
                                <dd class="text-pre-wrap">{{ $service->notes }}</dd>
                            </div>
                        @endif
                        @if ($service->releasedAt !== null)
                            <div class="data-row">
                                <dt>Data wydania</dt>
                                <dd>{{ \App\Domain\FieldLabels::formatDate($service->releasedAt) }}</dd>
                            </div>
                        @endif
                        @if ($service->cancelledAt !== null)
                            <div class="data-row">
                                <dt>Anulowane</dt>
                                <dd>
                                    {{ \App\Domain\FieldLabels::formatDate($service->cancelledAt) }} —
                                    {{ $service->cancellationReason ?? 'bez powodu' }}
                                </dd>
                            </div>
                        @endif
                    </dl>
                </div>
            </section>

            <section class="app-card mb-3" aria-labelledby="service-photos">
                <div class="app-card-header">
                    <h2 class="h6" id="service-photos">
                        Zdjęcia
                        @if (count($photos) > 0)
                            <span class="service-meta">{{ count($photos) }}</span>
                        @endif
                    </h2>
                </div>
                <div class="app-card-body">
                    @if (count($photos) === 0)
                        <p class="service-meta">Brak zdjęć.</p>
                    @else
                        <x-photo-gallery :photos="$photos" :service="$service" :editable="! $isFinal" />
                    @endif

                    @can('managePhotos', $service)
                        <form class="photo-upload" method="post" action="{{ route('services.photos.store', $service) }}"
                              enctype="multipart/form-data" data-guard-submit data-offline-blocked>
                            @csrf
                            <label class="form-label" for="photos">Dodaj zdjęcia</label>
                            <div class="photo-upload__row">
                                <input class="form-control" id="photos" name="photos[]" type="file" accept="image/*" multiple required>
                                <button class="btn btn-outline-primary" type="submit">Dodaj</button>
                            </div>
                            <p class="service-meta mb-0">Do 10 plików, maks. 10 MB każde.</p>
                        </form>
                    @endcan
                </div>
            </section>
        </div>

        <div class="col-12 col-lg-5">
            <section class="app-card mb-3" aria-labelledby="service-history">
                <div class="app-card-header">
                    <h2 class="h6" id="service-history">Historia zmian</h2>
                    <span class="service-meta">
                        {{ \App\Domain\FieldLabels::plural($events->total, 'wpis', 'wpisy', 'wpisów') }}
                    </span>
                </div>
                <div class="app-card-body">
                    @if ($events->isEmpty())
                        <p class="service-meta mb-0">Brak wpisów.</p>
                    @else
                        <ol class="history-list">
                            @foreach ($events->items as $event)
                                @php
                                    $description = $event->description !== null ? trim($event->description) : null;
                                    // The badge and the status line already say this.
                                    if ($description !== null && str_starts_with($description, 'Status: ')) {
                                        $description = null;
                                    }
                                    if ($description !== null && str_starts_with($description, 'Przyjęto zlecenie ')) {
                                        $description = null;
                                    }

                                    $statusLine = null;
                                    if ($event->fromStatus !== null && $event->toStatus !== null && $event->fromStatus !== $event->toStatus) {
                                        $statusLine = (\App\Domain\Enums\ServiceStatus::tryFrom($event->fromStatus)?->label() ?? $event->fromStatus)
                                            .' → '
                                            .(\App\Domain\Enums\ServiceStatus::tryFrom($event->toStatus)?->label() ?? $event->toStatus);
                                    }

                                    $diffs = [];
                                    foreach ($event->changes as $field => $diff) {
                                        if (\App\Domain\FieldLabels::hiddenInHistory((string) $field)) {
                                            continue;
                                        }

                                        $from = \App\Domain\FieldLabels::format((string) $field, $diff['from']);
                                        $to = \App\Domain\FieldLabels::format((string) $field, $diff['to']);

                                        if ($from === $to) {
                                            continue;
                                        }

                                        $diffs[$field] = [$from, $to];
                                    }
                                @endphp
                                <li class="history-item">
                                    <p class="history-item__head">
                                        <span class="badge text-bg-{{ $event->type->color() }}">{{ $event->type->label() }}</span>
                                        <span class="service-meta">
                                            {{ \App\Domain\FieldLabels::formatDate($event->createdAt) }}
                                            @if ($event->actorId !== null && isset($actors[$event->actorId]))
                                                · {{ $actors[$event->actorId] }}
                                            @endif
                                        </span>
                                    </p>

                                    @if ($statusLine !== null)
                                        <p class="history-item__status">{{ $statusLine }}</p>
                                    @endif

                                    @if ($description !== null)
                                        <p class="history-item__text">{{ $description }}</p>
                                    @endif

                                    @if ($diffs !== [])
                                        <dl class="history-diff">
                                            @foreach ($diffs as $field => [$from, $to])
                                                <div class="history-diff__row">
                                                    <dt>{{ \App\Domain\FieldLabels::label((string) $field) }}</dt>
                                                    <dd>{{ $from }} → {{ $to }}</dd>
                                                </div>
                                            @endforeach
                                        </dl>
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
                <details class="app-card disclosure">
                    <summary class="disclosure__summary">Anuluj zlecenie</summary>
                    <div class="disclosure__body">
                        <form method="post" action="{{ route('services.cancel', $service) }}"
                              data-confirm="Na pewno anulować to zlecenie?" data-guard-submit>
                            @csrf
                            <label class="form-label" for="cancellation_reason">Powód anulowania</label>
                            <input class="form-control mb-2" id="cancellation_reason" name="cancellation_reason"
                                   type="text" maxlength="255" required>
                            <button class="btn btn-outline-danger btn-sm" type="submit">Anuluj zlecenie</button>
                        </form>
                    </div>
                </details>
            @endcan
        </div>
    </div>
@endsection
