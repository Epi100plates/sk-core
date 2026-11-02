# Hosting współdzielony, aplikacja w podkatalogu /serwis

| | |
|---|---|
| **Status** | zaakceptowana (właściciel, 2026-11-02) |
| **Źródło** | plan `docs/aegis/plans/2026-11-02-przepisanie-aplikacji-serwis.md` |

## Kontekst

Produkcja to `epii.pl/serwis`. Brak SSH, brak Composera, pliki wgrywane przez FTP/panel. Stara aplikacja miała na sztywno `BASE_URL` w kilku miejscach.

## Decyzja

Aplikacja używa wyłącznie `route()` i `asset()`, `APP_URL=https://epii.pl/serwis`. Wdrożenie: ZIP z `vendor/` z `build-release.ps1`. Inicjalizacja danych przez `php artisan app:install` (lokalnie) albo jednorazowy route `/__install?token=…` (na hostingu).

## Konsekwencje

Zakaz ścieżek zakorzenionych w `/` w szablonach (sprawdzane testem). Po instalacji `INSTALL_TOKEN` musi być pusty — inaczej route pozostaje aktywny.
