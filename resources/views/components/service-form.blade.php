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
@endphp

<form method="post" action="{{ $action }}" enctype="multipart/form-data" data-guard-submit class="app-card">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div class="app-card-body">
        <div class="row g-3">
            <div class="col-12 col-md-6">
                <label class="form-label" for="phone">Telefon klienta <span class="text-danger">*</span></label>
                <input class="form-control @error('phone') is-invalid @enderror" id="phone" name="phone" type="tel"
                       inputmode="tel" value="{{ $value('phone') }}" placeholder="505 123 456" required>
                @error('phone')<p class="invalid-feedback">{{ $message }}</p>@enderror
            </div>

            <div class="col-12 col-md-6">
                <label class="form-label" for="device_model">Model urządzenia <span class="text-danger">*</span></label>
                <input class="form-control @error('device_model') is-invalid @enderror" id="device_model"
                       name="device_model" type="text" maxlength="100" value="{{ $value('device_model') }}" required>
                @error('device_model')<p class="invalid-feedback">{{ $message }}</p>@enderror
            </div>

            <div class="col-12">
                <label class="form-label" for="issue">Usterka <span class="text-danger">*</span></label>
                <textarea class="form-control @error('issue') is-invalid @enderror" id="issue" name="issue"
                          rows="3" maxlength="5000" required>{{ $value('issue') }}</textarea>
                @error('issue')<p class="invalid-feedback">{{ $message }}</p>@enderror
            </div>

            <div class="col-12 col-md-6">
                <label class="form-label" for="serial_number">Numer seryjny (S/N)</label>
                <input class="form-control" id="serial_number" name="serial_number" type="text" maxlength="100"
                       value="{{ $value('serial_number') }}">
            </div>

            <div class="col-12 col-md-6">
                <label class="form-label" for="seal_number">Numer plomby</label>
                <input class="form-control @error('seal_number') is-invalid @enderror" id="seal_number"
                       name="seal_number" type="text" maxlength="50" value="{{ $value('seal_number') }}">
                @error('seal_number')<p class="invalid-feedback">{{ $message }}</p>@enderror
            </div>

            <div class="col-12 col-md-6">
                <label class="form-label" for="device_password">Hasło do urządzenia</label>
                <input class="form-control" id="device_password" name="device_password" type="text" maxlength="100"
                       value="{{ $value('device_password') }}" autocomplete="off">
                <p class="service-meta mb-0">Zapisywane szyfrowane, widoczne dla pracowników.</p>
            </div>

            <div class="col-12 col-md-6">
                <label class="form-label" for="planned_at">Termin realizacji <span class="text-danger">*</span></label>
                <input class="form-control @error('planned_at') is-invalid @enderror" id="planned_at" name="planned_at"
                       type="datetime-local" step="1" value="{{ old('planned_at', $plannedAtInput) }}" required>
                @error('planned_at')<p class="invalid-feedback">{{ $message }}</p>@enderror
                <p class="service-meta mb-0">Czas lokalny ({{ $timezone ?? \App\Domain\FieldLabels::displayTimezone() }}).</p>
            </div>

            <div class="col-6 col-md-3">
                <label class="form-label" for="price">Cena dla klienta</label>
                <input class="form-control @error('price') is-invalid @enderror" id="price" name="price" type="text"
                       inputmode="decimal" value="{{ $value('price', '0') }}">
                @error('price')<p class="invalid-feedback">{{ $message }}</p>@enderror
            </div>

            <div class="col-6 col-md-3">
                <label class="form-label" for="cost">Koszt części</label>
                <input class="form-control @error('cost') is-invalid @enderror" id="cost" name="cost" type="text"
                       inputmode="decimal" value="{{ $value('cost', '0') }}">
                @error('cost')<p class="invalid-feedback">{{ $message }}</p>@enderror
            </div>

            <div class="col-12 col-md-6">
                <label class="form-label" for="notes">Uwagi wewnętrzne</label>
                <textarea class="form-control" id="notes" name="notes" rows="2" maxlength="5000">{{ $value('notes') }}</textarea>
            </div>

            <div class="col-12">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="is_express" name="is_express" value="1"
                           @checked(old('is_express', $isEdit ? $service->isExpress : false))>
                    <label class="form-check-label" for="is_express">Express — priorytet w kolejce</label>
                </div>
            </div>

            @if ($isEdit)
                <div class="col-12 col-md-6">
                    <label class="form-label" for="status">Status</label>
                    <select class="form-control" id="status" name="status">
                        @foreach (\App\Domain\Enums\ServiceStatus::editableOptions() as $statusValue => $statusLabel)
                            <option value="{{ $statusValue }}" @selected(old('status', $service->status->value) === $statusValue)>
                                {{ $statusLabel }}
                            </option>
                        @endforeach
                    </select>
                    <p class="service-meta mb-0">Wydanie i anulowanie mają osobne akcje.</p>
                </div>
            @endif

            <div class="col-12">
                <label class="form-label" for="photos">Zdjęcia (do 10 plików, max 10 MB każde)</label>
                <input class="form-control" id="photos" name="photos[]" type="file" accept="image/*" multiple>
            </div>

            @unless ($isEdit)
                <div class="col-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="print_confirmation"
                               name="print_confirmation" value="1" @checked(old('print_confirmation'))>
                        <label class="form-check-label" for="print_confirmation">Wydrukuj potwierdzenie przyjęcia</label>
                    </div>
                </div>
            @endunless
        </div>
    </div>

    <div class="app-card-header border-top-0">
        <a class="btn btn-link" href="{{ $isEdit ? route('services.show', $service) : route('services.queue') }}">Anuluj</a>
        <button class="btn btn-primary" type="submit">{{ $submitLabel }}</button>
    </div>
</form>
