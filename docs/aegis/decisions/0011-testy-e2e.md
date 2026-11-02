# Testy e2e i dostępność: Laravel Dusk + axe-core

| | |
|---|---|
| **Status** | zaakceptowana (właściciel, 2026-11-02) |
| **Źródło** | plan `docs/aegis/plans/2026-11-02-przepisanie-aplikacji-serwis.md` |

## Kontekst

Warunek „bez Node” wyklucza Playwrighta i Puppeteera. Macierz akceptacji wymaga jednak
sprawdzenia zachowania w prawdziwej przeglądarce (dwa rozmiary okna) i audytu dostępności
(brak błędów krytycznych axe na kluczowych widokach).

## Decyzja

- **E2E w PHP przez Laravel Dusk** (`tests/Browser`), ChromeDriver jako binarka pobrana
  ręcznie (`vendor/laravel/dusk/bin/chromedriver-win.exe`) — `dusk:chrome-driver` nie działa
  na tej maszynie (brak CA w PHP CLI).
- Scenariusze uruchamiane na osobnym katalogu danych (`storage/app/dusk-data` przez
  `.env.dusk.local`), więc testy nigdy nie ruszają danych deweloperskich.
- **axe-core jako statyczny plik JS** `public/js/axe.min.js`, wstrzykiwany do strony jako
  `<script src>` (CSP `script-src 'self'` zabrania skryptów inline). Plik jest **wykluczony
  z paczki wdrożeniowej** (`build-release.ps1`), bo służy tylko testom.
- Uruchamianie: `php artisan serve --port=8000` (z `DATA_STORE_PATH` z `.env.dusk.local`)
  oraz `php artisan dusk --without-tty`.

## Konsekwencje

- **15 scenariuszy Dusk, wszystkie zielone**: przyjęcie z wydrukiem potwierdzenia, karta
  zlecenia i historia, kolejka bez akcji dla wydanych, wydanie z okna 390×844, brak panelu
  kont dla pracownika, brak poziomego scrolla na telefonie, brak dryfu terminu (D-44),
  archiwum wydanych, brak danych klientów w cache PWA oraz **6 audytów axe** (login, kolejka,
  formularz przyjęcia, karta zlecenia, panel kont, formularz wydania) — **zero błędów
  critical i serious**.
- Audyt axe wykrył i wymusił dwie poprawki: kontrast przycisków (biały tekst linku na
  `btn-light`) i brak `<h1>` na logowaniu; paleta `btn-outline-*` i `--bs-link-color` została
  przyciemniona do poziomu AA.
- ChromeDriver trzeba doinstalować ręcznie po aktualizacji Chrome; CI dla Dusk wymaga
  osobnego joba z Chrome.
