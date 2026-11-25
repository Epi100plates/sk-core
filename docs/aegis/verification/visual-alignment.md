# Wyrównanie wizualne ze stroną studio-komp.pl

| | |
|---|---|
| **Data** | 2026-11-25 |
| **Zakres** | cały interfejs aplikacji serwisowej + wydruki A4 + ikony PWA |
| **Decyzja** | [ADR 0014](../decisions/0014-warstwa-wizualna.md) |
| **Źródło marki** | strona firmowa (`Piotrekv3/src/styles/global.css`, `Piotrekv3/dist/brand/`) |

## Cel i metoda

Właściciel poprosił, aby aplikacja przypominała wizualnie stronę firmową, bo w przyszłości oba
serwisy będą połączone. Punktem wyjścia był **system designu strony**, nie pojedynczy zrzut ekranu:

1. odczyt tokenów (`:root` w `global.css`), palety (`dist/brand/palette.json`) i fontów (`package.json`),
2. przegląd złożenia (nagłówek, hero, kafelki, cennik, stopka, pasek mobilny) na zrzutach 1366×900 i 390×844,
3. przeniesienie tokenów do `public/css/app.css` i zbudowanie na nich komponentów aplikacji,
4. weryfikacja w prawdziwej przeglądarce (Playwright/Chromium 158) i axe-core, a następnie w Dusk.

Zrzuty referencyjne: [ref-home-desktop.png](assets/ref-home-desktop.png),
[ref-home-mobile.png](assets/ref-home-mobile.png).

## Tokeny (wspólne ze stroną)

| Token | Wartość | Użycie w aplikacji |
|---|---|---|
| `--ink` | `#242a30` | tekst podstawowy, obramowania aktywne |
| `--graphite` | `#2d343a` | pasek górny, dolna nawigacja, status „w toku”, kafle statystyk |
| `--paper` | `#ffffff` | panele, formularze, karty zleceń |
| `--surface` | `#f4f5f2` | tło aplikacji, wiersze pomocnicze |
| `--surface-2` | `#e9ebe6` | tła zakończonych zleceń |
| `--line` / `--line-strong` | `#dcdfd9` / `#c6cabf` | włoskowate linie i obramowania pól |
| `--muted` / `--muted-2` | `#5f645e` / `#6c716b` | etykiety, tekst pomocniczy |
| `--yellow` | `#ffd400` | przycisk główny, aktywne filtry, „Do wydania”, karta zlecenia na wydruku |
| `--yellow-press` | `#ecc400` | stan `:active` |
| `--error` / `--ok` | `#b42318` / `#1f7a3f` | anulowanie, potwierdzenia |
| `--radius` | `1px` | wszystkie panele, pola, przyciski |
| Fonty | Schibsted Grotesk (variable) + IBM Plex Mono 400/500 | nagłówki i tekst / etykiety mono, numery zleceń |

Cienie w interfejsie zniknęły — głębię daje tło `--surface`, obramowanie 1 px i żółty akcent.
Na stronie i w aplikacji obowiązuje ta sama zasada „jeden mocny akcent na ekran”.

## Co konkretnie się zmieniło

| Obszar | Zmiana | Pliki |
|---|---|---|
| Nagłówek | grafitowy pasek, prawdziwe logo (PNG przycięte do tuszu), wyszukiwarka w pasku, żółty przycisk „Przyjmij”, mono login | `resources/views/layouts/app.blade.php`, `public/css/app.css` |
| Nawigacja | sidebar z grupami (Kolejka / Administracja / Konto) i żółtym znacznikiem aktywności; na telefonie grafitowa dolna nawigacja z ikonami SVG | jw., `resources/views/components/icon.blade.php` |
| Kolejka | pasek statystyk = filtr etapów (radio, liczby są wartościami pól), sekcje etapów z nagłówkiem i licznikiem, karty zleceń w stylu „biletu” z 4 px paskiem koloru etapu po lewej | `resources/views/services/queue.blade.php`, `services/_queue-results.blade.php`, `components/service-card.blade.php` |
| Karta zlecenia | dwukolumnowe wiersze danych (etykieta/wartość), historia jako timeline z kropkami, akcje: żółty „następny etap”, kontur „Edytuj”, grafit „Wydaj” | `resources/views/services/show.blade.php` |
| Wydanie | żółta karta zlecenia („docket”) z numerem, modelem, telefonem i terminem — odpowiednik karty z hero strony | `resources/views/services/release.blade.php` |
| Logowanie | podział na grafitowy panel marki (logo + claim) i formularz na papierze; żółty przycisk pełnej szerokości | `resources/views/auth/login.blade.php` |
| Formularze | pola 44 px, etykiety 12,5 px/600, obramowanie 1 px, czytelny stan błędu, wspólny pasek akcji | `components/service-form.blade.php`, `admin/users/form.blade.php`, `account/password.blade.php` |
| Pozostałe ekrany | archiwum wydane, panel kont, błąd domenowy, offline, instalacja — wspólny nagłówek strony (eyebrow + tytuł) i pasek filtrów | `services/released.blade.php`, `admin/users/index.blade.php`, `errors/domain.blade.php`, `offline.blade.php`, `install/result.blade.php` |
| Wydruki A4 | grafitowy pasek z logo, żółty chip z numerem zlecenia, mono etykiety, linie podpisu; dalej jedna strona A4 | `public/css/print.css`, `resources/views/print/*.blade.php` |
| Ikony/PWA | ikony generowane ze znaku marki (GD), motyw `#2d343a`, tło `#f4f5f2`, fonty w precache service workera | `app/Console/Commands/GenerateIcons.php`, `app/Http/Controllers/ManifestController.php`, `public/service-worker.js` |

