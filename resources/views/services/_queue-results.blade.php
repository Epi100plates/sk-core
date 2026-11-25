@php
    $multipleStages = count($groups) > 1;
    // The stage is stated once: by the group heading, by the active filter chip,
    // or — when neither applies — by the badge on each tile.
    $showBadges = ! $multipleStages && $activeFilter === null;
@endphp

@unless ($page->isEmpty())
    <p class="queue-summary" data-queue-summary>
        @if ($term !== '')
            <span data-queue-summary-text>{{ \App\Domain\FieldLabels::plural($page->total, 'zlecenie', 'zlecenia', 'zleceń') }} dla „{{ $term }}”</span>
            · <a href="{{ route('services.queue', array_filter(['status' => $activeFilter?->value])) }}"
                 data-async-link>Wyczyść szukanie</a>
        @else
            <span data-queue-summary-text>{{ \App\Domain\FieldLabels::plural($page->total, 'zlecenie', 'zlecenia', 'zleceń') }}</span>
        @endif
    </p>
@endunless

@if ($page->isEmpty())
    @if ($term !== '')
        <x-empty-state
            title="Brak wyników"
            :message="'Żadne zlecenie nie zawiera „'.$term.'”.'"
            action-label="Wyczyść szukanie"
            :action-url="route('services.queue', array_filter(['status' => $activeFilter?->value]))" />
    @elseif ($activeFilter instanceof \App\Domain\Enums\ServiceStatus)
        <x-empty-state
            title="Ten etap jest pusty"
            message="Żadne zlecenie nie jest teraz na tym etapie."
            action-label="Pokaż wszystkie"
            :action-url="route('services.queue')" />
    @else
        <x-empty-state
            title="Kolejka jest pusta"
            message="Przyjmij laptopa, aby zacząć." />
    @endif
@else
    @foreach ($groups as $group)
        <section class="queue-group" data-stage="{{ $group['status']->value }}"
                 @if ($multipleStages) aria-labelledby="queue-stage-{{ $group['status']->value }}" @endif>
            @if ($multipleStages)
                <h2 class="queue-group__head" id="queue-stage-{{ $group['status']->value }}">
                    <span>{{ $group['status']->label() }}</span>
                    <span class="queue-group__count">{{ count($group['items']) }}</span>
                    <span class="queue-group__rule" aria-hidden="true"></span>
                </h2>
            @endif

            @foreach ($group['items'] as $service)
                <x-service-card :service="$service" :show-status="$showBadges" />
            @endforeach
        </section>
    @endforeach

    <x-pagination :page="$page" />
@endif

@if ($released !== [])
    <section class="queue-released" aria-labelledby="recently-released">
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
                        <span class="service-meta">· {{ $service->reference }}</span>
                    </span>
                    <span class="service-meta">{{ \App\Domain\FieldLabels::formatDate($service->releasedAt) }}</span>
                </a>
            @endforeach
        </div>
    </section>
@endif
