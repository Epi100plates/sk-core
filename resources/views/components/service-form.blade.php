@props(['service' => null, 'plannedAtInput' => '', 'submitLabel' => 'Zapisz', 'action' => '', 'method' => 'POST'])

@php
    $isEdit = $service !== null;

    // A form field name is not always the record property name (device_model vs
    // deviceModel). One map keeps the edit form pre-filled with real values.
    $properties = [
        'phone' => 'phone',
        'device_model' => 'deviceModel',
        'issue' => 'issue',
        'serial_number' => 'serialNumber',
        'device_password' => 'devicePassword',
        'notes' => 'notes',
        'seal_number' => 'sealNumber',
        'price' => 'price',
        'cost' => 'cost',
    ];

    $value = function (string $key, string $default = '') use ($service, $isEdit, $properties): string {
        $current = null;

        if ($isEdit) {
            $property = $properties[$key] ?? $key;
            $current = is_scalar($service->{$property}) ? (string) $service->{$property} : null;
        }

        if (in_array($key, ['price', 'cost'], true) && $current !== null) {
            $current = str_replace('.', ',', $current);
        }

        return (string) old($key, $current ?? $default);
    };

    // A datetime-local field shows seconds only when its step is one second, so
    // the step follows the value: a promised date reads "15:00", while a stored
    // instant with seconds still round trips exactly (defect D-44).
    $plannedValue = (string) old('planned_at', $plannedAtInput);
    $plannedStep = strlen($plannedValue) > 16 ? 1 : 60;

    /* Three ways to promise a deadline. The server knows exactly two fields:
       `planned_days` (1–365 → today + days at 15:00) wins when it arrives, and
       `planned_at` (datetime-local) is the fallback. The radio group below only
       decides which of the two is live: it is called `planned_mode`, the server
       never sees it, and the hidden `planned_days` input is the one that travels.

       An edit starts on "inna data": the stored instant is a fact and must not be
       silently rewritten by the default preset when somebody only changes a phone
       number. */
    // Keys are strings on purpose: PHP turns "1" into an int key, and the preset
    // has to compare equal to the mode the server rendered as checked.
    $presets = [
        ['days' => '1', 'label' => '1 dzień'],
        ['days' => '2', 'label' => '2 dni'],
        ['days' => '3', 'label' => '3 dni'],
        ['days' => '7', 'label' => '7 dni'],
    ];
    $mode = (string) old('planned_mode', $isEdit ? 'date' : '2');

    if (! in_array($mode, ['1', '2', '3', '7', 'custom', 'date'], true)) {
        $mode = $isEdit ? 'date' : '2';
    }

    $plannedDays = (string) old('planned_days', '2');

    if (! ctype_digit($plannedDays) || (int) $plannedDays < 1 || (int) $plannedDays > 365) {
        $plannedDays = '2';
    }

    // The preview is rendered on the server as well, so the promised date reads
    // correctly before app.js runs and for anybody browsing without JavaScript.
    // It follows the same rule as the server: presets and a custom number of days
    // win over the date field.
    $weekdays = ['niedz', 'pon', 'wt', 'śr', 'czw', 'pt', 'sob'];
    $previewText = '—';

    if ($mode === 'date') {
        if ($plannedValue !== '') {
            try {
                $planned = \Carbon\CarbonImmutable::parse($plannedValue, \App\Domain\FieldLabels::displayTimezone());
                $previewText = $weekdays[(int) $planned->dayOfWeek].' '.$planned->format('d.m').', '.$planned->format('H:i');
            } catch (\Throwable) {
                $previewText = '—';
            }
        }
    } else {
        $planned = \Carbon\CarbonImmutable::now(\App\Domain\FieldLabels::displayTimezone())
            ->addDays((int) $plannedDays)
            ->setTime(15, 0);
        $previewText = $weekdays[(int) $planned->dayOfWeek].' '.$planned->format('d.m').', '.$planned->format('H:i');
    }

    $cancelUrl = $isEdit ? route('services.show', $service) : route('services.queue');
@endphp

