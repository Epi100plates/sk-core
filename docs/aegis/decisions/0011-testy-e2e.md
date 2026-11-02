# Testy e2e: Laravel Dusk (PHP + ChromeDriver)

| | |
|---|---|
| **Status** | zaakceptowana (właściciel, 2026-11-02) |
| **Źródło** | plan `docs/aegis/plans/2026-11-02-przepisanie-aplikacji-serwis.md` |

## Kontekst

Warunek „bez Node” wyklucza Playwrighta i Puppeteera.

## Decyzja

E2E w PHP przez Laravel Dusk; axe-core jako plik JS wstrzykiwany skryptem. Weryfikacja wizualna tej iteracji wykonana w Chromium przez narzędzie audytu (nie jest zależnością projektu).

## Konsekwencje

Dusk wymaga ChromeDrivera (binarka, nie Node). W tej iteracji nacisk położono na testy Pest w obu magazynach danych; scenariusze Dusk są przygotowane do uruchomienia po instalacji ChromeDrivera (patrz raport weryfikacji).
