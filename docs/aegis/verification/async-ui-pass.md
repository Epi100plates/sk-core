# Aktualizacje w miejscu: filtr, paginacja, „Dalej”, konta i zdjęcia bez przeładowań

| | |
|---|---|
| **Data** | 2026-11-25 |
| **Zakres** | kolejka (filtr etapu, „Pokaż wszystkie”, paginacja, „Dalej”), archiwum (zakres dat, „Wyczyść zakres”, paginacja), konta (blokada/odblokowanie, paginacja), karta zlecenia (dodanie i usunięcie zdjęcia), wyszukiwanie w nagłówku |
| **Decyzja** | [0017 — aktualizacje w miejscu](../decisions/0017-aktualizacje-w-miejscu.md) |
| **Źródło** | zlecenie właściciela (2026-11-25): „Sprawdź, co w kodzie można napisać optymalniej… aby strona po kliknięciu przycisków się nie przeładowywała za każdym razem, ale działało to asynchronicznie, znajdź też inne elementy strony, które można napisać lepiej i żeby to działało” |

## Co się zmieniło

**Jedna warstwa w `public/js/app.js`** (zamiast osobnej ścieżki wyszukiwania): delegowane
obsługiwacze `data-async-form`, `data-async-link`, `data-async-on-change` pobierają **prawdziwy
adres strony** i podmieniają każdy `[data-async-region]`, który występuje również w odpowiedzi.
Adres jest aktualizowany przez `history.replaceState`, regiony dostają `aria-busy` w czasie
żądania, `#async-status` (`role="status"`) ogłasza wynik, fokus wraca na to samo pole (radio
etapu po nazwie i wartości) albo do odświeżonego regionu. Zapisy nie są przerywane w locie
i są serializowane; odczyty mogą się nawzajem anulować.

**Regiony:** `flash` (layout), `queue-filter`, `queue-results`, `released-filter`,
`released-results`, `admin-users`, `service-photos`.

**Usunięte:** trasa `GET /queue/results`, akcja `QueueController::results()`, nagłówek
`X-Queue-Results`, atrybuty `data-results-url` / `data-status`, `data-auto-submit` oraz
`data-queue-filter-term` / `data-queue-filter-reset` z ręczną synchronizacją. `Pagination`
wróciła do prostego `fullUrlWithQuery` + `data-async-link` / `data-async-scroll`.
`EmptyState` i linki „Wyczyść szukanie” / „Pokaż wszystkie” / „Wyczyść zakres” działają
w miejscu, a `lightbox.js` czyta grupę zdjęć z żywego DOM (miniatury po podmianie regionu
działają jak pierwsze).

**Przy okazji, mniejsze rzeczy:**

- komunikat flash (sukces/ostrzeżenie) znika sam po 6 s — przy aktualizacji w miejscu stary
  baner wisiałby nad nowym wynikiem; błędy nigdy nie znikają same;
- pole szukania dostało `inputmode="search"`, `enterkeyhint="search"`, `autocapitalize="off"`,
  `spellcheck="false"` — na telefonie klawiatura pokazuje „szukaj”, a nie „idź”;
- `bench:queue` robi jeden render rozgrzewający poza pomiarem; wcześniej pierwszy pomiar
  zawierał kompilację szablonów Blade i przy `--requests=2` p95 skakało powyżej progu 300 ms
  (jedna z bramek była przez to niestabilna, nie dlatego, że kolejka zwolniła);
- „Wyczyść zakres” w archiwum czyści też pola dat — pasek filtrów jest regionem, więc stan
  pól pochodzi z serwera, a nie z ręcznej synchronizacji w JS.

## Dowody

```
php artisan test                        309 passed (1249 assertions)   json + mysql
php artisan dusk                        17 passed (67 assertions)      w tym 6 audytów axe
vendor/bin/pint --test                  PASS (159 plików)
vendor/bin/phpstan analyse              [OK] no errors (poziom 6)
php artisan bench:queue                 p95 138,2 ms / 200 zleceń (cel 300 ms)
```

Nowe i zmienione testy:

- `tests/Feature/QueueTest.php` — regiony `queue-filter` / `queue-results`, formularz
  `data-async-form` + `data-async-on-change`, akcja kafelka `data-async-form`, `#async-status`,
  region `flash`, paginacja z `data-async-link data-async-scroll`, brak trasy `/queue/results`
  (404).
