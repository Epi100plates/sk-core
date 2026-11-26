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

<form method="post" action="{{ $action }}" enctype="multipart/form-data" id="service-form"
      class="app-form record-form" data-guard-submit>
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div class="page-head">
        <div class="page-head__main">
            <a class="link-back" href="{{ $cancelUrl }}">
                <span class="link-back__icon" aria-hidden="true"><x-icon name="arrow-right" :size="14" /></span>
                {{ $isEdit ? 'Karta zlecenia' : 'Wróć do kolejki' }}
            </a>
            <span class="eyebrow">
                @if ($isEdit)
                    Edycja zlecenia · {{ $service->reference }}
                @else
                    Nowe zlecenie
                @endif
            </span>
            <h1 class="page-title">
                {{ $isEdit ? $service->deviceModel : 'Przyjęcie laptopa' }}
                @if ($isEdit && $service->isExpress)
                    <span class="badge badge-express align-middle ms-1">Express</span>
                @endif
            </h1>
            <p class="page-head__meta">
                @if ($isEdit)
                    {{ $service->reference }} · {{ $service->displayPhone() }}
                @else
                    Telefon, model i usterka są obowiązkowe.
                @endif
            </p>
        </div>
        @if ($isEdit)
            <div class="page-head__aside">
                <x-status-badge :status="$service->status" />
            </div>
        @endif
    </div>

    @if ($isEdit)
        @can('print', $service)
            <a class="record-print" href="{{ route('print.acceptance', $service) }}" target="_blank" rel="noopener">
                <x-icon name="print" :size="14" /> Potwierdzenie przyjęcia
            </a>
        @endcan
    @endif

    {{-- The same action row as the order card: the primary job first, the way out
         beside it, print on the far right. On phones it gives way to the sticky
         bar at the bottom of the form. --}}
    <div class="page-actions record-actions">
        <button class="btn btn-primary" type="submit">{{ $submitLabel }}</button>
        <a class="btn btn-outline-secondary" href="{{ $cancelUrl }}">Anuluj</a>
        <span class="page-actions__grow"></span>
        @if ($isEdit)
            @can('print', $service)
                <a class="btn btn-outline-secondary" href="{{ route('print.acceptance', $service) }}"
                   target="_blank" rel="noopener">
                    <x-icon name="print" :size="15" /> Potwierdzenie przyjęcia
                </a>
            @endcan
        @endif
    </div>

    @if ($errors->any())
        {{-- Server validation speaks once at the top as well as next to each
             field: on a long form the field that failed may be off screen. --}}
        <div class="form-alert record-alert" role="alert" tabindex="-1" aria-labelledby="form-errors-title" data-error-summary>
            <h2 class="form-alert__title" id="form-errors-title">Nie zapisano — popraw pola poniżej</h2>
            <ul class="form-alert__list">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="record-layout">
        <div class="record-main">
            <section class="app-card record-card record-card--rows" aria-labelledby="record-data-title">
                <div class="app-card-header">
                    <h2 class="h6" id="record-data-title">Dane zlecenia</h2>
                    <span class="record-card__note">Pola z <span aria-hidden="true">*</span> są wymagane</span>
                </div>
                <div class="app-card-body">
                    <div class="record-row">
                        <label class="record-row__label" for="phone">
                            Telefon <span class="record-row__req" aria-hidden="true">*</span><span class="visually-hidden">wymagane</span>
                        </label>
                        <div class="record-row__field">
                            <input class="record-input" id="phone" name="phone" type="tel" inputmode="tel"
                                   value="{{ $value('phone') }}" placeholder="505 123 456"
                                   autocomplete="off" required
                                   @error('phone') aria-invalid="true" aria-describedby="phone-error" @enderror>
                            @error('phone')<p class="record-row__error" id="phone-error">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div class="record-row">
                        <label class="record-row__label" for="device_model">
                            Model <span class="record-row__req" aria-hidden="true">*</span><span class="visually-hidden">wymagane</span>
                        </label>
                        <div class="record-row__field">
                            <input class="record-input" id="device_model" name="device_model" type="text" maxlength="100"
                                   value="{{ $value('device_model') }}" placeholder="np. Lenovo IdeaPad 3" required
                                   @error('device_model') aria-invalid="true" aria-describedby="device_model-error" @enderror>
                            @error('device_model')<p class="record-row__error" id="device_model-error">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div class="record-row">
                        <label class="record-row__label" for="serial_number">Numer seryjny</label>
                        <div class="record-row__field">
                            <input class="record-input record-input--mono" id="serial_number" name="serial_number"
                                   type="text" maxlength="100" value="{{ $value('serial_number') }}"
                                   placeholder="S/N z obudowy" autocomplete="off" spellcheck="false"
                                   @error('serial_number') aria-invalid="true" aria-describedby="serial_number-error" @enderror>
                            @error('serial_number')<p class="record-row__error" id="serial_number-error">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div class="record-row">
                        <label class="record-row__label" for="device_password">Hasło</label>
                        <div class="record-row__field">
                            <input class="record-input record-input--mono" id="device_password" name="device_password"
                                   type="text" maxlength="100" value="{{ $value('device_password') }}"
                                   placeholder="hasło lub PIN" autocomplete="off" spellcheck="false"
                                   @error('device_password') aria-invalid="true" aria-describedby="device_password-error" @enderror>
                            @error('device_password')<p class="record-row__error" id="device_password-error">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div class="record-row">
                        <label class="record-row__label" for="issue">
                            Usterka <span class="record-row__req" aria-hidden="true">*</span><span class="visually-hidden">wymagane</span>
                        </label>
                        <div class="record-row__field">
                            <textarea class="record-input record-textarea" id="issue" name="issue" rows="3"
                                      maxlength="5000" required placeholder="Co zgłasza klient — jego słowami."
                                      @error('issue') aria-invalid="true" aria-describedby="issue-error" @enderror>{{ $value('issue') }}</textarea>
                            @error('issue')<p class="record-row__error" id="issue-error">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div class="record-row">
                        <label class="record-row__label" for="notes">Uwagi</label>
                        <div class="record-row__field">
                            <textarea class="record-input record-textarea record-textarea--short" id="notes" name="notes"
                                      rows="2" maxlength="5000" placeholder="Stan obudowy, akcesoria, uzgodnienia."
                                      @error('notes') aria-invalid="true" aria-describedby="notes-error" @enderror>{{ $value('notes') }}</textarea>
                            @error('notes')<p class="record-row__error" id="notes-error">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </div>
            </section>

            <section class="app-card record-card record-card--pad" aria-labelledby="record-photos-title">
                <div class="app-card-header">
                    <h2 class="h6" id="record-photos-title">Zdjęcia</h2>
                    <span class="record-card__note">opcjonalnie</span>
                </div>
                <div class="app-card-body">
                    <label class="record-file-label" for="photos">
                        <span class="record-file-label__title">Dodaj zdjęcia</span>
                        <span class="record-file-label__hint">
                            @if ($isEdit)
                                Nowe pliki dołączą do zdjęć już zapisanych w karcie zlecenia.
                            @else
                                Stan obudowy, plomba, ekran — do 10 plików, maks. 10 MB każde.
                            @endif
                        </span>
                    </label>
                    <input class="record-file @if ($errors->has('photos') || $errors->has('photos.*')) is-invalid @endif"
                           id="photos" name="photos[]" type="file" accept="image/*" multiple
                           @if ($errors->has('photos') || $errors->has('photos.*'))
                               aria-invalid="true" aria-describedby="photos-error"
                           @endif>
                    @if ($errors->has('photos') || $errors->has('photos.*'))
                        <p class="record-row__error" id="photos-error">
                            {{ $errors->first('photos') ?: $errors->first('photos.*') }}
                        </p>
                    @endif
                </div>
            </section>
        </div>

        <aside class="record-side" aria-label="Termin, rozliczenie i opcje">
            <section class="app-card record-card record-card--pad" aria-labelledby="record-deadline-title">
                <div class="app-card-header">
                    <h2 class="h6" id="record-deadline-title">Termin realizacji</h2>
                    <span class="record-card__note">odbiór od 15:00</span>
                </div>
                <div class="app-card-body">
                    <div class="deadline" data-deadline>
                        <div class="record-deadline">
                            <span class="record-deadline__label">Obiecany termin</span>
                            <strong class="record-deadline__value" data-deadline-preview aria-live="polite" aria-atomic="true">{{ $previewText }}</strong>
                        </div>

                        <fieldset class="deadline__modes">
                            <legend class="visually-hidden">Termin realizacji</legend>

                            <div class="deadline__chips deadline__chips--presets">
                                @foreach ($presets as $preset)
                                    <label class="choice choice--preset">
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
                            </div>

                            <div class="deadline__chips deadline__chips--modes">
                                <label class="choice choice--mode">
                                    <input class="choice__input" type="radio" name="planned_mode" value="custom"
                                           data-deadline-mode @checked($mode === 'custom')>
                                    <span class="choice__body">
                                        <span class="choice__label">Liczba dni</span>
                                    </span>
                                </label>

                                <label class="choice choice--mode">
                                    <input class="choice__input" type="radio" name="planned_mode" value="date"
                                           data-deadline-mode @checked($mode === 'date')>
                                    <span class="choice__body">
                                        <span class="choice__label">Inna data</span>
                                    </span>
                                </label>
                            </div>
                        </fieldset>

                        {{-- The server field a preset sends. Always in the DOM with a
                             usable value, so the default preset works without
                             app.js; "inna data" switches it off and sends
                             `planned_at` instead. --}}
                        <input type="hidden" name="planned_days" value="{{ $plannedDays }}" data-deadline-days
                               @disabled($mode === 'date')>

                        <div class="deadline__panel" data-deadline-panel="days" @if ($mode !== 'custom') hidden @endif>
                            <label class="form-label" for="planned_days_input">Podaj liczbę dni</label>
                            <div class="deadline__days">
                                <input class="form-control @error('planned_days') is-invalid @enderror"
                                       id="planned_days_input" type="number" min="1" max="365" step="1"
                                       inputmode="numeric" value="{{ $plannedDays }}" data-deadline-number
                                       @error('planned_days') aria-invalid="true" aria-describedby="planned_days-error" @enderror>
                                <span class="deadline__suffix">dni</span>
                            </div>
                            @error('planned_days')<p class="record-row__error" id="planned_days-error">{{ $message }}</p>@enderror
                        </div>

                        <div class="deadline__panel" data-deadline-panel="date" @if ($mode !== 'date') hidden @endif>
                            <label class="form-label" for="planned_at">Data i godzina</label>
                            <input class="form-control @error('planned_at') is-invalid @enderror" id="planned_at"
                                   name="planned_at" type="datetime-local" step="{{ $plannedStep }}"
                                   value="{{ $plannedValue }}" @disabled($mode !== 'date') @required($mode === 'date')
                                   @error('planned_at') aria-invalid="true" aria-describedby="planned_at-error" @enderror>
                        </div>

                        @error('planned_at')<p class="record-row__error" id="planned_at-error">{{ $message }}</p>@enderror
                        <p class="form-hint">Termin liczony od dziś; godzina odbioru 15:00.</p>
                    </div>
                </div>
            </section>

            <section class="app-card record-card record-card--pad" aria-labelledby="record-money-title">
                <div class="app-card-header">
                    <h2 class="h6" id="record-money-title">Rozliczenie</h2>
                </div>
                <div class="app-card-body">
                    <div class="record-row record-row--tight">
                        <label class="record-row__label" for="price">Cena dla klienta</label>
                        <div class="record-row__field">
                            <div class="money-field">
                                <input class="record-input record-input--money @error('price') is-invalid @enderror"
                                       id="price" name="price" type="text" inputmode="decimal"
                                       value="{{ $value('price', '0') }}"
                                       @error('price') aria-invalid="true" aria-describedby="price-error" @enderror>
                                <span class="money-field__suffix" aria-hidden="true">zł</span>
                            </div>
                            @error('price')<p class="record-row__error" id="price-error">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div class="record-row record-row--tight">
                        <label class="record-row__label record-row__label--quiet" for="cost">Koszt części</label>
                        <div class="record-row__field">
                            <div class="money-field">
                                <input class="record-input record-input--money record-input--quiet @error('cost') is-invalid @enderror"
                                       id="cost" name="cost" type="text" inputmode="decimal"
                                       value="{{ $value('cost', '0') }}"
                                       @error('cost') aria-invalid="true" aria-describedby="cost-error" @enderror>
                                <span class="money-field__suffix" aria-hidden="true">zł</span>
                            </div>
                            @error('cost')<p class="record-row__error" id="cost-error">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div class="record-row record-row--tight">
                        <label class="record-row__label record-row__label--quiet" for="seal_number">Numer plomby</label>
                        <div class="record-row__field">
                            <input class="record-input record-input--mono @error('seal_number') is-invalid @enderror"
                                   id="seal_number" name="seal_number" type="text" maxlength="50"
                                   value="{{ $value('seal_number') }}" placeholder="np. 004821"
                                   autocomplete="off" spellcheck="false"
                                   @error('seal_number') aria-invalid="true" aria-describedby="seal_number-error" @enderror>
                            @error('seal_number')<p class="record-row__error" id="seal_number-error">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </div>
            </section>

            <section class="app-card record-card record-card--pad" aria-labelledby="record-options-title">
                <div class="app-card-header">
                    <h2 class="h6" id="record-options-title">{{ $isEdit ? 'Status i opcje' : 'Opcje' }}</h2>
                </div>
                <div class="app-card-body">
                    @if ($isEdit)
                        <div class="record-field">
                            <label class="form-label" for="status">Etap zlecenia</label>
                            <select class="record-select @error('status') is-invalid @enderror" id="status" name="status"
                                    @error('status') aria-invalid="true" aria-describedby="status-error" @enderror>
                                @foreach (\App\Domain\Enums\ServiceStatus::editableOptions() as $statusValue => $statusLabel)
                                    <option value="{{ $statusValue }}" @selected(old('status', $service->status->value) === $statusValue)>
                                        {{ $statusLabel }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="form-hint">Każda zmiana etapu trafia do historii zmian.</p>
                            @error('status')<p class="record-row__error" id="status-error">{{ $message }}</p>@enderror
                        </div>
                    @endif

                    <div class="record-toggles">
                        <div class="toggle">
                            <input class="form-check-input" type="checkbox" id="is_express" name="is_express" value="1"
                                   @checked(old('is_express', $isEdit ? $service->isExpress : false))>
                            <label class="toggle__label" for="is_express">
                                <span class="toggle__title">Express</span>
                                <span class="toggle__hint">Priorytet — laptop wchodzi przed zwykłą kolejką.</span>
                            </label>
                        </div>

                        @unless ($isEdit)
                            <div class="toggle toggle--quiet">
                                <input class="form-check-input" type="checkbox" id="print_confirmation"
                                       name="print_confirmation" value="1" @checked(old('print_confirmation'))>
                                <label class="toggle__label" for="print_confirmation">
                                    <span class="toggle__title">Potwierdzenie przyjęcia</span>
                                    <span class="toggle__hint">Wydruk dla klienta. Większość klientów go nie chce — zaznacz, gdy poprosi.</span>
                                </label>
                            </div>
                        @endunless
                    </div>
                </div>
            </section>
        </aside>
    </div>

    <div class="form-actions record-foot">
        <a class="btn btn-link form-actions__cancel" href="{{ $cancelUrl }}">Anuluj</a>
        <button class="btn btn-primary form-actions__primary" type="submit">{{ $submitLabel }}</button>
    </div>
</form>