<form method="post" action="{{ $action }}" enctype="multipart/form-data" data-guard-submit class="app-card app-form">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    @if ($errors->any())
        {{-- Server validation speaks once at the top as well as next to each
             field: on a long form the field that failed may be off screen. --}}
        <div class="form-alert" role="alert" tabindex="-1" aria-labelledby="form-errors-title" data-error-summary>
            <h2 class="form-alert__title" id="form-errors-title">Nie zapisano — popraw pola poniżej</h2>
            <ul class="form-alert__list">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="app-card-body app-form__body">
        <section class="form-section" aria-labelledby="form-section-device">
            <h2 class="form-section__head" id="form-section-device">
                <span class="form-section__title">Klient i sprzęt</span>
                <span class="form-section__rule" aria-hidden="true"></span>
            </h2>

            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <label class="form-label" for="phone">Telefon klienta <span class="text-danger">*</span></label>
                    <input class="form-control @error('phone') is-invalid @enderror" id="phone" name="phone" type="tel"
                           inputmode="tel" value="{{ $value('phone') }}" placeholder="505 123 456"
                           autocomplete="off" required
                           @error('phone') aria-invalid="true" aria-describedby="phone-error" @enderror>
                    @error('phone')<p class="invalid-feedback" id="phone-error">{{ $message }}</p>@enderror
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label" for="device_model">Model urządzenia <span class="text-danger">*</span></label>
                    <input class="form-control @error('device_model') is-invalid @enderror" id="device_model"
                           name="device_model" type="text" maxlength="100" value="{{ $value('device_model') }}" required
                           @error('device_model') aria-invalid="true" aria-describedby="device_model-error" @enderror>
                    @error('device_model')<p class="invalid-feedback" id="device_model-error">{{ $message }}</p>@enderror
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label" for="serial_number">Numer seryjny (S/N)</label>
                    <input class="form-control @error('serial_number') is-invalid @enderror" id="serial_number"
                           name="serial_number" type="text" maxlength="100" value="{{ $value('serial_number') }}"
                           autocomplete="off" spellcheck="false"
                           @error('serial_number') aria-invalid="true" aria-describedby="serial_number-error" @enderror>
                    @error('serial_number')<p class="invalid-feedback" id="serial_number-error">{{ $message }}</p>@enderror
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label" for="device_password">Hasło do urządzenia</label>
                    <input class="form-control @error('device_password') is-invalid @enderror" id="device_password"
                           name="device_password" type="text" maxlength="100" value="{{ $value('device_password') }}"
                           autocomplete="off" spellcheck="false"
                           @error('device_password') aria-invalid="true" aria-describedby="device_password-error" @enderror>
                    @error('device_password')<p class="invalid-feedback" id="device_password-error">{{ $message }}</p>@enderror
                </div>
            </div>
        </section>

        <section class="form-section" aria-labelledby="form-section-issue">
            <h2 class="form-section__head" id="form-section-issue">
                <span class="form-section__title">Usterka i termin</span>
                <span class="form-section__rule" aria-hidden="true"></span>
            </h2>

            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label" for="issue">Usterka <span class="text-danger">*</span></label>
                    <textarea class="form-control @error('issue') is-invalid @enderror" id="issue" name="issue"
                              rows="3" maxlength="5000" required
                              @error('issue') aria-invalid="true" aria-describedby="issue-error" @enderror>{{ $value('issue') }}</textarea>
                    <p class="form-hint mb-0">To, co zgłasza klient, jego słowami — bez diagnozy.</p>
                    @error('issue')<p class="invalid-feedback" id="issue-error">{{ $message }}</p>@enderror
                </div>

                <div class="col-12">
                    <div class="deadline" data-deadline>
                        <fieldset class="deadline__modes">
                            <legend class="form-label deadline__legend">
                                Termin realizacji <span class="text-danger">*</span>
                            </legend>

                            <div class="deadline__chips">
                                @foreach ($presets as $preset)
                                    <label class="choice">
                                        <input class="choice__input" type="radio" name="planned_mode"
                                               value="{{ $preset['days'] }}" data-deadline-mode
                                               data-days="{{ $preset['days'] }}"
                                               @checked($mode === $preset['days'])>
                                        <span class="choice__body">
                                            <span class="choice__value">{{ $preset['days'] }}</span>
                                            <span class="choice__unit">{{ $preset['days'] === '1' ? 'dzień' : 'dni' }}</span>
                                        </span>
                                    </label>
                                @endforeach

                                <label class="choice">
                                    <input class="choice__input" type="radio" name="planned_mode" value="custom"
                                           data-deadline-mode @checked($mode === 'custom')>
                                    <span class="choice__body">
                                        <span class="choice__label">Własna liczba dni</span>
                                    </span>
                                </label>

                                <label class="choice">
                                    <input class="choice__input" type="radio" name="planned_mode" value="date"
                                           data-deadline-mode @checked($mode === 'date')>
                                    <span class="choice__body">
                                        <span class="choice__label">Inna data</span>
                                    </span>
                                </label>
                            </div>
                        </fieldset>

                        <p class="deadline__preview" aria-live="polite">
                            <span class="deadline__preview-label">Termin:</span>
                            <span class="deadline__preview-value" data-deadline-preview>{{ $previewText }}</span>
                        </p>

                        {{-- The server field a preset sends. Always in the DOM with a
                             usable value, so the default preset works without
                             app.js; "inna data" switches it off and sends
                             `planned_at` instead. --}}
                        <input type="hidden" name="planned_days" value="{{ $plannedDays }}" data-deadline-days
                               @disabled($mode === 'date')>

                        <div class="deadline__panel" data-deadline-panel="days" @if ($mode !== 'custom') hidden @endif>
                            <label class="form-label" for="planned_days_input">Liczba dni</label>
                            <div class="deadline__days">
                                <input class="form-control @error('planned_days') is-invalid @enderror"
                                       id="planned_days_input" type="number" min="1" max="365" step="1"
                                       inputmode="numeric" value="{{ $plannedDays }}" data-deadline-number
                                       @error('planned_days') aria-invalid="true" aria-describedby="planned_days-error" @enderror>
                                <span class="deadline__suffix">dni</span>
                            </div>
                            @error('planned_days')<p class="invalid-feedback" id="planned_days-error">{{ $message }}</p>@enderror
                        </div>

                        <div class="deadline__panel" data-deadline-panel="date" @if ($mode !== 'date') hidden @endif>
                            <label class="form-label" for="planned_at">Data i godzina</label>
                            <input class="form-control @error('planned_at') is-invalid @enderror" id="planned_at"
                                   name="planned_at" type="datetime-local" step="{{ $plannedStep }}"
                                   value="{{ $plannedValue }}" @disabled($mode !== 'date') @required($mode === 'date')
                                   @error('planned_at') aria-invalid="true" aria-describedby="planned_at-error" @enderror>
                        </div>

                        @error('planned_at')<p class="invalid-feedback" id="planned_at-error">{{ $message }}</p>@enderror
                        <p class="form-hint mb-0">Termin liczony od dziś, odbiór od 15:00.</p>
                    </div>
                </div>

                <div class="col-12">
                    <div class="toggle">
                        <input class="form-check-input" type="checkbox" id="is_express" name="is_express" value="1"
                               @checked(old('is_express', $isEdit ? $service->isExpress : false))>
                        <label class="toggle__label" for="is_express">
                            <span class="toggle__title">Express</span>
                            <span class="toggle__hint">Priorytet — laptop wchodzi przed zwykłą kolejką.</span>
                        </label>
                    </div>
                </div>
            </div>
        </section>

        @if ($isEdit)
            <section class="form-section" aria-labelledby="form-section-status">
                <h2 class="form-section__head" id="form-section-status">
                    <span class="form-section__title">Status zlecenia</span>
                    <span class="form-section__rule" aria-hidden="true"></span>
                </h2>

                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="status">Etap</label>
                        <select class="form-control form-select @error('status') is-invalid @enderror" id="status"
                                name="status"
                                @error('status') aria-invalid="true" aria-describedby="status-error" @enderror>
                            @foreach (\App\Domain\Enums\ServiceStatus::editableOptions() as $statusValue => $statusLabel)
                                <option value="{{ $statusValue }}" @selected(old('status', $service->status->value) === $statusValue)>
                                    {{ $statusLabel }}
                                </option>
                            @endforeach
                        </select>
                        <p class="form-hint mb-0">Każda zmiana etapu trafia do historii zmian.</p>
                        @error('status')<p class="invalid-feedback" id="status-error">{{ $message }}</p>@enderror
                    </div>
                </div>
            </section>
        @endif

        <section class="form-section" aria-labelledby="form-section-money">
            <h2 class="form-section__head" id="form-section-money">
                <span class="form-section__title">Rozliczenie</span>
                <span class="form-section__rule" aria-hidden="true"></span>
            </h2>

            <div class="row g-3">
                <div class="col-6 col-md-4">
                    <label class="form-label" for="price">Cena dla klienta</label>
                    <div class="money-field">
                        <input class="form-control @error('price') is-invalid @enderror" id="price" name="price"
                               type="text" inputmode="decimal" value="{{ $value('price', '0') }}"
                               @error('price') aria-invalid="true" aria-describedby="price-error" @enderror>
                        <span class="money-field__suffix" aria-hidden="true">zł</span>
                    </div>
                    @error('price')<p class="invalid-feedback" id="price-error">{{ $message }}</p>@enderror
                </div>

                <div class="col-6 col-md-4">
                    <label class="form-label form-label--quiet" for="cost">Koszt części</label>
                    <div class="money-field">
                        <input class="form-control form-control--quiet @error('cost') is-invalid @enderror" id="cost"
                               name="cost" type="text" inputmode="decimal" value="{{ $value('cost', '0') }}"
                               @error('cost') aria-invalid="true" aria-describedby="cost-error" @enderror>
                        <span class="money-field__suffix" aria-hidden="true">zł</span>
                    </div>
                    @error('cost')<p class="invalid-feedback" id="cost-error">{{ $message }}</p>@enderror
                </div>

                <div class="col-12 col-md-4">
                    <label class="form-label form-label--quiet" for="seal_number">Numer plomby</label>
                    <input class="form-control form-control--quiet @error('seal_number') is-invalid @enderror"
                           id="seal_number" name="seal_number" type="text" maxlength="50"
                           value="{{ $value('seal_number') }}" autocomplete="off" spellcheck="false"
                           @error('seal_number') aria-invalid="true" aria-describedby="seal_number-error" @enderror>
                    @error('seal_number')<p class="invalid-feedback" id="seal_number-error">{{ $message }}</p>@enderror
                </div>

                <div class="col-12">
                    <label class="form-label" for="notes">Uwagi</label>
                    <textarea class="form-control @error('notes') is-invalid @enderror" id="notes" name="notes"
                              rows="2" maxlength="5000"
                              @error('notes') aria-invalid="true" aria-describedby="notes-error" @enderror>{{ $value('notes') }}</textarea>
                    @error('notes')<p class="invalid-feedback" id="notes-error">{{ $message }}</p>@enderror
                </div>
            </div>
        </section>

        <section class="form-section" aria-labelledby="form-section-photos">
            <h2 class="form-section__head" id="form-section-photos">
                <span class="form-section__title">Zdjęcia i wydruk</span>
                <span class="form-section__rule" aria-hidden="true"></span>
            </h2>

            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label" for="photos">Zdjęcia</label>
                    <input class="form-control @if ($errors->has('photos') || $errors->has('photos.*')) is-invalid @endif"
                           id="photos" name="photos[]" type="file" accept="image/*" multiple
                           @if ($errors->has('photos') || $errors->has('photos.*'))
                               aria-invalid="true" aria-describedby="photos-error"
                           @endif>
                    <p class="form-hint mb-0">Do 10 plików, maks. 10 MB każde.</p>
                    @if ($errors->has('photos') || $errors->has('photos.*'))
                        <p class="invalid-feedback" id="photos-error">
                            {{ $errors->first('photos') ?: $errors->first('photos.*') }}
                        </p>
                    @endif
                </div>

                @unless ($isEdit)
                    <div class="col-12">
                        <div class="toggle toggle--quiet">
                            <input class="form-check-input" type="checkbox" id="print_confirmation"
                                   name="print_confirmation" value="1" @checked(old('print_confirmation'))>
                            <label class="toggle__label" for="print_confirmation">
                                <span class="toggle__title">Potwierdzenie przyjęcia</span>
                                <span class="toggle__hint">
                                    Wydruk dla klienta. Większość klientów go nie chce — zaznacz, gdy poprosi.
                                </span>
                            </label>
                        </div>
                    </div>
                @endunless
            </div>
        </section>
    </div>

    <div class="form-actions">
        <a class="btn btn-link form-actions__cancel" href="{{ $cancelUrl }}">Anuluj</a>
        <button class="btn btn-primary btn-lg form-actions__primary" type="submit">{{ $submitLabel }}</button>
    </div>
</form>
