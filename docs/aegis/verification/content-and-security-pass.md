# Treść, przepływy i bezpieczeństwo — weryfikacja przebudowy

| | |
|---|---|
| **Data** | 2026-11-25 |
| **Zakres** | wszystkie 32 trasy aplikacji (desktop 1440×900 i telefon 390×844), historia zmian, wydruki A4, nagłówki HTTP |
| **Decyzje** | [0015 — treść i przepływy](../decisions/0015-tresc-i-przeplywy.md), [0014 — warstwa wizualna](../decisions/0014-warstwa-wizualna.md) |
| **Środowisko** | Laravel 12, PHP 8.4, magazyn JSON (dane deweloperskie) i MySQL/MariaDB 10.4 (testy) |

## Co było nie tak (znalezione przez przeklikanie aplikacji)

| # | Ekran | Problem |
|---|---|---|
| 1 | wszystkie | Pasek `eyebrow` powtarzał tytuł strony („Kolejka napraw" + „Kolejka napraw", „Użytkownicy" + „Użytkownicy") |
| 2 | wszystkie | Stopka menu: „Magazyn danych: json" — informacja techniczna dla pracownika serwisu |
| 3 | wszystkie | Menu „Kolejka" jako grupa i jako pozycja; nagłówek pokazywał „Kolejka napraw" obok logo (nazwa strony + nazwa ekranu) |
| 4 | kolejka | „22 aktywnych zleceń · pokazano 1–22 z 22" — numeracja stron przy jednej stronie |
| 5 | kolejka | „Kliknij status, aby filtrować." + przycisk „Zastosuj filtr" — dwa kroki na jeden filtr |
| 6 | kolejka | 22 karty × wiersz „Hasło do urządzenia —", „plomba PLO-…", link „Otwórz" obok linku w tytule |
| 7 | kolejka | 22 karty zajmowały 6 971 px (7 ekranów) — brak możliwości szybkiego przeskanowania |
| 8 | karta zlecenia | Numer zlecenia w `eyebrow` i w metadanych; `Model` w tytule i w tabeli |
| 9 | karta zlecenia | Historia: „Status: X → Y" **i** „X → Y" w tym samym wpisie |
| 10 | karta zlecenia | Historia: 17 wierszy `— → —` przy przyjęciu zlecenia |
| 11 | karta zlecenia | Historia: `Wydał: — → 1` — surowe ID użytkownika zamiast nazwy |
| 12 | karta zlecenia | Historia: `Hasło do urządzenia: — → haslo-klienta` — **jawne hasło klienta w audycie** |
| 13 | karta zlecenia | Historia: „Dodano zdjęcie photos/32/05dec…jpg" — wewnętrzna ścieżka magazynu |
| 14 | formularz | „Zapisywane szyfrowane, widoczne dla pracowników." — zbędny tekst w aplikacji jednego serwisu |
| 15 | formularz | „Czas lokalny (Europe/Warsaw)." pod każdym polem daty |
| 16 | formularz | Data z sekundami: `26.11.2026 15:00:00` |
| 17 | formularz | „Uwagi wewnętrzne" vs „Uwagi" na karcie; „Zdjęcia (do 10 plików, max 10 MB każde)" + osobna podpowiedź |
| 18 | wyszukiwanie | Trzy zdania o tym samym (opis w nagłówku, „Wpisz co najmniej 3 znaki", opis w pustym stanie) |
| 19 | wyszukiwanie | „Znaleziono 1 zleceń" — błędna odmiana |
| 20 | użytkownicy | „ADMINISTRACJA · 1 KONT" — błędna odmiana |
| 21 | użytkownicy | „Konta pracowników, techników i administratorów serwisu." — język systemu dla sieci placówek |
| 22 | hasło | „KONTO · ADMIN" + „Konto: admin (Administrator)" + „Minimum 12 znaków." dwa razy |
| 23 | logowanie | Trzy warianty tej samej informacji o koncie zakładanym przez administratora |
| 24 | wydanie | Numer zlecenia w `eyebrow` i na żółtej karcie; „WYDANIE" jako etykieta na stronie wydania |
| 25 | wydanie | Pusta kolumna „Zdjęcia / Brak zdjęć." i „Czas lokalny…" |

## Jak to wygląda po przebudowie

| | przed | po |
|---|---|---|
| Wysokość kolejki z 22 zleceniami (1440 px) | 6 971 px | 3 154 px |
| Wysokość kolejki na telefonie (390 px) | 11 120 px | 5 147 px |
| Wiersze danych `—` na karcie zlecenia | 1–2 na kartę | 0 (puste pola nie są renderowane) |
| Wpisy w historii zlecenia SRV-2026-000022 | 3 wpisy, 25 wierszy | 3 wpisy, 12 wierszy (bez `— → —`) |
| Kroki do filtrowania kolejki | 2 (kafel + przycisk) | 1 (kafel) |
| Wystąpienia numeru zlecenia na karcie | 1–2 | 1 |
| Hasło urządzenia w historii | jawne | `••••` |
| Render kolejki, 200 zleceń (`bench:queue`) | p50 89,4 ms · p95 118,6 ms | p50 64,8 ms · p95 76,3 ms |

## Weryfikacja automatyczna (stan po zmianach)

```
php artisan test                        275 passed (976 assertions)   json + mysql
php artisan dusk                        15 passed (41 assertions)     w tym 6 audytów axe
vendor/bin/pint --test                  PASS (157 plików)
vendor/bin/phpstan analyse              108 plików, [OK] no errors (poziom 6)
php artisan bench:queue --services=200  p50 64,8 ms · p95 76,3 ms     (cel < 300 ms)
```

Testy dodane w tej rundzie:

- `tests/Unit/FieldLabelsTest.php` — odmiana (`0/1/3/12/22/25`), ukrywanie pól technicznych, maskowanie hasła, znacznik zamiast hasła w diffie, format telefonu.
- `tests/Feature/SecurityTest.php` — 404 zamiast 500 dla nieistniejącego zlecenia/zdjęcia/konta, nagłówki bezpieczeństwa na 404 i przekierowaniu, `url.intended` z obcego hosta ignorowany, hasło nie trafia do historii (jedno wystąpienie w HTML — przycisk „Pokaż").
- `tests/Browser/AcceptanceTest.php` — status czytany w formie renderowanej (wersaliki plakietki).

## Weryfikacja ręczna (przeglądarka, `php artisan serve`)

1. **Przepływ zlecenia**: przyjęcie → zdjęcie → „Rozpocznij naprawę" → „Oznacz do odbioru" → wydanie. Wszystkie kroki przechodzą, historia pokazuje 5 wpisów z autorem, bez duplikatów.
2. **Filtr kolejki**: klik w kafel „Oczekujące" przeładowuje listę (`?status[]=…`), licznik zmienia się z 22 na 18, pojawia się „Pokaż wszystkie". Bez JS działa `<noscript>` z przyciskiem.
3. **Formularze**: termin `26.11.2026 15:00` (bez sekund); rekord z sekundami (`16:42:46`) nadal wraca z formularza bez zmiany instantu — test D-44 przechodzi.
4. **Anulowanie**: panel `+ Anuluj zlecenie` domyślnie zamknięty, otwiera się bez JS.
5. **Wydruki**: potwierdzenie i protokół na A4 — jedna strona, puste wiersze (S/N, plomba, uwagi) nie są drukowane.
6. **Telefon 390×844**: brak poziomego przewijania na 10 trasach; karta zlecenia mieści się w ekranie, dolna nawigacja nie zasłania akcji.
7. **Konto pracownika** (utworzone przez formularz, rola „Pracownik"): nowe konto jest zmuszone do zmiany hasła (`RequirePasswordChange`), po zmianie nie widzi „Użytkownicy" w menu ani „Konta" na dolnym pasku (4 pozycje), a `GET /admin/users` i `/admin/users/1/edit` zwracają **403**.
8. **Strony błędów**: 403 „Nie masz dostępu do tej sekcji", 404 „Nie ma takiej strony ani zlecenia", 429 „Zbyt wiele prób", 500 „Coś poszło nie tak" — wszystkie po polsku, w brandzie, z linkiem do kolejki; 404 nie ujawnia stack trace.

## Bezpieczeństwo — ustalenia i poprawki

Audyt objął uwierzytelnianie, autoryzację, CSRF, upload, sekrety, nagłówki, instalator i logi. **Nie znaleziono podatności krytycznej ani wysokiej.** Poprawione:

| Problem | Poprawka | Dowód |
|---|---|---|
| Hasło urządzenia w jawnej postaci w `service_events` (omijało `SecretBox`) | `ServiceDiff` zapisuje `••••`, `FieldLabels` maskuje odczyt; dane deweloperskie wyczyszczone (31 wpisów) | test `never writes the device password into the audit trail` |
| Nieistniejące ID → 500 z pełnym stack trace (przy `APP_DEBUG=true`) | bindery tras zwracają `abort(404)`; `.env.example` ma `APP_DEBUG=false` | test `answers with 404 instead of a stack trace` |
| Nagłówki bezpieczeństwa tylko na odpowiedziach 200 | middleware globalny + `$exceptions->respond` | test `sends the security headers with error and redirect responses too` |
| `redirect()->intended()` ufa nagłówkowi `Host` (open redirect) | cel przekierowania musi mieć ten sam host co żądanie | test `ignores an intended url pointing at another host` |
| Dwa liczniki limitu logowania (middleware + kontroler) → realnie ~3 próby zamiast 5 | limit tylko w `throttle:login`, kontroler bez własnego licznika | `AuthTest` (5 prób, 6. → 429) |
| Strona 429 bez wyjaśnienia | `resources/views/errors/429.blade.php` po polsku | ręcznie |
| 403/404/500 w języku angielskim („This action is unauthorized.") | `layouts/plain` + polskie `errors/{403,404,500}.blade.php` w brandzie | ręcznie, konto pracownika |
| `deploy/htaccess-fallback.txt` nie blokował `_runtime/`, `old-app/`, `Piotrekv3/` | rozszerzona lista `RedirectMatch 404` | przegląd pliku |

Świadomie **nie** zmieniano: sesji `Secure` (wymaga HTTPS — wpis w `.env.example`), tokena instalatora w query stringu (hosting bez SSH; po instalacji token jest pusty), limitu uploadu 10 × 10 MB (osobna decyzja o koszcie), polityki „każdy pracownik widzi każde zlecenie" (decyzja 0009).

## Ograniczenia tej weryfikacji

- Testy Dusk działają w Chromium w oknie 390×844, nie na fizycznym telefonie.
- Wydruk sprawdzony w podglądzie A4 Chromium, nie na papierze (pasek grafitu wymaga włączonych „tła grafiki").
- `DATA_STORE=mysql` sprawdzony testami (dataset `mysql` na MariaDB 10.4), nie na hostingu docelowym.
- Próby logowania i blokada 429 sprawdzone testem i sondą HTTP, bez testu obciążeniowego.
