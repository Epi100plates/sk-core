<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Logowanie · {{ config('app.name') }}</title>
    <meta name="theme-color" content="#0d3b66">
    <link rel="icon" href="{{ asset('icons/icon-96.png') }}">
    <link rel="stylesheet" href="{{ asset('css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ config('app.asset_version') }}">
</head>
<body class="app-body" data-asset-version="{{ config('app.asset_version') }}" data-base-url="{{ url('/') }}">
<main class="container py-5" id="main">
    <div class="row justify-content-center">
        <div class="col-12 col-sm-8 col-md-6 col-lg-4">
            <div class="text-center mb-4">
                <h1 class="h4 mb-1">{{ config('app.name') }}</h1>
                <p class="service-meta mb-0">Zaloguj się, aby pracować z kolejką napraw.</p>
            </div>

            <div class="app-card">
                <div class="app-card-body">
                    <x-flash />

                    <form method="post" action="{{ route('login.attempt') }}" data-guard-submit>
                        @csrf

                        <div class="mb-3">
                            <label class="form-label" for="login">Login</label>
                            <input class="form-control" id="login" name="login" type="text"
                                   value="{{ old('login') }}" autocomplete="username" required autofocus>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="password">Hasło</label>
                            <input class="form-control" id="password" name="password" type="password"
                                   autocomplete="current-password" required>
                        </div>

                        <button class="btn btn-primary w-100" type="submit">Zaloguj</button>
                    </form>
                </div>
            </div>

            <p class="service-meta text-center mt-3 mb-0">
                Hasło startowe dostajesz od administratora — zmień je po pierwszym logowaniu.
            </p>
        </div>
    </div>
</main>
<script src="{{ asset('js/app.js') }}?v={{ config('app.asset_version') }}"></script>
<script src="{{ asset('js/pwa.js') }}?v={{ config('app.asset_version') }}"></script>
</body>
</html>
