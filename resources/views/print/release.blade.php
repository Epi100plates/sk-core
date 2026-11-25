<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Protokół wydania · {{ $service->reference }}</title>
    <meta name="theme-color" content="#2d343a">
    <link rel="stylesheet" href="{{ asset('css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/print.css') }}?v={{ config('app.asset_version') }}">
</head>
<body class="print-body" data-autoprint="true">
<nav class="print-toolbar" aria-label="Akcje wydruku">
    <button type="button" data-print-trigger>Drukuj (Ctrl+P)</button>
    <a href="{{ route('services.show', $service) }}">Wróć do zlecenia</a>
</nav>

<main class="print-sheet" id="main">
    <header class="print-header">
        <div class="print-brand">
            <img class="print-mark" src="{{ asset('brand/studio-komp-mark.png') }}" alt="" width="45" height="45">
            <span class="print-brand__name">{{ config('app.name') }}</span>
        </div>
        <p class="print-ref">
            <span class="print-ref__label">Zlecenie</span>
            <span class="print-ref__value">{{ $service->reference }}</span>
        </p>
    </header>

    <div class="print-heading">
        <h1>Protokół wydania</h1>
        <p class="print-meta">
            <span class="print-meta__line">Wydano: <span class="print-meta__value">{{ \App\Domain\FieldLabels::formatDate($service->releasedAt) }}</span></span>
            <span class="print-meta__line">Wydrukowano: <span class="print-meta__value">{{ \App\Domain\FieldLabels::formatDate($printedAt) }}</span></span>
        </p>
    </div>

    <h2>Urządzenie</h2>
    <table>
        <tbody>
        <tr>
            <th scope="row">Model</th>
            <td>{{ $service->deviceModel }}</td>
        </tr>
        <tr>
            <th scope="row">Numer seryjny</th>
            <td>{{ $service->serialNumber ?? '—' }}</td>
        </tr>
        <tr>
            <th scope="row">Numer plomby</th>
            <td>{{ $service->sealNumber ?? '—' }}</td>
        </tr>
        <tr>
            <th scope="row">Zgłoszona usterka</th>
            <td class="text-pre-wrap">{{ $service->issue }}</td>
        </tr>
        <tr>
            <th scope="row">Uwagi</th>
            <td class="text-pre-wrap">{{ $service->notes ?? '—' }}</td>
        </tr>
        </tbody>
    </table>

    <h2>Rozliczenie</h2>
    <table>
        <tbody>
        <tr>
            <th scope="row">Telefon kontaktowy</th>
            <td>{{ $service->displayPhone() }}</td>
        </tr>
        <tr>
            <th scope="row">Cena dla klienta</th>
            <td><x-money :value="$service->price" /></td>
        </tr>
        <tr>
            <th scope="row">Koszt części</th>
            <td><x-money :value="$service->cost" /></td>
        </tr>
        <tr>
            <th scope="row">Data przyjęcia</th>
            <td>{{ \App\Domain\FieldLabels::formatDate($service->acceptedAt) }}</td>
        </tr>
        </tbody>
    </table>

    <p class="print-note">
        Klient potwierdza odbiór sprawnego urządzenia oraz wykonanie usługi zgodnie z powyższym zakresem.
    </p>

    <div class="print-signatures">
        <div class="signature-line">Podpis wydającego</div>
        <div class="signature-line">Podpis odbierającego</div>
    </div>
</main>

<script src="{{ asset('js/print.js') }}?v={{ config('app.asset_version') }}"></script>
</body>
</html>
