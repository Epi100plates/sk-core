<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Kolejka') · {{ config('app.name') }}</title>
    <link rel="manifest" href="{{ route('manifest') }}">
    <meta name="theme-color" content="#0d3b66">
    <meta name="color-scheme" content="light">
    <link rel="icon" href="{{ asset('icons/icon-96.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('icons/icon-192.png') }}">
    <link rel="stylesheet" href="{{ asset('css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ config('app.asset_version') }}">
    @stack('styles')
</head>
<body class="app-body"
      data-asset-version="{{ config('app.asset_version') }}"
      data-base-url="{{ url('/') }}">

<div id="offline-banner" class="offline-banner d-none" role="status" aria-live="polite">
    Brak połączenia z internetem — zmiany są zablokowane. Wróć do zasięgu i odśwież stronę.
</div>

<header class="app-header">
    <div class="container-fluid d-flex flex-wrap align-items-center gap-2 py-2">
        <a class="app-brand d-flex align-items-center gap-2 me-auto" href="{{ route('services.queue') }}">
            <span aria-hidden="true">🛠</span>
            <span>{{ config('app.name') }}</span>
        </a>

        <form class="search-form d-flex gap-1" method="get" action="{{ route('services.search') }}" role="search">
            <label class="visually-hidden" for="global-search">Szukaj zlecenia</label>
            <input class="form-control form-control-sm" id="global-search" type="search"
                   name="q" value="{{ request()->routeIs('services.search') ? request('q') : '' }}"
                   placeholder="Telefon, plomba, model…" minlength="3" required>
            <button class="btn btn-sm btn-light" type="submit">Szukaj</button>
        </form>

        <div class="d-flex align-items-center gap-2">
            <a class="btn btn-sm btn-light" href="{{ route('services.create') }}">+ Przyjmij</a>
            @auth
                <span class="d-none d-md-inline small">{{ auth()->user()->getAuthName() }}</span>
                <form method="post" action="{{ route('logout') }}" data-clear-cache-on-submit>
                    @csrf
                    <button class="btn btn-sm btn-outline-light" type="submit">Wyloguj</button>
                </form>
            @endauth
        </div>
    </div>
</header>

<div class="app-shell">
    <nav class="app-sidebar" aria-label="Nawigacja główna">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('services.queue') ? 'active' : '' }}"
                   href="{{ route('services.queue') }}" @if(request()->routeIs('services.queue')) aria-current="page" @endif>
                    <span aria-hidden="true">📋</span> Kolejka
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('services.create') ? 'active' : '' }}"
                   href="{{ route('services.create') }}">
                    <span aria-hidden="true">➕</span> Przyjmij laptopa
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('services.search') ? 'active' : '' }}"
                   href="{{ route('services.search') }}">
                    <span aria-hidden="true">🔍</span> Szukaj
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('services.released') ? 'active' : '' }}"
                   href="{{ route('services.released') }}">
                    <span aria-hidden="true">📦</span> Wydane
                </a>
            </li>
            @can('viewAny', App\Domain\Data\UserRecord::class)
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}"
                       href="{{ route('admin.users.index') }}">
                        <span aria-hidden="true">👥</span> Użytkownicy
                    </a>
                </li>
            @endcan
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('account.password') ? 'active' : '' }}"
                   href="{{ route('account.password') }}">
                    <span aria-hidden="true">🔑</span> Hasło
                </a>
            </li>
        </ul>
    </nav>

    <main class="app-main" id="main">
        <x-flash />
        @yield('content')
    </main>
</div>

<nav class="app-bottom-nav" aria-label="Nawigacja dolna">
    <a href="{{ route('services.queue') }}" class="{{ request()->routeIs('services.queue') ? 'active' : '' }}">
        <span class="nav-icon" aria-hidden="true">📋</span> Kolejka
    </a>
    <a href="{{ route('services.create') }}" class="{{ request()->routeIs('services.create') ? 'active' : '' }}">
        <span class="nav-icon" aria-hidden="true">➕</span> Przyjmij
    </a>
    <a href="{{ route('services.search') }}" class="{{ request()->routeIs('services.search') ? 'active' : '' }}">
        <span class="nav-icon" aria-hidden="true">🔍</span> Szukaj
    </a>
    <a href="{{ route('services.released') }}" class="{{ request()->routeIs('services.released') ? 'active' : '' }}">
        <span class="nav-icon" aria-hidden="true">📦</span> Wydane
    </a>
    @can('viewAny', App\Domain\Data\UserRecord::class)
        <a href="{{ route('admin.users.index') }}" class="{{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
            <span class="nav-icon" aria-hidden="true">👥</span> Konta
        </a>
    @endcan
</nav>

<div id="lightbox" class="lightbox" role="dialog" aria-modal="true" aria-label="Podgląd zdjęcia" hidden>
    <button class="btn btn-light lightbox-close" type="button" data-lightbox-close aria-label="Zamknij podgląd">✕</button>
    <img data-lightbox-image src="" alt="">
    <div class="lightbox-controls">
        <button class="btn btn-light" type="button" data-lightbox-prev aria-label="Poprzednie zdjęcie">◀</button>
        <span data-lightbox-counter aria-live="polite">1 / 1</span>
        <button class="btn btn-light" type="button" data-lightbox-next aria-label="Następne zdjęcie">▶</button>
    </div>
</div>

<script src="{{ asset('js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('js/app.js') }}?v={{ config('app.asset_version') }}"></script>
<script src="{{ asset('js/lightbox.js') }}?v={{ config('app.asset_version') }}"></script>
<script src="{{ asset('js/pwa.js') }}?v={{ config('app.asset_version') }}"></script>
@stack('scripts')
</body>
</html>
