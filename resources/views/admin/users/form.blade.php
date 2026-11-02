@extends('layouts.app')

@section('title', $user === null ? 'Nowe konto' : 'Edycja konta')

@section('content')
    <h1 class="h5 mb-3">{{ $user === null ? 'Nowe konto pracownika' : 'Edycja konta '.$user->login }}</h1>

    <form class="app-card" method="post"
          action="{{ $user === null ? route('admin.users.store') : route('admin.users.update', $user) }}"
          data-guard-submit>
        @csrf
        @if ($user !== null)
            @method('PUT')
        @endif

        <div class="app-card-body">
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <label class="form-label" for="name">Imię / nazwa <span class="text-danger">*</span></label>
                    <input class="form-control @error('name') is-invalid @enderror" id="name" name="name" type="text"
                           maxlength="100" value="{{ old('name', $user->name ?? '') }}" required>
                    @error('name')<p class="invalid-feedback">{{ $message }}</p>@enderror
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label" for="login">Login <span class="text-danger">*</span></label>
                    <input class="form-control @error('login') is-invalid @enderror" id="login" name="login" type="text"
                           maxlength="50" value="{{ old('login', $user->login ?? '') }}" required>
                    @error('login')<p class="invalid-feedback">{{ $message }}</p>@enderror
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label" for="role">Rola <span class="text-danger">*</span></label>
                    <select class="form-control @error('role') is-invalid @enderror" id="role" name="role" required>
                        @foreach ($roles as $value => $label)
                            <option value="{{ $value }}" @selected(old('role', $user->role->value ?? 'employee') === $value)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                    @error('role')<p class="invalid-feedback">{{ $message }}</p>@enderror
                    <p class="service-meta mb-0">
                        Pracownik i technik mają pełny zakres pracy (przyjęcie, edycja, wydanie, ceny i koszty).
                        Administrator dodatkowo zarządza kontami.
                    </p>
                </div>

                <div class="col-12 col-md-6">
                    <div class="form-check mt-4">
                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1"
                               @checked(old('is_active', $user->isActive ?? true))>
                        <label class="form-check-label" for="is_active">Konto aktywne</label>
                    </div>
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label" for="password">
                        {{ $user === null ? 'Hasło startowe' : 'Nowe hasło (opcjonalnie)' }}
                        @if ($user === null)<span class="text-danger">*</span>@endif
                    </label>
                    <input class="form-control @error('password') is-invalid @enderror" id="password" name="password"
                           type="password" minlength="12" autocomplete="new-password" @if ($user === null) required @endif>
                    @error('password')<p class="invalid-feedback">{{ $message }}</p>@enderror
                    <p class="service-meta mb-0">Minimum 12 znaków. Pracownik zmieni je przy pierwszym logowaniu.</p>
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label" for="password_confirmation">Powtórz hasło</label>
                    <input class="form-control" id="password_confirmation" name="password_confirmation" type="password"
                           minlength="12" autocomplete="new-password" @if ($user === null) required @endif>
                </div>
            </div>
        </div>

        <div class="app-card-header border-top-0">
            <a class="btn btn-link" href="{{ route('admin.users.index') }}">Anuluj</a>
            <button class="btn btn-primary" type="submit">{{ $user === null ? 'Utwórz konto' : 'Zapisz zmiany' }}</button>
        </div>
    </form>
@endsection
