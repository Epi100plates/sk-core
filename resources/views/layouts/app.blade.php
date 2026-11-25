<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Kolejka') · {{ config('app.name') }}</title>
    <link rel="manifest" href="{{ route('manifest') }}">
    <meta name="theme-color" content="#2d343a">
    <meta name="color-scheme" content="light">
    <link rel="icon" href="{{ asset('icons/icon-96.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('icons/icon-192.png') }}">
    <link rel="preload" href="{{ asset('fonts/schibsted-grotesk-latin.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{{ asset('fonts/schibsted-grotesk-latin-ext.woff2') }}" as="font" type="font/woff2" crossorigin>
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
    <div class="app-header__inner">
        <a class="app-brand" href="{{ route('services.queue') }}">
            <img src="{{ asset('brand/studio-komp-logo-trim.png') }}" alt="Studio-Komp — serwis laptopów">
            <span class="visually-hidden">Kolejka napraw</span>
        </a>

        <form class="app-search" method="get" action="{{ route('services.search') }}" role="search">
            <label class="visually-hidden" for="global-search">Szukaj zlecenia</label>
            <input class="form-control" id="global-search" type="search"
                   name="q" value="{{ request()->routeIs('services.search') ? request('q') : '' }}"
                   placeholder="Telefon, plomba, model…" minlength="3" required>
            <button class="btn btn-outline-light" type="submit">
                <x-icon name="search" :size="16" />
                <span class="visually-hidden">Szukaj</span>
            </button>
        </form>

        <div class="app-header__actions">
            <a class="btn btn-primary btn-sm" href="{{ route('services.create') }}" aria-label="Przyjmij laptopa">
                <x-icon name="plus" :size="16" />
                <span class="btn-label">Przyjmij</span>
            </a>
            @auth
                <span class="app-user">{{ auth()->user()->getAuthName() }}</span>
                <form method="post" action="{{ route('logout') }}" data-clear-cache-on-submit>
                    @csrf
                    <button class="app-header__logout" type="submit" aria-label="Wyloguj z aplikacji">
                        <x-icon name="logout" :size="15" />
                        <span class="btn-label">Wyloguj</span>
                    </button>
                </form>
            @endauth
        </div>
    </div>
</header>

<div class="app-shell">
    <nav class="app-sidebar" aria-label="Nawigacja główna">
        <p class="app-sidebar__label eyebrow">Kolejka</p>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('services.queue') ? 'active' : '' }}"
                   href="{{ route('services.queue') }}" @if(request()->routeIs('services.queue')) aria-current="page" @endif>
                    <x-icon name="queue" :size="17" /> Kolejka
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('services.create') ? 'active' : '' }}"
                   href="{{ route('services.create') }}">
                    <x-icon name="plus" :size="17" /> Przyjmij laptopa
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('services.search') ? 'active' : '' }}"
                   href="{{ route('services.search') }}">
                    <x-icon name="search" :size="17" /> Szukaj
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('services.released') ? 'active' : '' }}"
                   href="{{ route('services.released') }}">
                    <x-icon name="package" :size="17" /> Wydane
                </a>
            </li>
        </ul>

        @can('viewAny', App\Domain\Data\UserRecord::class)
            <p class="app-sidebar__label eyebrow">Administracja</p>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}"
                       href="{{ route('admin.users.index') }}">
                        <x-icon name="users" :size="17" /> Użytkownicy
                    </a>
                </li>
            </ul>
        @endcan

        <p class="app-sidebar__label eyebrow">Konto</p>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('account.password') ? 'active' : '' }}"
                   href="{{ route('account.password') }}">
                    <x-icon name="key" :size="17" /> Zmień hasło
                </a>
            </li>
        </ul>

        <p class="app-sidebar__foot">
            {{ config('app.name') }}<br>
            Magazyn danych: {{ config('persistence.driver') }}
        </p>
    </nav>

    <main class="app-main" id="main">
        <x-flash />
        @yield('content')
    </main>
</div>

<nav class="app-bottom-nav" aria-label="Nawigacja dolna">
    <a href="{{ route('services.queue') }}" class="{{ request()->routeIs('services.queue') ? 'active' : '' }}"
       @if(request()->routeIs('services.queue')) aria-current="page" @endif>
        <x-icon name="queue" :size="18" /> Kolejka
    </a>
    <a href="{{ route('services.create') }}" class="{{ request()->routeIs('services.create') ? 'active' : '' }}">
        <x-icon name="plus" :size="18" /> Przyjmij
    </a>
    <a href="{{ route('services.search') }}" class="{{ request()->routeIs('services.search') ? 'active' : '' }}">
        <x-icon name="search" :size="18" /> Szukaj
    </a>
    <a href="{{ route('services.released') }}" class="{{ request()->routeIs('services.released') ? 'active' : '' }}">
        <x-icon name="package" :size="18" /> Wydane
    </a>
    @can('viewAny', App\Domain\Data\UserRecord::class)
        <a href="{{ route('admin.users.index') }}" class="{{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
            <x-icon name="users" :size="18" /> Konta
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