Zrzuty aplikacji: [kolejka — desktop](assets/app-queue-desktop.png),
[kolejka — telefon](assets/app-queue-mobile.png), [karta zlecenia](assets/app-show-desktop.png),
[karta zlecenia — telefon](assets/app-show-mobile.png), [wydanie](assets/app-release-desktop.png),
[logowanie](assets/app-login-desktop.png), [wyniki szukania w kolejce](assets/app-queue-search-desktop.png),
[archiwum](assets/app-released-desktop.png), [wydruk przyjęcia](assets/print-acceptance.png).

## Dostępność

Kontrasty par tokenów (WCAG 2.1, obliczone dla wartości z palety):

| Para | Współczynnik | Wymóg |
|---|---|---|
| `--ink` na `--paper` | 14,49:1 | AA 4,5 ✔ |
| `--muted` na `--paper` | 6,05:1 | AA 4,5 ✔ |
| `--muted` na `--surface` | 5,53:1 | AA 4,5 ✔ |
| `--ink` na `--yellow` | 10,12:1 | AA 4,5 ✔ |
| `--paper` na `--graphite` | 12,62:1 | AA 4,5 ✔ |
| `--yellow` na `--graphite` | 8,82:1 | AA 4,5 ✔ |
| `--on-dark-muted` na `--graphite` | 6,32:1 | AA 4,5 ✔ |
| `--error` na `--paper` | 6,57:1 | AA 4,5 ✔ |

Uwagi wykonawcze: żółty nigdy nie niesie białego tekstu (zawsze `--ink`), stan aktywny filtra
jest widoczny także bez koloru (odwrócenie tła + `focus-visible`), a `text-transform: uppercase`
na etykietach mono nie zmienia treści w DOM.

Wynik axe-core 4.10 (reguły WCAG 2.0/2.1 A+AA) na 12 widokach: **0 naruszeń** — 11 tras
w sesji pracownika (kolejka, karta, wydanie, edycja, przyjęcie, konta, archiwum, szukanie, hasło,
dwa wydruki) + ekran logowania w osobnym kontekście przeglądarki.

## Odporność układu

Sprawdzono `document.documentElement.scrollWidth <= innerWidth` na 390×844 dla tras:
`/`, `/services/{id}`, `/services/{id}/release`, `/services/create`, `/services/{id}/edit`,
`/admin/users`, `/released`, `/search?q=…`, `/account/password` oraz podglądu wydruku A4 —
**brak poziomego przewijania** na wszystkich. Widełki: sidebar pojawia się od 1024 px,
dolna nawigacja działa poniżej, akcje na telefonie rozciągają się na całą szerokość.

## Bramki i pomiary

```
php artisan test                 257 passed (936 assertions)   ← json + mysql (MariaDB 10.4)
php artisan dusk                 15 passed (41 assertions)     ← w tym 6 audytów axe
vendor/bin/pint --test           PASS (157 plików)
vendor/bin/phpstan analyse       OK, bez błędów (poziom 6)
```

Czas renderowania kolejki nie zmienił się istotnie: `bench:queue --services=200` mierzy ten sam
widok (wynik w [runtime-verification.md](runtime-verification.md)).

## Ograniczenia i rzeczy do sprawdzenia przez właściciela

1. **Papier vs ekran** — wydruki sprawdzone w podglądzie A4 w Chromium (jedna strona, brak
   przycięć), ale nie na fizycznej drukarce. Grafitowy pasek wymaga włączonej opcji „tła grafiki”;
   bez niej dokument pozostaje czytelny, bo cała treść jest na papierze (znika tylko nazwa firmy,
   znak marki i chip z numerem zostają).
2. **Fonty** — łacina i latin-ext są lokalne; egzotyczne znaki spadają na font systemowy.
   Sprawdzone w Chromium 158; Firefox/Safari nie były uruchamiane.
3. **Telefon z realnym urządzeniem** — testy Dusk używają okna 390×844 w Chromium, nie fizycznego
   iPhone'a/Androida; warto potwierdzić przy UAT (bezpieczne obszary, autofill, klawiatura).
4. **Waga** — logo to dwa PNG (~190 KB razem). Dalej: SVG/WebP i jeden wariant z `currentColor`.
5. **Synchronizacja ze stroną** — ręczna. Gdy strona zmieni tokeny, trzeba je przenieść do
   `app.css`; wspólny plik `tokens.css` byłby następnym krokiem.
6. **Cache** — po każdej zmianie CSS/JS trzeba podbić `ASSET_VERSION`; w tej zmianie doszło do 9.

> **Aktualizacja 2026-11-25 (późniejsza runda):** przebudowa treści i przepływów zmieniła układ
> karty zlecenia (gęsty wiersz zamiast biletu), historię zmian, formularze i strony błędów —
> opis i zrzuty w [content-and-security-pass.md](content-and-security-pass.md), decyzja
> [0015](../decisions/0015-tresc-i-przeplywy.md). Tokeny, fonty i kolory marki z tego dokumentu
> pozostają aktualne.

> **Aktualizacja 2026-11-25 (kolejna runda):** kolejka dostała sekcje etapów i kolorowe paski,
> jedno pole szukania w nagłówku (na żywo) i klikalne kafelki — decyzja
> [0016](../decisions/0016-kolejka-i-wyszukiwanie.md), weryfikacja w
> [queue-usability-pass.md](queue-usability-pass.md). `ASSET_VERSION` doszedł do 16.
