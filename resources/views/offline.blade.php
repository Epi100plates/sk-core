<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Brak połączenia · {{ config('app.name') }}</title>
    <meta name="theme-color" content="#2d343a" data-theme-color-light="#2d343a" data-theme-color-dark="#0c1012">
    <meta name="color-scheme" content="light dark">
    <script src="{{ asset('js/theme.js') }}?v={{ config('app.asset_version') }}"></script>
    <link rel="stylesheet" href="{{ asset('css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ config('app.asset_version') }}">
</head>
<body class="app-body">
<main class="auth-page" id="main">
    <div class="app-card offline-card">
        <div class="app-card-header">
            <span class="eyebrow">Tryb offline</span>
            <x-icon name="wifi-off" :size="18" />
        </div>
        <div class="app-card-body">
            <h1 class="auth-title">Brak połączenia z internetem</h1>
            <p class="service-meta mt-2 mb-3">
                Ta strona jest tylko podglądem. Zmiany w zleceniach wymagają połączenia —
                nie zapisujemy danych klientów w pamięci telefonu.
            </p>
            <a class="btn btn-primary w-100" href="{{ url('/') }}">Spróbuj ponownie</a>
        </div>
    </div>
</main>
</body>
</html>
