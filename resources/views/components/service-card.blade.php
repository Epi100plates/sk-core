@props([
    'service',
    'nextStatus' => null,
    'compact' => false,
])

@php
    $next = $nextStatus instanceof \App\Domain\Enums\ServiceStatus ? $nextStatus : $service->status->nextStatus();
    $isFinal = $service->status->isFinal();
@endphp

<article class="app-card service-card mb-3 {{ $service->isExpress ? 'is-express' : '' }} {{ $isFinal ? 'is-final' : '' }}">
    <div class="app-card-body">
        <div class="d-flex flex-wrap gap-2 align-items-start justify-content-between mb-2">
            <div>
                <h2 class="h6 mb-1">
                    <a class="text-decoration-none" href="{{ route('services.show', $service) }}">{{ $service->deviceModel }}</a>
                    @if ($service->isExpress)
                        <span class="badge text-bg-danger">EXPRESS</span>
                    @endif
                    @if ($service->hasIssuedConfirmation())
                        <span class="badge text-bg-secondary" title="Potwierdzenie przyjęcia zostało wydane">🖨 potwierdzenie wydane</span>
                    @endif
                </h2>
                <p class="service-meta mb-0">
                    {{ $service->reference }}
                    @if ($service->sealNumber !== null)
                        · plomba {{ $service->sealNumber }}
                    @endif
                </p>
            </div>
            <x-status-badge :status="$service->status" />
        </div>

        <dl class="row mb-2 service-meta">
            <dt class="col-5 col-sm-3">Telefon</dt>
            <dd class="col-7 col-sm-3 mb-1">{{ $service->displayPhone() }}</dd>

            <dt class="col-5 col-sm-3">Termin</dt>
            <dd class="col-7 col-sm-3 mb-1">{{ \App\Domain\FieldLabels::formatDate($service->plannedAt) }}</dd>

            <dt class="col-5 col-sm-3">Cena</dt>
            <dd class="col-7 col-sm-3 mb-1"><x-money :value="$service->price" /></dd>

            <dt class="col-5 col-sm-3">Koszt części</dt>
            <dd class="col-7 col-sm-3 mb-1"><x-money :value="$service->cost" /></dd>
        </dl>

        @unless ($compact)
            <p class="service-issue mb-2">{{ \Illuminate\Support\Str::limit($service->issue, 180) }}</p>
        @endunless

        <p class="service-meta mb-2">
            Hasło do urządzenia:
            <x-device-password :password="$service->devicePassword" :field-id="'password-'.$service->id" />
        </p>

        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-sm btn-outline-primary" href="{{ route('services.show', $service) }}">Otwórz</a>

            @if (! $isFinal && $next instanceof \App\Domain\Enums\ServiceStatus)
                @can('advance', $service)
                    <form method="post" action="{{ route('services.advance', $service) }}" data-guard-submit>
                        @csrf
                        <button class="btn btn-sm btn-primary" type="submit">{{ $service->status->advanceLabel() }} →</button>
                    </form>
                @endcan
            @endif

            @if ($service->status === \App\Domain\Enums\ServiceStatus::ReadyForPickup)
                @can('release', $service)
                    <a class="btn btn-sm btn-success" href="{{ route('services.release', $service) }}">Wydaj</a>
                @endcan
            @endif

            @if ($service->status === \App\Domain\Enums\ServiceStatus::Released && $service->releasedAt !== null)
                <span class="service-meta align-self-center">
                    wydano {{ \App\Domain\FieldLabels::formatDate($service->releasedAt) }}
                </span>
            @endif
        </div>
    </div>
</article>
