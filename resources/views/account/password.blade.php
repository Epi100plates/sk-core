@extends('layouts.app')

@section('title', 'Hasło')

@section('content')
    <h1 class="h5 mb-3">Zmiana hasła</h1>

    <form class="app-card" method="post" action="{{ route('account.password.update') }}" data-guard-submit>
        @csrf
        @method('PUT')

        <div class="app-card-body">
            <p class="service-meta">
                Konto: <strong>{{ $user->login }}</strong> ({{ $user->role->label() }})
            </p>

            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <label class="form-label" for="current_password">Obecne hasło <span class="text-danger">*</span></label>
                    <input class="form-control @error('current_password') is-invalid @enderror" id="current_password"
                           name="current_password" type="password" autocomplete="current-password" required>
                    @error('current_password')<p class="invalid-feedback">{{ $message }}</p>@enderror
                </div>

                <div class="col-12 col-md-6"></div>

                <div class="col-12 col-md-6">
                    <label class="form-label" for="password">Nowe hasło <span class="text-danger">*</span></label>
                    <input class="form-control @error('password') is-invalid @enderror" id="password" name="password"
                           type="password" minlength="12" autocomplete="new-password" required>
                    @error('password')<p class="invalid-feedback">{{ $message }}</p>@enderror
                    <p class="service-meta mb-0">Minimum 12 znaków.</p>
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label" for="password_confirmation">Powtórz nowe hasło <span class="text-danger">*</span></label>
                    <input class="form-control" id="password_confirmation" name="password_confirmation" type="password"
                           minlength="12" autocomplete="new-password" required>
                </div>
            </div>
        </div>

        <div class="app-card-header border-top-0">
            <a class="btn btn-link" href="{{ route('services.queue') }}">Wróć do kolejki</a>
            <button class="btn btn-primary" type="submit">Zmień hasło</button>
        </div>
    </form>
@endsection
