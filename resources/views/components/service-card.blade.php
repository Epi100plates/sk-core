@props([
    'service',
    'nextStatus' => null,
    'compact' => false,
    'showStatus' => true,
])

@php
    $next = $nextStatus instanceof \App\Domain\Enums\ServiceStatus ? $nextStatus : $service->status->nextStatus();
    $isFinal = $service->status->isFinal();
    $readyForPickup = $service->status === \App\Domain\Enums\ServiceStatus::ReadyForPickup;
    // The whole tile opens the edit form, where every field of this laptop can be
    // changed; a handed-over order has no edit form, so it opens its card.
    $target = \Illuminate\Support\Facades\Gate::allows('update', $service)
        ? route('services.edit', $service)
        : route('services.show', $service);
@endphp

<article class="app-card service-card service-card--row {{ $isFinal ? 'is-final' : '' }}"
         data-stage="{{ $service->status->value }}"
         data-card-href="{{ $target }}">
    <div class="ticket__body">
        <div class="ticket__main">
            <div class="ticket__head">
                {{-- Level 2 in both cases: under a stage heading and on its own,
                     so the outline never skips a level. --}}
                <h2 class="ticket__title">
                    <a href="{{ $target }}">{{ $service->deviceModel }}</a>
                </h2>
                <span class="ticket__ref">{{ $service->reference }}</span>
            </div>

            {{-- One labelled fact per cell, label above value: the shop reads the
                 deadline and the money without decoding a run-on line, and every
                 value keeps the same left edge down the list. --}}
            <dl class="ticket__facts">
                <div class="ticket__fact">
                    <dt>Telefon</dt>
                    <dd>{{ $service->displayPhone() }}</dd>
                </div>
                <div class="ticket__fact">
                    <dt>Termin</dt>
                    <dd>{{ \App\Domain\FieldLabels::formatDate($service->plannedAt) }}</dd>
                </div>
                <div class="ticket__fact">
                    <dt>Cena</dt>
                    <dd><x-money :value="$service->price" /></dd>
                </div>
                <div class="ticket__fact">
                    <dt>Koszt</dt>
                    <dd><x-money :value="$service->cost" /></dd>
                </div>
                @if ($service->devicePassword !== null)
                    {{-- Full width: the value carries its own "Ukryj" button and
                         would be squeezed in a quarter of the row. --}}
                    <div class="ticket__fact ticket__fact--secret">
                        <dt>Hasło</dt>
                        <dd><x-device-password :password="$service->devicePassword" :field-id="'password-'.$service->id" /></dd>
                    </div>
                @endif
            </dl>

            @unless ($compact)
                {{-- Prose, not another field: a hairline and a larger size keep it
                     from reading as yet another grey metadata line. --}}
                <p class="ticket__issue">{{ \Illuminate\Support\Str::limit($service->issue, 160) }}</p>
            @endunless
        </div>

        <div class="ticket__side">
            <div class="ticket__flags">
                @if ($showStatus)
                    <x-status-badge :status="$service->status" />
                @endif
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
                        {{-- Advanced in place: the server re-renders the stages, so
                             the tile moves to its new section without a reload. --}}
                        <form method="post" action="{{ route('services.advance', $service) }}" data-async-form>
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

                <a class="ticket__more" href="{{ route('services.show', $service) }}">
                    Szczegóły <x-icon name="arrow-right" :size="13" />
                </a>
            </div>
        </div>
    </div>
</article>
