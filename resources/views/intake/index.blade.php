@extends('layouts.app')

@section('title', 'Zgłoszenia')

@section('content')
    <div class="page-head">
        <div class="page-head__main">
            <span class="eyebrow">Formularz zgłoszeń</span>
            <h1 class="page-title">Zgłoszenia z formularza</h1>
            <p class="page-head__meta">
                Zgłoszenia wysłane ze strony publicznej. Oznacz kontakt, odrzuć z powodem
                albo przyjmij laptopa do serwisu — żadne zgłoszenie nie znika.
            </p>
        </div>
    </div>

    <form class="app-card mb-3" method="get" action="{{ route('intake.index') }}"
          data-async-form data-async-on-change>
        <div class="app-card-body" data-async-region="intake-filter">
            <div class="toolbar">
                <div class="field-group field-group--grow">
                    <label class="form-label" for="intake-q">Szukaj</label>
                    <input class="form-control" id="intake-q" name="q" type="search" value="{{ $term }}"
                           placeholder="Numer, telefon, model, treść" autocomplete="off"
                           inputmode="search" enterkeyhint="search" autocapitalize="off" spellcheck="false">
                </div>

                <div class="field-group">
                    <label class="form-label" for="intake-status">Etap</label>
                    <select class="form-select" id="intake-status" name="status">
                        <option value="">Wszystkie ({{ array_sum($counts) }})</option>
                        @foreach (\App\Domain\Enums\IntakeStatus::options() as $value => $label)
                            <option value="{{ $value }}" @selected($activeFilter?->value === $value)>
                                {{ $label }} ({{ $counts[$value] ?? 0 }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <button class="btn btn-primary" type="submit">Pokaż</button>

                @if ($term !== '' || $activeFilter !== null)
                    <a class="btn btn-link" href="{{ route('intake.index') }}" data-async-link>Wyczyść</a>
                @endif
            </div>
        </div>
    </form>

    <div data-async-region="intake-results">
        @if ($page->isEmpty())
            @if ($term !== '')
                <x-empty-state title="Brak wyników"
                               :message="'Żadne zgłoszenie nie zawiera „'.$term.'”.'"
                               action-label="Wyczyść szukanie"
                               :action-url="route('intake.index', array_filter(['status' => $activeFilter?->value]))" />
            @elseif ($activeFilter !== null)
                <x-empty-state title="Ten etap jest pusty"
                               message="Żadne zgłoszenie nie jest teraz na tym etapie."
                               action-label="Pokaż wszystkie"
                               :action-url="route('intake.index')" />
            @else
                <x-empty-state title="Brak zgłoszeń"
                               message="Gdy ktoś wyśle formularz na stronie, zgłoszenie pojawi się tutaj." />
            @endif
        @else
            <p class="queue-summary">
                {{ \App\Domain\FieldLabels::plural($page->total, 'zgłoszenie', 'zgłoszenia', 'zgłoszeń') }}
            </p>

            <div class="list-group mb-2">
                @foreach ($page->items as $ticket)
                    <a class="list-group-item list-group-item-action intake-row"
                       href="{{ route('intake.show', $ticket) }}">
                        <span class="intake-row__main">
                            <span class="intake-row__number">{{ $ticket->number }}</span>
                            @if ($ticket->name !== '')
                                <span class="intake-row__name">{{ $ticket->name }}</span>
                            @endif
                            <span class="service-meta">
                                @if ($ticket->device !== '')
                                    · {{ $ticket->device }}
                                @endif
                                · {{ $ticket->displayPhone() }}
                            </span>
                        </span>
                        <span class="intake-row__side">
                            <span class="badge text-bg-{{ $ticket->status->color() }}">{{ $ticket->status->label() }}</span>
                            <span class="service-meta">{{ \App\Domain\FieldLabels::formatDate($ticket->submittedAt) }}</span>
                        </span>
                    </a>
                @endforeach
            </div>

            <x-pagination :page="$page" />
        @endif
    </div>
@endsection
