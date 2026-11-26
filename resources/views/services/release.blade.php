@extends('layouts.app')

@section('title', 'Wydanie '.$service->reference)

@section('content')
    <div class="page-head">
        <div class="page-head__main">
            <a class="link-back" href="{{ route('services.show', $service) }}">
                <span class="link-back__icon" aria-hidden="true"><x-icon name="arrow-right" :size="14" /></span>
                Karta zlecenia
            </a>
            <span class="eyebrow">Wydanie zlecenia</span>
            <h1 class="page-title">Protokół wydania</h1>
            <p class="page-head__meta">Sprawdź dane, potwierdź cenę końcową i wydaj sprzęt.</p>
        </div>
        <div class="page-head__aside">
            <x-status-badge :status="$service->status" />
        </div>
    </div>

    {{-- Two columns from 992px: the job summary stays beside the form instead of
         pushing it below the fold, which is what made releasing on a 1366×768
         laptop a scroll hunt. On a phone the summary is a compact band on top. --}}
    <div class="release-layout">
        <aside class="release-aside" aria-label="Podsumowanie zlecenia">
            <div class="docket docket--compact">
                <p class="docket__label">
                    <span>Zlecenie serwisowe</span>
                </p>
                <p class="docket__reference">{{ $service->reference }}</p>
                <dl class="docket__rows">
                    <div class="docket__row">
                        <dt>Model</dt>
                        <dd>{{ $service->deviceModel }}</dd>
                    </div>
                    <div class="docket__row">
                        <dt>Telefon</dt>
                        <dd>{{ $service->displayPhone() }}</dd>
                    </div>
                    <div class="docket__row">
                        <dt>Przyjęto</dt>
                        <dd>{{ \App\Domain\FieldLabels::formatDate($service->acceptedAt) }}</dd>
                    </div>
                    <div class="docket__row docket__row--stack">
                        <dt>Usterka</dt>
                        <dd>{{ $service->issue }}</dd>
                    </div>
                </dl>
            </div>

            @if (count($photos) > 0)
                <section class="app-card release-aside__card" aria-labelledby="release-photos">
                    <div class="app-card-header">
                        <h2 class="h6" id="release-photos">Zdjęcia</h2>
                        <span class="service-meta">{{ count($photos) }}</span>
                    </div>
                    <div class="app-card-body">
                        <x-photo-gallery :photos="$photos" :service="$service" group="release-photos" />
                    </div>
                </section>
            @endif
        </aside>

        <form method="post" action="{{ route('services.release.store', $service) }}" class="app-card app-form app-form--split"
              data-guard-submit data-offline-blocked>
            @csrf

            @if ($errors->any())
                <div class="form-alert" role="alert" tabindex="-1" aria-labelledby="release-errors-title" data-error-summary>
                    <h2 class="form-alert__title" id="release-errors-title">Nie wydano — popraw pola poniżej</h2>
                    <ul class="form-alert__list">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="app-card-body app-form__body">
                <section class="form-section" aria-labelledby="release-section-when">
                    <h2 class="form-section__head" id="release-section-when">
                        <span class="form-section__title">Wydanie</span>
                        <span class="form-section__rule" aria-hidden="true"></span>
                    </h2>

                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label" for="released_at">Data wydania <span class="text-danger">*</span></label>
                            <input class="form-control @error('released_at') is-invalid @enderror" id="released_at"
                                   name="released_at" type="datetime-local"
                                   step="{{ strlen((string) old('released_at', $defaultReleasedAt)) > 16 ? 1 : 60 }}"
                                   value="{{ old('released_at', $defaultReleasedAt) }}" required
                                   @error('released_at') aria-invalid="true" aria-describedby="released_at-error" @enderror>
                            @error('released_at')<p class="invalid-feedback" id="released_at-error">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </section>

                <section class="form-section" aria-labelledby="release-section-money">
                    <h2 class="form-section__head" id="release-section-money">
                        <span class="form-section__title">Rozliczenie</span>
                        <span class="form-section__rule" aria-hidden="true"></span>
                    </h2>

                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label" for="price">Cena <span class="text-danger">*</span></label>
                            <div class="money-field">
                                <input class="form-control @error('price') is-invalid @enderror" id="price" name="price"
                                       type="text" inputmode="decimal"
                                       value="{{ old('price', str_replace('.', ',', $service->price)) }}" required
                                       @error('price') aria-invalid="true" aria-describedby="price-error" @enderror>
                                <span class="money-field__suffix" aria-hidden="true">zł</span>
                            </div>
                            @error('price')<p class="invalid-feedback" id="price-error">{{ $message }}</p>@enderror
                        </div>

                        <div class="col-6">
                            <label class="form-label form-label--quiet" for="cost">Koszt części <span class="text-danger">*</span></label>
                            <div class="money-field">
                                <input class="form-control form-control--quiet @error('cost') is-invalid @enderror" id="cost"
                                       name="cost" type="text" inputmode="decimal"
                                       value="{{ old('cost', str_replace('.', ',', $service->cost)) }}" required
                                       @error('cost') aria-invalid="true" aria-describedby="cost-error" @enderror>
                                <span class="money-field__suffix" aria-hidden="true">zł</span>
                            </div>
                            @error('cost')<p class="invalid-feedback" id="cost-error">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </section>

                <section class="form-section" aria-labelledby="release-section-device">
                    <h2 class="form-section__head" id="release-section-device">
                        <span class="form-section__title">Identyfikacja sprzętu</span>
                        <span class="form-section__rule" aria-hidden="true"></span>
                    </h2>

                    <div class="row g-3">
                        <div class="col-12 col-md-4">
                            <label class="form-label form-label--quiet" for="serial_number">Numer seryjny</label>
                            <input class="form-control form-control--quiet @error('serial_number') is-invalid @enderror"
                                   id="serial_number" name="serial_number" type="text" maxlength="100"
                                   value="{{ old('serial_number', $service->serialNumber) }}"
                                   autocomplete="off" spellcheck="false"
                                   @error('serial_number') aria-invalid="true" aria-describedby="serial_number-error" @enderror>
                            @error('serial_number')<p class="invalid-feedback" id="serial_number-error">{{ $message }}</p>@enderror
                        </div>

                        <div class="col-12 col-md-4">
                            <label class="form-label form-label--quiet" for="seal_number">Numer plomby</label>
                            <input class="form-control form-control--quiet @error('seal_number') is-invalid @enderror"
                                   id="seal_number" name="seal_number" type="text" maxlength="50"
                                   value="{{ old('seal_number', $service->sealNumber) }}"
                                   autocomplete="off" spellcheck="false"
                                   @error('seal_number') aria-invalid="true" aria-describedby="seal_number-error" @enderror>
                            @error('seal_number')<p class="invalid-feedback" id="seal_number-error">{{ $message }}</p>@enderror
                        </div>

                        <div class="col-12 col-md-4">
                            <label class="form-label form-label--quiet" for="device_password">Hasło do urządzenia</label>
                            <input class="form-control form-control--quiet @error('device_password') is-invalid @enderror"
                                   id="device_password" name="device_password" type="text" maxlength="100"
                                   value="{{ old('device_password', $service->devicePassword) }}"
                                   autocomplete="off" spellcheck="false"
                                   @error('device_password') aria-invalid="true" aria-describedby="device_password-error" @enderror>
                            @error('device_password')<p class="invalid-feedback" id="device_password-error">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </section>

                <section class="form-section" aria-labelledby="release-section-notes">
                    <h2 class="form-section__head" id="release-section-notes">
                        <span class="form-section__title">Uwagi i wydruk</span>
                        <span class="form-section__rule" aria-hidden="true"></span>
                    </h2>

                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label" for="notes">Uwagi</label>
                            <textarea class="form-control @error('notes') is-invalid @enderror" id="notes" name="notes"
                                      rows="2" maxlength="5000"
                                      @error('notes') aria-invalid="true" aria-describedby="notes-error" @enderror>{{ old('notes', $service->notes) }}</textarea>
                            @error('notes')<p class="invalid-feedback" id="notes-error">{{ $message }}</p>@enderror
                        </div>

                        <div class="col-12">
                            <div class="toggle toggle--quiet">
                                <input class="form-check-input" type="checkbox" id="print_protocol"
                                       name="print_protocol" value="1" @checked(old('print_protocol'))>
                                <label class="toggle__label" for="print_protocol">
                                    <span class="toggle__title">Wydrukuj protokół wydania</span>
                                    <span class="toggle__hint">Drukuje się od razu po potwierdzeniu wydania.</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <div class="form-actions">
                <a class="btn btn-link form-actions__cancel" href="{{ route('services.show', $service) }}">Anuluj</a>
                <button class="btn btn-primary btn-lg form-actions__primary" type="submit">Potwierdź wydanie</button>
            </div>
        </form>
    </div>
@endsection
