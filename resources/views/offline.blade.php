<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Brak połączenia · {{ config('app.name') }}</title>
    <meta name="theme-color" content="#0d3b66">
    <link rel="stylesheet" href="{{ asset('css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ config('app.asset_version') }}">
</head>
<body class="app-body">
<main class="container py-5" id="main">
    <div class="row justify-content-center">
        <div class="col-12 col-md-8 col-lg-6">
            <div class="app-card">
                <div class="app-card-body text-center">
                    <h1 class="h4 mb-2">Brak połączenia z internetem</h1>
                    <p class="service-meta mb-3">
                        Ta aplikacja w trybie offline pozwala tylko na podgląd tej strony.
                        Zmiany w zleceniach wymagają połączenia — nie zapisujemy danych klientów w pamięci telefonu.
                    </p>
                    <a class="btn btn-primary" href="{{ url('/') }}">Spróbuj ponownie</a>
                </div>
            </div>
        </div>
    </div>
</main>
</body>
</html>
