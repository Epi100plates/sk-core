# Warstwa wizualna: tokeny marki ze strony studio-komp.pl

| | |
|---|---|
| **Status** | zaakceptowana (właściciel, 2026-11-25) |
| **Źródło** | prośba właściciela (2026-11-25) + system designu strony: `Piotrekv3/src/styles/global.css`, `Piotrekv3/dist/brand/palette.json` |

## Kontekst

Aplikacja serwisowa dostała domyślny motyw Bootstrap (granat `#0d3b66`, promienie 12 px, cienie), który nie miał nic wspólnego z nową stroną firmową. Strona i aplikacja mają się w przyszłości połączyć (wspólna domena, linki, być może wspólna sesja), więc dwie różne tożsamości wizualne byłyby widoczne dla klienta i pracowników. Strona firmowa ma już dopracowany system: kierunek „precyzja” — dokumentacja warsztatowa, włoskowate linie, skalibrowany żółty sygnałowy, grafitowa chrome, fonty Schibsted Grotesk i IBM Plex Mono, promień 1 px, brak cieni.

## Decyzja

Aplikacja przejmuje tokeny i język wizualny strony, bez kopiowania jej komponentów:

1. **Tokeny** w `public/css/app.css` (`--ink`, `--graphite`, `--yellow`, `--surface`, `--line`, `--error`, `--ok`, skala typografii, `--radius: 1px`). Wartości są przepisane ze strony; strona pozostaje źródłem prawdy marki.
2. **Fonty lokalnie** w `public/fonts`: Schibsted Grotesk (variable, latin + latin-ext) i IBM Plex Mono (400/500, latin + latin-ext), `@font-face` z `unicode-range`, preload w `<head>`, precache w service workerze. Bez CDN i bez Node.
3. **Logo** w `public/brand`: wersja na ciemnym tle (nagłówek, panel logowania) i znak SK (ikony, PWA, wydruki). Ikony PWA (`icons:generate`) są skalowane ze znaku marki, a nie rysowane od nowa.
4. **Chrome aplikacji**: grafitowy pasek górny z logo, żółty przycisk główny, białe panele na tle `--surface`, włoskowate obramowania zamiast cieni, grafitowa dolna nawigacja na telefonie z żółtym wskaźnikiem aktywnej pozycji.
5. **Język kolorów statusów** (funkcjonalny, nie dekoracyjny): żółty = akcja potrzebna („Do wydania”), grafit = w toku, kontur = oczekiwanie, wyciszony = zakończone, czerwień = anulowane. Kolory przechodzą axe/WCAG AA.
6. **Ikony** jako inline SVG w `components/icon.blade.php` (1,5 px kreska, `currentColor`) — bez icon fontu, bez dodatkowych żądań i bez `unsafe-inline`.
7. **Motyw „dokumentacji”** wykorzystany tam, gdzie pasuje do pracy serwisu: pasek statystyk kolejki (liczby statusów są jednocześnie filtrem), wiersze danych na włoskowatych liniach, żółta karta zlecenia („docket”) na protokole wydania.
8. **Wydruki A4** (`public/css/print.css`) używają tych samych tokenów i fontów: grafitowy pasek z logo, żółty chip z numerem zlecenia, mono etykiety, linie podpisu.

## Konsekwencje

- **Brak nowych zależności i brak buildu** — CSS i fonty to pliki statyczne; wymóg „bez Node.js” pozostaje spełniony.
- **Waga**: +110 KB fontów (dwa pliki preloadowane, reszta doładowywana) i ~190 KB dwóch plików PNG z logo. Dalej: podmiana logo na SVG/WebP bez zmiany HTML.
- **Jeden arkusz** `public/css/app.css` (ok. 1,4 tys. linii) opisuje cały interfejs; zmiana wyglądu jest w jednym miejscu. Bootstrap dostarcza już tylko siatkę i utility.
- **`ASSET_VERSION` trzeba podbijać przy każdej zmianie CSS/JS** — od tego zależy cache przeglądarki i service workera (`?v=`).
- **Synchronizacja ze stroną jest ręczna**: strona buduje się w Node (Astro), aplikacja nie może współdzielić pakietu. Gdy strona zmieni tokeny, trzeba je przenieść do `app.css` (i odwrotnie, jeśli aplikacja wprowadzi nowy token). Docelowo warto trzymać jeden plik `tokens.css` kopiowany w obie strony.
- **Spójność funkcjonalna ważniejsza niż ozdobna**: żółty jest zarezerwowany dla akcji i wyróżnień, nie dla dekoracji; każdy ekran ma najwyżej jeden mocny akcent.
- **Dostępność**: brak nowych naruszeń axe (WCAG 2.1 A/AA) na 11 ekranach; kontrast tekstu na żółtym i grafitcie sprawdzony w testach Dusk (`tests/Browser/A11yTest.php`).
- **Konsekwencja organizacyjna**: katalog referencyjny `Piotrekv3/` (źródło assetów i tokenów) jest poza repozytorium; do repo trafiają tylko skopiowane pliki marki.

## Alternatywy

- **Zostawić Bootstrap z nowym `$primary`** — odrzucone: nie da się tak wyrazić włoskowatych linii, promienia 1 px, paska zlecenia ani typografii display.
- **Tailwind CSS** — odrzucone: wymaga Node.js i buildu, czego plan zabrania.
- **Współdzielony layout/iframe ze stroną** — odrzucone: aplikacja ma własną nawigację i sesję, a CSP zabrania ramek (`frame-ancestors 'none'`).
- **CSS-in-JS** — odrzucone: wymaga inline `<style>` lub `eval`, czyli złamania CSP.
