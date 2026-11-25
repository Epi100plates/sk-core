@props([
    'service',
    'nextStatus' => null,
    'compact' => false,
])

@php
    $next = $nextStatus instanceof \App\Domain\Enums\ServiceStatus ? $nextStatus : $service->status->nextStatus();
    $isFinal = $service->status->isFinal();
    $readyForPickup = $service->status === \App\Domain\Enums\ServiceStatus::ReadyForPickup;
@endphp

<article class="app-card service-card service-card--row {{ $service->isExpress ? 'is-express' : '' }} {{ $isFinal ? 'is-final' : '' }}">
    <div class="ticket__body">
        <div class="ticket__main">
            <div class="ticket__head">
                <h2 class="ticket__title">
                    <a href="{{ route('services.show', $service) }}">{{ $service->deviceModel }}</a>
                </h2>
                <span class="ticket__ref">{{ $service->reference }}</span>
            </div>

            <p class="ticket__meta">
                <span>{{ $service->displayPhone() }}</span>
                <span>termin {{ \App\Domain\FieldLabels::formatDate($service->plannedAt) }}</span>
                <span><x-money :value="$service->price" /> / <x-money :value="$service->cost" /></span>
                @if ($service->devicePassword !== null)
                    <x-device-password :password="$service->devicePassword" :field-id="'password-'.$service->id" />
                @endif
            </p>

            @unless ($compact)
                <p class="ticket__issue">{{ \Illuminate\Support\Str::limit($service->issue, 160) }}</p>
            @endunless
        </div>

        <div class="ticket__side">
            <div class="ticket__flags">
                <x-status-badge :status="$service->status" />
                @if ($service->isExpress)
                    <span class="badge badge-express">Express</span>
                @endif
                @if ($service->hasIssuedConfirmation())
                    <span class="badge text-bg-secondary" title="Potwierdzenie przyjęcia zostało wydane">
                        <x-icon name="print" :size="11" /> potwierdzenie wydane
                    </span>
                @endif
            </div>

            <div class="ticket__actions">
                @if (! $isFinal && $next instanceof \App\Domain\Enums\ServiceStatus)
                    @can('advance', $service)
                        <form method="post" action="{{ route('services.advance', $service) }}" data-guard-submit>
                            @csrf
                            <button class="btn btn-sm btn-outline-primary" type="submit">{{ $service->status->advanceLabel() }}</button>
                        </form>
                    @endcan
                @endif

                @if ($readyForPickup)
                    @can('release', $service)
                        <a class="btn btn-sm btn-outline-primary" href="{{ route('services.release', $service) }}">Wydaj</a>
                    @endcan
                @endif
            </div>
        </div>
    </div>
</article>
