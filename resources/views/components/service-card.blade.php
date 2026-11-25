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
    <div class="ticket__bar">
        <span class="ticket__ref">
            {{ $service->reference }}
            @if ($service->sealNumber !== null)
                · plomba {{ $service->sealNumber }}
            @endif
        </span>
        <span class="ticket__flags">
            @if ($service->isExpress)
                <span class="badge badge-express">Express</span>
            @endif
            @if ($service->hasIssuedConfirmation())
                <span class="badge text-bg-secondary" title="Potwierdzenie przyjęcia zostało wydane">
                    <x-icon name="print" :size="11" /> potwierdzenie wydane
                </span>
            @endif
        </span>
    </div>

    <div class="ticket__body">
        <div class="ticket__head">
            <h2 class="ticket__title">
                <a href="{{ route('services.show', $service) }}">{{ $service->deviceModel }}</a>
            </h2>
            <x-status-badge :status="$service->status" />
        </div>

        <dl class="data-rows data-rows--split">
            <div class="data-row">
                <dt>Telefon</dt>
                <dd>{{ $service->displayPhone() }}</dd>
            </div>
            <div class="data-row">
                <dt>Termin</dt>
                <dd>{{ \App\Domain\FieldLabels::formatDate($service->plannedAt) }}</dd>
            </div>
            <div class="data-row">
                <dt>Cena / koszt części</dt>
                <dd><x-money :value="$service->price" /> / <x-money :value="$service->cost" /></dd>
            </div>
            <div class="data-row">
                <dt>Hasło do urządzenia</dt>
                <dd><x-device-password :password="$service->devicePassword" :field-id="'password-'.$service->id" /></dd>
            </div>
        </dl>

        @unless ($compact)
            <p class="ticket__issue">{{ \Illuminate\Support\Str::limit($service->issue, 180) }}</p>
        @endunless

        <div class="ticket__actions">
            <a class="btn btn-sm btn-outline-primary" href="{{ route('services.show', $service) }}">Otwórz</a>

            @if (! $isFinal && $next instanceof \App\Domain\Enums\ServiceStatus)
                @can('advance', $service)
                    <form method="post" action="{{ route('services.advance', $service) }}" data-guard-submit>
                        @csrf
                        <button class="btn btn-sm btn-primary" type="submit">{{ $service->status->advanceLabel() }}</button>
                    </form>
                @endcan
            @endif

            @if ($service->status === \App\Domain\Enums\ServiceStatus::ReadyForPickup)
                @can('release', $service)
                    <a class="btn btn-sm btn-success" href="{{ route('services.release', $service) }}">Wydaj</a>
                @endcan
            @endif

            @if ($service->status === \App\Domain\Enums\ServiceStatus::Released && $service->releasedAt !== null)
                <span class="service-meta ms-auto">
                    wydano {{ \App\Domain\FieldLabels::formatDate($service->releasedAt) }}
                </span>
            @endif
        </div>
    </div>
</article>
