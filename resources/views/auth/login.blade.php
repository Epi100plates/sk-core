<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Logowanie · {{ config('app.name') }}</title>
    <meta name="theme-color" content="#2d343a">
    <meta name="color-scheme" content="light">
    <link rel="icon" href="{{ asset('icons/icon-96.png') }}">
    <link rel="preload" href="{{ asset('fonts/schibsted-grotesk-latin.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="{{ asset('css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ config('app.asset_version') }}">
</head>
<body class="app-body" data-asset-version="{{ config('app.asset_version') }}" data-base-url="{{ url('/') }}">
<main class="auth-page" id="main">
    <div class="auth-shell">
        <aside class="auth-aside">
            <img class="auth-aside__logo" src="{{ asset('brand/studio-komp-logo-dark.png') }}"
                 alt="Studio-Komp — serwis laptopów" width="1494" height="504">
            <div>
                <p class="eyebrow auth-aside__eyebrow">Panel serwisu</p>
                <p class="auth-aside__claim">Kolejka napraw, przyjęcia i wydania.</p>
            </div>
            <p class="auth-aside__note">Dostęp tylko dla pracowników serwisu</p>
        </aside>

        <div class="auth-card">
            <div class="auth-card__head">
                <h1 class="auth-title">Zaloguj się</h1>
                <p class="auth-lead">Konto zakłada administrator serwisu.</p>
            </div>

            <div class="auth-card__body">
                <x-flash />

                <form method="post" action="{{ route('login.attempt') }}" data-guard-submit>
                    @csrf

                    <div class="mb-3">
                        <label class="form-label" for="login">Login</label>
                        <input class="form-control @error('login') is-invalid @enderror" id="login" name="login" type="text"
                               value="{{ old('login') }}" autocomplete="username" required autofocus>
                        @error('login')<p class="invalid-feedback">{{ $message }}</p>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="password">Hasło</label>
                        <input class="form-control @error('password') is-invalid @enderror" id="password" name="password"
                               type="password" autocomplete="current-password" required>
                        @error('password')<p class="invalid-feedback">{{ $message }}</p>@enderror
                    </div>

                    <button class="btn btn-primary w-100" type="submit">Zaloguj</button>
                </form>
            </div>
        </div>
    </div>

    <p class="auth-foot">
        Hasło startowe dostajesz od administratora — zmień je po pierwszym logowaniu.
    </p>
</main>
<script src="{{ asset('js/app.js') }}?v={{ config('app.asset_version') }}"></script>
<script src="{{ asset('js/pwa.js') }}?v={{ config('app.asset_version') }}"></script>
</body>
</html>
