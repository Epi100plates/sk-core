# Stack: PHP 8.2+ / Laravel 12 / Bootstrap 5, bez Node.js i Pythona

| | |
|---|---|
| **Status** | zaakceptowana (właściciel, 2026-11-02) |
| **Źródło** | plan `docs/aegis/plans/2026-11-02-przepisanie-aplikacji-serwis.md` |

## Kontekst

Właściciel postawił twardy warunek: wyłącznie PHP, HTML, CSS i frameworki PHP. Stara aplikacja działa na hostingu współdzielonym z PHP 8.2, bez SSH i bez Composera na serwerze.

## Decyzja

Backend: PHP 8.2+ i Laravel 12. Styl: Bootstrap 5.3 (pliki CSS/JS skopiowane do `public/`, nie z CDN). Testy: Pest. Narzędzia: Pint, Larastan (PHPStan poziom 6). Zero `package.json`, zero `node_modules`, zero plików `.py` — kontrola w CI.

## Konsekwencje

Wdrożenie to ZIP z katalogiem `vendor/` (brak Composera na serwerze). Interaktywność UI musi działać bez buildu — patrz ADR 0004. Nowe biblioteki JS tylko jako gotowe pliki albo napisane ręcznie.
