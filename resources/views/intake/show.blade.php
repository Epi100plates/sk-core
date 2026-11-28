@extends('layouts.app')

@section('title', 'Zgłoszenie '.$ticket->number)

@section('content')
    <div class="page-head">
        <div class="page-head__main">
            <span class="eyebrow">
                Zgłoszenie z formularza
                @if ($ticket->sourcePage !== null)
                    · {{ $ticket->sourcePage }}
                @endif
            </span>
            <h1 class="page-title">{{ $ticket->number }}</h1>
            <p class="page-head__meta">
                Wysłano {{ \App\Domain\FieldLabels::formatDate($ticket->submittedAt) }}
                @if ($ticket->contactedAt !== null)
                    · kontakt {{ \App\Domain\FieldLabels::formatDate($ticket->contactedAt) }}
                @endif
            </p>
        </div>
        <div class="page-head__aside">
            <span class="badge text-bg-{{ $ticket->status->color() }}">{{ $ticket->status->label() }}</span>
        </div>
    </div>

    @if ($ticket->status === \App\Domain\Enums\IntakeStatus::Rejected && $ticket->rejectionReason !== null)
        <div class="approval-note" role="note">
            <span class="approval-note__label">Powód odrzucenia</span>
            <p class="approval-note__text">{{ $ticket->rejectionReason }}</p>
        </div>
    @endif

    @if ($ticket->status === \App\Domain\Enums\IntakeStatus::Converted)
        <div class="approval-note approval-note--ok" role="note">
            <span class="approval-note__label">Przyjęte do serwisu</span>
            <p class="approval-note__text">
                Zgłoszenie zamieniono na zlecenie.
                @if ($ticket->convertedServiceReference !== null)
                    <a href="{{ route('services.show', $ticket->convertedServiceId) }}">
                        {{ $ticket->convertedServiceReference }}</a>.
                @endif
            </p>
        </div>
    @endif

    <div class="page-actions">
        @if ($ticket->status === \App\Domain\Enums\IntakeStatus::New)
            @can('manage', $ticket)
                <form method="post" action="{{ route('intake.contact', $ticket) }}">
                    @csrf
                    <button class="btn btn-outline-primary" type="submit">Kontakt</button>
                </form>
            @endcan
        @endif

        @if (! $ticket->status->isFinal())
            @can('manage', $ticket)
                <a class="btn btn-primary" href="{{ route('intake.convert', $ticket) }}">Przyjmij do serwisu</a>
            @endcan
        @endif

        <span class="page-actions__grow"></span>

        <a class="btn btn-outline-secondary" href="{{ route('intake.index') }}">Wróć do zgłoszeń</a>
    </div>

    <div class="row g-3">
        <div class="col-12 col-lg-7">
            <section class="app-card mb-3" aria-labelledby="intake-data">
                <div class="app-card-header">
                    <h2 class="h6" id="intake-data">Dane zgłoszenia</h2>
                </div>
                <div class="app-card-body">
                    <dl class="data-rows">
                        <div class="data-row">
                            <dt>Imię</dt>
                            <dd>{{ $ticket->name !== '' ? $ticket->name : '—' }}</dd>
                        </div>
                        <div class="data-row">
                            <dt>Telefon</dt>
                            <dd>{{ $ticket->displayPhone() }}</dd>
                        </div>
                        <div class="data-row">
                            <dt>Model</dt>
                            <dd>{{ $ticket->device !== '' ? $ticket->device : '—' }}</dd>
                        </div>
                        <div class="data-row">
                            <dt>Problem</dt>
                            <dd class="text-pre-wrap">{{ $ticket->problem }}</dd>
                        </div>
                        <div class="data-row">
                            <dt>Strona</dt>
                            <dd>{{ $ticket->sourcePage ?? '—' }}</dd>
                        </div>
                        <div class="data-row">
                            <dt>Wysłano</dt>
                            <dd>{{ \App\Domain\FieldLabels::formatDate($ticket->submittedAt) }}</dd>
                        </div>
                        @if ($ticket->convertedServiceReference !== null)
                            <div class="data-row">
                                <dt>Zlecenie</dt>
                                <dd>{{ $ticket->convertedServiceReference }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>
            </section>
        </div>

        <div class="col-12 col-lg-5">
            @if (! $ticket->status->isFinal())
                @can('manage', $ticket)
                    <details class="app-card disclosure">
                        <summary class="disclosure__summary">Odrzuć zgłoszenie</summary>
                        <div class="disclosure__body">
                            <form method="post" action="{{ route('intake.reject', $ticket) }}"
                                  data-confirm="Na pewno odrzucić zgłoszenie?" data-guard-submit>
                                @csrf
                                <label class="form-label" for="rejection_reason">Powód odrzucenia</label>
                                <input class="form-control mb-2" id="rejection_reason" name="rejection_reason"
                                       type="text" maxlength="255" required>
                                @error('rejection_reason')<p class="invalid-feedback d-block">{{ $message }}</p>@enderror
                                <button class="btn btn-outline-danger btn-sm" type="submit">Odrzuć</button>
                            </form>
                        </div>
                    </details>
                @endcan
            @endif

            <p class="service-meta mt-3 mb-0">
                Zgłoszenie zostaje w bazie po oznaczeniu kontaktu, odrzuceniu i przyjęciu —
                historia nie znika.
            </p>
        </div>
    </div>
@endsection
