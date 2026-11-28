@extends('layouts.app')

@section('title', $user === null ? 'Nowe konto' : 'Edycja konta')

@section('content')
    <div class="page-head">
        <div class="page-head__main">
            <span class="eyebrow">Administracja</span>
            <h1 class="page-title">{{ $user === null ? 'Nowe konto' : 'Konto '.$user->login }}</h1>
            <p class="page-head__meta">
                {{ $user === null
                    ? 'Przekaż hasło startowe pracownikowi — zmieni je przy pierwszym logowaniu.'
                    : 'Zmień dane konta albo odbierz dostęp.' }}
            </p>
        </div>
    </div>

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
                        Szef zatwierdza wydanie i zarządza kontami. Serwisant admin ma pełny zakres pracy
                        i zarządza kontami, ale nie zatwierdza wydania. Technik i pracownik mają ten sam
                        zakres pracy.
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
                    <p class="service-meta mb-0">Minimum 12 znaków.</p>
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label" for="password_confirmation">Powtórz hasło</label>
                    <input class="form-control" id="password_confirmation" name="password_confirmation" type="password"
                           minlength="12" autocomplete="new-password" @if ($user === null) required @endif>
                </div>
            </div>
        </div>

        <div class="app-card-footer">
            <a class="btn btn-link me-auto" href="{{ route('admin.users.index') }}">Anuluj</a>
            <button class="btn btn-primary" type="submit">{{ $user === null ? 'Utwórz konto' : 'Zapisz zmiany' }}</button>
        </div>
    </form>
@endsection
