@extends('layouts.app')

@section('title', 'Wydanie '.$service->reference)

@section('content')
    <h1 class="h5 mb-3">Wydanie zlecenia {{ $service->reference }}</h1>

    <div class="row g-3">
        <div class="col-12 col-lg-7">
            <form method="post" action="{{ route('services.release.store', $service) }}" class="app-card"
                  data-guard-submit data-offline-blocked>
                @csrf

                <div class="app-card-header">
                    <h2 class="h6 mb-0">Protokół wydania</h2>
                    <x-status-badge :status="$service->status" />
                </div>

                <div class="app-card-body">
                    <dl class="row definition-list">
                        <dt class="col-5 col-sm-4">Model</dt>
                        <dd class="col-7 col-sm-8">{{ $service->deviceModel }}</dd>

                        <dt class="col-5 col-sm-4">Telefon</dt>
                        <dd class="col-7 col-sm-8">{{ $service->displayPhone() }}</dd>

                        <dt class="col-5 col-sm-4">Usterka</dt>
                        <dd class="col-7 col-sm-8 text-pre-wrap">{{ $service->issue }}</dd>
                    </dl>

                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="released_at">Data wydania <span class="text-danger">*</span></label>
                            <input class="form-control @error('released_at') is-invalid @enderror" id="released_at"
                                   name="released_at" type="datetime-local" step="1"
                                   value="{{ old('released_at', $defaultReleasedAt) }}" required>
                            @error('released_at')<p class="invalid-feedback">{{ $message }}</p>@enderror
                            <p class="service-meta mb-0">Czas lokalny ({{ $timezone }}).</p>
                        </div>

                        <div class="col-6 col-md-3">
                            <label class="form-label" for="price">Cena <span class="text-danger">*</span></label>
                            <input class="form-control @error('price') is-invalid @enderror" id="price" name="price"
                                   type="text" inputmode="decimal" value="{{ old('price', str_replace('.', ',', $service->price)) }}" required>
                            @error('price')<p class="invalid-feedback">{{ $message }}</p>@enderror
                        </div>

                        <div class="col-6 col-md-3">
                            <label class="form-label" for="cost">Koszt części <span class="text-danger">*</span></label>
                            <input class="form-control @error('cost') is-invalid @enderror" id="cost" name="cost"
                                   type="text" inputmode="decimal" value="{{ old('cost', str_replace('.', ',', $service->cost)) }}" required>
                            @error('cost')<p class="invalid-feedback">{{ $message }}</p>@enderror
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label" for="serial_number">Numer seryjny</label>
                            <input class="form-control" id="serial_number" name="serial_number" type="text"
                                   maxlength="100" value="{{ old('serial_number', $service->serialNumber) }}">
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label" for="seal_number">Numer plomby</label>
                            <input class="form-control" id="seal_number" name="seal_number" type="text"
                                   maxlength="50" value="{{ old('seal_number', $service->sealNumber) }}">
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label" for="device_password">Hasło do urządzenia</label>
                            <input class="form-control" id="device_password" name="device_password" type="text"
                                   maxlength="100" value="{{ old('device_password', $service->devicePassword) }}">
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label" for="notes">Uwagi</label>
                            <textarea class="form-control" id="notes" name="notes" rows="2"
                                      maxlength="5000">{{ old('notes', $service->notes) }}</textarea>
                        </div>

                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="print_protocol"
                                       name="print_protocol" value="1" @checked(old('print_protocol'))>
                                <label class="form-check-label" for="print_protocol">Wydrukuj protokół wydania</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="app-card-header border-top-0">
                    <a class="btn btn-link" href="{{ route('services.show', $service) }}">Anuluj</a>
                    <button class="btn btn-success" type="submit">Potwierdź wydanie</button>
                </div>
            </form>
        </div>

        <div class="col-12 col-lg-5">
            <section class="app-card" aria-labelledby="release-photos">
                <div class="app-card-header">
                    <h2 class="h6 mb-0" id="release-photos">Zdjęcia</h2>
                </div>
                <div class="app-card-body">
                    <x-photo-gallery :photos="$photos" :service="$service" group="release-photos" />
                </div>
            </section>
        </div>
    </div>
@endsection