- `tests/Feature/ReleasedArchiveTest.php` (nowy) — zakres dat, region paska filtrów z polami
  i linkiem „Wyczyść zakres”, pusta lista po wyczyszczeniu, paginacja, brak `data-auto-submit`,
  działanie jako zwykły formularz GET bez JS.
- `tests/Feature/AdminUsersTest.php` — jeden region `admin-users` z tabelą i paginacją,
  `data-async-form` na formularzach blokady, nazwy dostępne przycisków z loginem, stan
  „zablokowane” renderowany przez serwer po przełączeniu.
- `tests/Feature/PhotosTest.php` — region `service-photos`, `data-async-form` na dodawaniu
  i usuwaniu (z `_method=DELETE`), brak `data-guard-submit`.
- `tests/Feature/AuthTest.php` — usunięta trasa `/queue/results` z listy tras chronionych.
- `tests/Browser/AsyncQueueTest.php` (nowy) — w Chrome: po kliknięciu etapu, „Pokaż wszystkie”,
  „Następna” i „Dalej” dokument **nie jest przeładowywany** (globalna zmienna `window.__serwisProbe`
  przeżywa akcję), adres pokazuje `?status=`, `?q=`, `?page=`, a flash pokazuje potwierdzenie.

## Sprawdzone ręcznie w Chrome (serwer `127.0.0.1:8123`, dane deweloperskie)

Metoda: przed każdą akcją `window.__probe = 'alive'`; przeładowanie strony wyczyściłoby tę
zmienną, więc jej obecność po akcji jest dowodem aktualizacji w miejscu.

| Akcja | Wynik |
|---|---|
| kafel „W trakcie naprawy” | `?status=in_progress`, 4 karty (z 22), fokus nadal na radiu `in_progress`, link „Pokaż wszystkie”, `__probe` żyje |
| „Pokaż wszystkie” | `/?`, 22 karty w 3 sekcjach, `__probe` żyje |
| wpisanie `macbook` | `?q=macbook`, 3 karty, podsumowanie „3 zlecenia dla „macbook””, `#async-status` z tym samym tekstem, fokus w polu szukania |
| kafel „Oczekujące” przy frazie | `?q=macbook&status=pending`, 2 karty, ukryte `q` i adres „Pokaż wszystkie” nadal niosą frazę |
| „Zablokuj konto admin” (własne konto) | brak przeładowania, błąd „Nie możesz zablokować własnego konta.” w regionie `flash`, przycisk znów aktywny, `aria-busy="false"` |
| dodanie zdjęcia (plik 96×96 PNG) | „Dodano zdjęcia: 1.”, miniatura w miejsce „Brak zdjęć.”, pole pliku wyczyszczone, `__probe` żyje |
| usunięcie tego zdjęcia | „Zdjęcie zostało usunięte.”, karta wraca do „Brak zdjęć.”, `__probe` żyje |
| archiwum: `from=2026-11-20` | `?from=2026-11-20`, puste archiwum z podpowiedzią, link „Wyczyść zakres” |
| archiwum: „Wyczyść zakres” | `?`, 9 zleceń, **pole daty wyczyszczone**, link zniknął |
| telefon 390×844 | `scrollWidth 375 = clientWidth 375` (brak poziomego przewijania), 22 karty |
| konsola | 0 błędów po odświeżeniu kolejki (3 wcześniejsze błędy 503 pochodziły z przebiegu Dusk, który podmienia `.env` na czas testu) |

## Czego świadomie nie zmieniono

1. **Nawigacja między ekranami** (kafel → edycja, menu, wydruki, logowanie) nadal ładuje nowy
   dokument. Router po stronie klienta musiałby odtwarzać obsługę błędów walidacji, wydruki
   w osobnym layoucie, wysyłkę plików i historię przeglądarki — ryzyko większe niż zysk.
2. **Nagłówki cache zdjęć** (`private, max-age=0, must-revalidate`) zostają. Krótszy cache
   oszczędziłby kilka żądań na wejściu w kartę, ale pozwoliłby przeglądarce pokazać zdjęcie
   z pamięci podręcznej po wylogowaniu, bez pytania serwera o sesję — to pogorszyłoby ochronę
   danych klienta (decyzja D5, defekt D-28).
3. **Liczniki w pasku etapów** nadal pokazują stan całego warsztatu, nie liczbę trafień dla
   wpisanej frazy (liczba trafień jest w podsumowaniu nad listą) — zachowanie z decyzji 0016.
