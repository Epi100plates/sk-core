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

{{-- No dark header and no logo: everything the header carried (search, the way
     in, the way out) lives in one compact bar above the content. The queue adds
     its stage filter as a second row of that bar, so the counts and the controls
     arrive together instead of in two boxes. --}}
<div class="app-shell">
    <nav class="app-sidebar" aria-label="Nawigacja główna">
        <p class="app-sidebar__label eyebrow">Zlecenia</p>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('services.queue') ? 'active' : '' }}"
                   href="{{ route('services.queue') }}" @if(request()->routeIs('services.queue')) aria-current="page" @endif>
                    <x-icon name="queue" :size="17" /> Kolejka
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('services.create') ? 'active' : '' }}"
                   href="{{ route('services.create') }}" @if(request()->routeIs('services.create')) aria-current="page" @endif>
                    <x-icon name="plus" :size="17" /> Przyjmij laptopa
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('services.released') ? 'active' : '' }}"
                   href="{{ route('services.released') }}" @if(request()->routeIs('services.released')) aria-current="page" @endif>
                    <x-icon name="package" :size="17" /> Wydane
                </a>
            </li>
        </ul>

        @isset($sidebarQueue)
            {{-- A compact copy of the queue, not a second full screen: the model
                 is the label, the dot carries the stage, and the count links to
                 the complete list. The list takes the free height of the rail
                 and scrolls inside it, so the account and admin groups stay
                 pinned to the bottom. --}}
            <div class="app-sidebar__queue" data-async-region="sidebar-queue">
                <p class="app-sidebar__label eyebrow" id="sidebar-queue-title">Kolejka komputerów</p>

                @if ($sidebarQueue === [])
                    <p class="app-sidebar__queue-empty">Brak komputerów w kolejce.</p>
                @else
                    <ul class="sidebar-queue" aria-labelledby="sidebar-queue-title">
                        @foreach ($sidebarQueue as $queued)
                            <li>
                                <a class="sidebar-queue__item" href="{{ route('services.show', $queued) }}"
                                   title="{{ $queued->deviceModel }} · {{ $queued->status->label() }}">
                                    <span class="sidebar-queue__dot" data-stage="{{ $queued->status->value }}"
                                          aria-hidden="true"></span>
                                    <span class="sidebar-queue__model">{{ \Illuminate\Support\Str::limit($queued->deviceModel, 20) }}</span>
                                    <span class="sidebar-queue__ref">#{{ \Illuminate\Support\Str::substr($queued->reference, -4) }}</span>
                                    <span class="visually-hidden">, {{ $queued->status->label() }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>

                    <a class="sidebar-queue__all" href="{{ route('services.queue') }}">
                        Cała kolejka
                        <span class="sidebar-queue__count">({{ $sidebarQueueTotal }})</span>
                    </a>
                @endif
            </div>
        @endisset

        <div class="app-sidebar__bottom">
            <p class="app-sidebar__label eyebrow">Konto</p>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('account.password') ? 'active' : '' }}"
                       href="{{ route('account.password') }}" @if(request()->routeIs('account.password')) aria-current="page" @endif>
                        <x-icon name="key" :size="17" /> Zmień hasło
                    </a>
                </li>
            </ul>

            @can('viewAny', App\Domain\Data\UserRecord::class)
                <p class="app-sidebar__label eyebrow">Administracja</p>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}"
                           href="{{ route('admin.users.index') }}" @if(request()->routeIs('admin.users.*')) aria-current="page" @endif>
                            <x-icon name="users" :size="17" /> Użytkownicy
                        </a>
                    </li>
                </ul>
            @endcan
        </div>
    </nav>

    <main class="app-main" id="main">
        <div class="app-bar">
            <div class="app-bar__row">
                @hasSection('bar-title')
                    <div class="app-bar__title">@yield('bar-title')</div>
                @endif

                <div class="app-bar__controls">
                    <form class="app-search" method="get" action="{{ route('services.queue') }}" role="search"
                          data-live-search>
                        <label class="visually-hidden" for="global-search">Szukaj w kolejce</label>
                        <input class="form-control" id="global-search" type="search"
                               name="q" value="{{ is_string(request('q')) ? request('q') : '' }}"
                               placeholder="Model, telefon, plomba…" autocomplete="off"
                               inputmode="search" enterkeyhint="search" autocapitalize="off" spellcheck="false"
                               aria-describedby="global-search-hint">
                        @if (is_string(request('status')))
                            <input type="hidden" name="status" value="{{ request('status') }}">
                        @endif
                        <button class="btn btn-outline-secondary" type="submit">
                            <x-icon name="search" :size="16" />
                            <span class="visually-hidden">Szukaj</span>
                        </button>
                        <span class="visually-hidden" id="global-search-hint">Od 3 znaków lista filtruje się sama.</span>
                    </form>

                    <div class="app-bar__actions">
                        {{-- The bar's primary action points at the screen you are not on:
                             on the intake form "Przyjmij" would only reload the page,
                             so the same slot leads back to the queue. --}}
                        @if (request()->routeIs('services.create'))
                            <a class="btn btn-primary btn-sm" href="{{ route('services.queue') }}" aria-label="Kolejka">
                                <x-icon name="queue" :size="16" />
                                <span class="btn-label">Kolejka</span>
                            </a>
                        @else
                            <a class="btn btn-primary btn-sm" href="{{ route('services.create') }}" aria-label="Przyjmij laptopa">
                                <x-icon name="plus" :size="16" />
                                <span class="btn-label">Przyjmij</span>
                            </a>
                        @endif
                        @auth
                            <span class="app-user">{{ auth()->user()->getAuthName() }}</span>
                            <form method="post" action="{{ route('logout') }}" data-clear-cache-on-submit>
                                @csrf
                                <button class="app-bar__logout" type="submit" aria-label="Wyloguj z aplikacji">
                                    <x-icon name="logout" :size="15" />
                                    <span class="btn-label">Wyloguj</span>
                                </button>
                            </form>
                        @endauth
                    </div>
                </div>
            </div>

            @hasSection('bar')
                <div class="app-bar__row app-bar__row--filters">@yield('bar')</div>
            @endif
        </div>

        {{-- The async layer swaps this region, so a message from the previous
             action never stays on screen next to the result of the new one. --}}
        <div data-async-region="flash">
            <x-flash />
        </div>

        <p class="visually-hidden" id="async-status" role="status" aria-live="polite"></p>

        @yield('content')
    </main>
</div>

<nav class="app-bottom-nav" aria-label="Nawigacja dolna">
    <a href="{{ route('services.queue') }}" class="{{ request()->routeIs('services.queue') ? 'active' : '' }}"
       @if(request()->routeIs('services.queue')) aria-current="page" @endif>
        <x-icon name="queue" :size="18" /> Kolejka
    </a>
    <a href="{{ route('services.create') }}" class="{{ request()->routeIs('services.create') ? 'active' : '' }}" @if(request()->routeIs('services.create')) aria-current="page" @endif>
        <x-icon name="plus" :size="18" /> Przyjmij
    </a>
    <a href="{{ route('services.released') }}" class="{{ request()->routeIs('services.released') ? 'active' : '' }}" @if(request()->routeIs('services.released')) aria-current="page" @endif>
        <x-icon name="package" :size="18" /> Wydane
    </a>
    @can('viewAny', App\Domain\Data\UserRecord::class)
        <a href="{{ route('admin.users.index') }}" class="{{ request()->routeIs('admin.users.*') ? 'active' : '' }}" @if(request()->routeIs('admin.users.*')) aria-current="page" @endif>
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