4. **Pamięć podręczna JSON** — kolejka czyta plik `services.json` trzy razy na żądanie
   (lista, archiwum, liczniki). Przy 31 zleceniach to ułamki milisekundy; memoizacja w
   `JsonStore` byłaby optymalizacją bez odczuwalnego zysku i z ryzykiem nieświeżych danych
   po zapisie. Zostawione bez zmian.
5. **`prefetch` linków** — odrzucony: Laravel wysyła `Cache-Control: no-cache, private`, więc
   wstępne pobranie i tak wymaga potwierdzenia serwera, a realny zysk daje tylko warstwa
   regionów.

## Czego nie sprawdzono

1. **Fizyczny telefon i czytnik ekranu** — układ sprawdzony w Chromium 390×844, ogłoszenia
   `aria-live` nie były testowane z NVDA/VoiceOver. To nadal pozycja UAT po stronie właściciela.
2. **Firefox/Safari** — używane API (`fetch`, `AbortController`, `URLSearchParams`, `DOMParser`,
   `history.replaceState`, `scrollIntoView({block})`) są wspierane, ale przeglądarek nie
   uruchamiano; testy przeglądarkowe to Chrome przez Dusk.
3. **Historia przeglądarki** — aktualizacje robią `replaceState`, więc „wstecz” wraca do
   poprzedniego ekranu, nie do poprzedniej strony paginacji (świadome, opisane w ADR 0017).
4. **Bardzo wolna sieć** — opóźnienie i anulowanie żądań sprawdzone lokalnie; zachowanie przy
   timeoutach operatora nie było symulowane (po błędzie pojawia się komunikat w `#async-status`,
   a fokus i treść zostają bez zmian).

## Aktualizacja 2026-11-25 (kafelek etapu jako przełącznik)

Zgłoszenie właściciela: „Odklikiwanie na górnej belce żeby wyłącz filtrowanie po tej kategorii
ma też działać, po kliknięciu w dany kafelek filtr ma się włączać, a po odkliknięciu ma się
wyłączać”. Radio nie odznacza się od drugiego kliknięcia, więc dotąd jedynym wyjściem był link
**Pokaż wszystkie**.

**Co doszło:** `data-filter-toggle` na radiach etapów i 40 linii w `app.js`
(`clearFilterByChip`). Kafelek, którego wartość jest w adresie (a nie „zaznaczony”, bo przeglądarka
zaznacza radio **przed** wysłaniem zdarzenia `click`), zdejmuje `status` i `page` z adresu
i odświeża oba regiony tą samą warstwą — fraza `q` zostaje. Spacja na wybranym radiu nie wysyła
w Chrome zdarzenia `click`, więc klawiatura ma tę samą czynność na `keydown` (z `preventDefault`,
żeby strona nie skoczyła). Bez JavaScriptu radia nadal wysyłają formularz, a link czyści wybór.

| Akcja (Chrome, `127.0.0.1:8123`, dane deweloperskie) | Wynik |
|---|---|
| kafel „W trakcie naprawy” → ten sam kafel | `?status=in_progress` (4 karty) → `/?`, 22 karty, żaden kafelek nie zaznaczony, link „Pokaż wszystkie” zniknął, `__probe` żyje, fokus na radiu `in_progress`, `#async-status` = „22 zlecenia” |
| to samo po wpisaniu `dell` | `?q=dell&status=pending` (1 karta) → `?q=dell` (6 kart), ukryte `q` nadal `dell`, `__probe` żyje |
| kafel „Czeka na części” (0 trafień) → ten sam kafel | `?status=awaiting_parts` (0 kart) → `/?`, 22 karty |
| Spacja na wybranym radiu (klawiatura) | filtr wyłączony, adres `/?`, 22 karty, `scrollY` nadal 0, fokus na tym samym radiu |
| sieć | jedno żądanie `GET` na kliknięcie (6 kliknięć = 6 żądań), konsola 0 błędów i 0 ostrzeżeń |

Bramki: `php artisan test` **315 passed (1295 assertions)** (json + mysql), `php artisan dusk`
**17 passed (70 assertions)** (w tym 6 audytów axe), Pint **PASS (159 plików)**, PHPStan poziom 6
**bez błędów**. Regresję pilnują: `QueueTest` (liczba kafelków z `data-filter-toggle` równa liczbie
etapów) i `AsyncQueueTest` (klik w wybrany kafel zdejmuje `status` z adresu bez przeładowania).

