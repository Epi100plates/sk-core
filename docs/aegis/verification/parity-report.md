# Raport parzystości i pokrycia defektów

| | |
|---|---|
| **Data** | 2026-11-02 |
| **Zakres** | F-01…F-57 (funkcje) i D-01…D-58 (defekty) z planu |
| **Wynik bramek** | `php artisan test`: **254 testy, 915 asercji, zielone** w trybie `json` i `mysql`; `php artisan dusk`: **9 scenariuszy, zielone** (Chromium + ChromeDriver); Pint czysty (154 pliki); PHPStan poziom 6 bez błędów |
| **Środowisko** | PHP 8.4.27, MariaDB 10.4.32 (`serwis_test`), Chromium (390×844 i 1366×768) |

Legenda: **T** = test automatyczny (Pest), **E** = weryfikacja w przeglądarce (Chromium),
**M** = test na fiksturze migracji, **—** = świadomie nie dotyczy v1.

## 1. Funkcje (F)

| F | Zakres | Dowód |
|---|---|---|
| F-01 | logowanie, jeden generyczny komunikat | T: `AuthTest` (login/ok, złe hasło, brak konta, throttle 429), E |
| F-02 | POST-owe wylogowanie + komunikat | T: `AuthTest` (logout, 405 dla GET) |
| F-03 | ochrona wszystkich tras | T: `AuthTest` (6 tras → redirect), `SecurityTest` (trasy publiczne) |
| F-04 | rotacja identyfikatora sesji | T: `AuthTest` |
| F-05 | CSRF na każdej mutacji | T: `SecurityTest` (token w 4 formularzach, 419 bez tokenu) |
| F-06 | panel kont, 3 role, macierz | T: `AdminUsersTest` (CRUD, 403 dla pracownika/technika, blokada konta, hasła hashowane) + `RoleTest` |
| F-07 | telefon wymagany, 4 formaty → jeden | T: `PhoneTest`, `AcceptServiceTest` (walidacja), `SearchTest` |
| F-08 | model wymagany ≤100 | T: `AcceptServiceTest` |
| F-09 | usterka wymagana, zachowane nowe linie | T: `AcceptServiceTest` |
| F-10 | hasło urządzenia szyfrowane | T: `AcceptServiceTest`, `JsonStoreTest` (brak plaintextu w pliku), `PhotosTest` (brak w cache) |
| F-11 | S/N opcjonalny | T: `AcceptServiceTest`, `UpdateServiceTest` |
| F-12 | uwagi opcjonalne | T: `UpdateServiceTest` |
| F-13 | termin wymagany, domyślnie jutro 15:00 lokalnie | T: `AcceptServiceTest` (domyślka + brak przesunięcia UTC), E (pole `13:00` → `15:00` naprawione) |
| F-14 | cena ≥ 0, `12,50` = `12.50` | T: `MoneyTest`, `AcceptServiceTest` (`199,99 → 199.99`) |
| F-15 | koszt części obecny także na mobile | T: `AcceptServiceTest`, `AdminUsersTest` (widoczny dla pracownika), E |
| F-16 | express na górze kolejki | T: `QueueTest` (kolejność), `AcceptServiceTest` (flaga) |
| F-17 | checkbox wydruku + flaga na karcie | T: `AcceptServiceTest`, `PrintTest` (znacznik „potwierdzenie wydane”), E |
| F-18 | 1–10 zdjęć, miniatura, prywatny dysk | T: `PhotosTest` (zapis, miniatura, odrzucenie 11 plików, odrzucenie nie-obrazu), E (upload w UI) |
| F-19 | status `pending`, autor i data przyjęcia | T: `AcceptServiceTest` |
| F-20 | zdarzenie `accepted` z migawką | T: `AcceptServiceTest` (zmiany zawierają pola) |
| F-21 | kolejka: aktywne + 10 ostatnio wydanych na końcu | T: `QueueTest` (kolejność, brak wydanych z akcją, paginacja), E |
| F-22 | sortowanie: express → status → termin | T: `QueueTest` (dokładna kolejność), `ServiceStatusTest` (priorytety) |
| F-23 | karta: model, status, telefon, termin, cena, hasło ukryte | T: `QueueTest`, E (przycisk „Pokaż” przy haśle) |
| F-24 | „następny etap” z walidacją przejść | T: `StatusAndReleaseTest` (2× advance, 422 przy braku etapu, JSON) |
| F-25 | przycisk „Wydaj” dla `ready_for_pickup` | T: `AdminUsersTest` (widoczny dla pracownika), E |
| F-26 | archiwum wydanych: filtr dat, szukanie, grupowanie | T: `CancelServiceTest` (archiwum), E (strona `/released`) |
| F-27…F-29 | jedno wyszukiwanie (telefon/plomba/model/SN/usterka/numer) | T: `SearchTest` (5 wariantów, 3 znaki, wydane, escaping), E |
| F-30/F-31 | publiczny lookup | — (D4); T: `SecurityTest` (`/lookup`, `/reklamacja*` → 404) |
| F-32 | filtry statusu w URL | T: `QueueTest` |
| F-33 | zmiana statusu jako zdarzenie | T: `StatusAndReleaseTest`, `UpdateServiceTest` |
| F-34 | edycja wszystkich pól, brak dryfu przy zapisie bez zmian | T: `UpdateServiceTest` (D-44, D-47, D-48), E („Brak zmian — nic nie zostało zapisane.”) |
| F-35 | status z formularza = `status_changed`, brak `released` | T: `UpdateServiceTest` |
| F-36 | normalizacja plomby i duplikat | T: `UpdateServiceTest` |
| F-37 | galeria + usuwanie z audytem | T: `PhotosTest` (usuwanie, plik znika, zdarzenie, obca fotografia → 404), E (lightbox) |
| F-38 | historia: autor, czas lokalny, diff z polskimi nazwami, mobile | T: `FieldLabelsTest`, `PrintTest`, E (panel na 390 px) |
| F-39 | formularz wydania z datą i dwustopniowym potwierdzeniem | T: `StatusAndReleaseTest`, E (wydanie z telefonu) |
| F-40 | `released_at` z formularza, `released_by` | T: `StatusAndReleaseTest` (`12:30` lokalnie → `11:30` UTC) |
| F-41 | zdarzenie `released` z from/to | T: `StatusAndReleaseTest` |
| F-42 | wydanie z telefonu działa | T: `StatusAndReleaseTest` (User-Agent iPhone), E (390×844) |
| F-43 | manifest + komplet ikon + skróty | T: `SecurityTest` (manifest, ikony 200), `CheckIcons` w CI, E (service worker aktywny) |
| F-44 | offline: powłoka, blokada zapisów, zero danych klientów | T: `SecurityTest` (strona offline), E (offline → strona `/offline`, cache tylko CSS/JS/ikony) |
| F-45/F-46 | push i sync | — (poza v1, decyzja D5) |
| F-47 | lightbox: ESC, strzałki, focus trap, swipe | E (dialog `role="dialog"`, powrót focusu, brak błędów konsoli) |
| F-48 | jeden UI: sidebar ≥1024 px, dolna nawigacja <1024 px | E (1366×768 i 390×844, brak poziomego scrolla) |
| F-49 | brak wykrywania User-Agenta | T: `SecurityTest` (lista tras), przegląd kodu |
| F-50 | audyt: jeden wpis na zmianę | T: `AcceptServiceTest`, `UpdateServiceTest`, `PhotosTest`, `CancelServiceTest` |
| F-51 | logi dzienne, bez PII | T: `LogRedactionTest` (procesor na kanałach `single` i `daily`, maskowanie telefonu i hasła) |
| F-52 | instalator CLI + web z tokenem | T: `CommandsTest` (idempotencja), `SecurityTest` (trasa bez tokenu → 404) |
| F-53 | dane demo tylko poza produkcją | T: `CommandsTest` (30 zleceń, wyjątek w produkcji) |
| F-54 | `photos:verify [--fix]` | T: `CommandsTest`, kod komendy |
| F-55 | druk potwierdzenia przyjęcia i protokołu wydania | T: `PrintTest` (treść, znacznik, sesja), E (zrzuty A4) |
| F-56 | anulowanie z powodem | T: `CancelServiceTest` (4 testy) |
| F-57 | JSON/MySQL, `store:check`, `store:json-to-mysql` | T: `JsonStoreTest`, `CommandsTest` (migracja idempotentna), `store:check` w CI |

## 2. Defekty (D)

| D | Domknięcie | Dowód |
|---|---|---|
| D-01 | bcrypt, brak plaintextu | T: `AdminUsersTest` (`Hash::check`), `CommandsTest` |
| D-02 | wszystkie trasy za `auth` | T: `AuthTest`, `SecurityTest` |
| D-03 | brak surowego SQL; zapytania przez repozytoria | przegląd kodu + `SearchTest` (parametryzacja LIKE) |
| D-04 | Blade `{{ }}`, brak `innerHTML`, CSP bez `unsafe-inline` | T: `AuthTest` (`?error=`), `SearchTest` (escaping), `SecurityTest` (CSP) |
| D-05 | CSRF globalnie + autoryzacja obiektowa zdjęć | T: `SecurityTest`, `PhotosTest` |
| D-06 | brak publicznych narzędzi (setup/load_test_data/diagnose/verify-pwa) | T: `SecurityTest` (404) |
| D-07 | throttle 5/15 min, jeden komunikat, POST logout | T: `AuthTest` |
| D-08 | logout czyści sesję, token i cache PWA | T: `AuthTest`; cache: `public/js/pwa.js` + zdarzenie `serwis:logout` |
| D-09 | hasło urządzenia szyfrowane, brak w cache | T: `JsonStoreTest` (brak plaintextu w pliku), `PhotosTest` (cache), E (Cache Storage) |
| D-10 | jedno wydanie dla obu urządzeń | T: `StatusAndReleaseTest` (iPhone UA ok), E |
| D-11 | data wydania z formularza | T: `StatusAndReleaseTest` |
| D-12 | komunikaty flash po każdej akcji | T: `QueueTest`, `UpdateServiceTest`, E |
| D-13 | checkboxy z modelu | T: `UpdateServiceTest` (D-48), E |
| D-14/D-47 | brak zerowania kosztu | T: `UpdateServiceTest` |
| D-15 | format `datetime-local` z sekundami | T: `UpdateServiceTest` (round-trip), E |
| D-16 | jedna ścieżka zdjęć | T: `PhotosTest` (także przy wydaniu przez formularz wydania? — patrz §3 uwaga 1) |
| D-17 | transakcyjność akcji | T: `JsonStoreTest` (rollback), `AcceptServiceTest` |
| D-18 | maszyna stanów | T: `ServiceStatusTest`, `UpdateServiceTest`, `StatusAndReleaseTest` |
| D-19 | jedna implementacja sortowania | T: `QueueTest` (identyczna kolejność w obu magazynach) |
| D-20 | normalizacja i walidacja telefonu | T: `PhoneTest`, `SearchTest` |
| D-21 | jeden mechanizm wyszukiwania | T: `SearchTest` |
| D-22 | plomba: trim + upper, ostrzeżenie | T: `UpdateServiceTest` |
| D-23 | kwoty w groszach, format `pl_PL` | T: `MoneyTest`, `AcceptServiceTest` |
| D-24/D-44/D-45 | brak dryfu terminu | T: `UpdateServiceTest` (zapis bez zmian), `AcceptServiceTest` (domyślka i strefa), E |
| D-25 | zarządzanie kontami w panelu | T: `AdminUsersTest` |
| D-26 | flaga potwierdzenia ma znaczenie | T: `PrintTest`, E |
| D-27 | precache istniejących assetów | E (Cache Storage: 12 pozycji, w tym `offline`) |
| D-28 | cache bez PII | E (Cache Storage po zalogowaniu: tylko CSS/JS/ikony/offline) |
| D-29 | komplet ikon i skrótów | T: `SecurityTest` + `icons:check` w CI |
| D-30 | brak `share_target` | manifest bez tego klucza (T: `SecurityTest`) |
| D-31 | brak auto-reloadu po aktualizacji | `public/js/pwa.js` (brak `location.reload`) |
| D-32 | jawnie: offline = tylko odczyt | ADR 0005, E (offline → `/offline`, formularze zablokowane) |
| D-33 | UTF-8 w komunikatach i testach | przegląd + E |
| D-34 | dostępny lightbox | E (`role=dialog`, focus trap, ESC, powrót focusu) |
| D-35 | jeden `app.css` + Bootstrap | przegląd widoków: brak bloków `<style>` |
| D-36 | jedna historia migracji | `database/migrations/*` (4 pliki), `migrate:fresh` w testach MySQL |
| D-37 | tylko `.env` + `config/*` | brak `database.php` w repo |
| D-38 | audyt w magazynie, brak duplikatów, brak PII w logach | T: `AcceptServiceTest` (1 zdarzenie), `LogRedactionTest` (maskowanie telefonu i hasła) |
| D-39 | testy i CI | `.github/workflows/ci.yml` |
| D-40 | paginacja serwerowa | T: `QueueTest` (paginacja 50) |
| D-41 | jeden UI, jedna akcja na operację | przegląd tras (`routes/web.php`) |
| D-42 | brak martwego kodu | brak nieużywanych klas/metod (PHPStan + przegląd; usunięty `HasFactory`, brak `getStatistics` itd.) |
| D-43 | anulowanie + wydruk | T: `CancelServiceTest`, `PrintTest` |
| D-46 | wydane nie stoją nad naprawami, brak akcji | T: `QueueTest`, `StatusAndReleaseTest` |
| D-48 | flagi nie zmieniają się same | T: `UpdateServiceTest` |
| D-49 | brak stron-sierot | T: `SecurityTest` (404) |
| D-50 | sesja przed outputem | T: `AuthTest` (każda trasa renderuje się zalogowanemu), brak błędów 500 w E |
| D-51 | `199,99 → 199.99` | T: `MoneyTest`, `AcceptServiceTest` |
| D-52 | zdjęcia tylko przez kontroler | T: `PhotosTest` (302 dla anonima, `Cache-Control: private`), `SecurityTest` (`/storage/...` → 404) |
| D-53 | wpis `photo_deleted` po usunięciu | T: `PhotosTest` |
| D-54 | jeden wpis audytu na operację | T: `AcceptServiceTest`, `UpdateServiceTest` |
| D-55 | komunikat po wylogowaniu | T: `AuthTest` |
| D-56 | data wydania w formularzu, walidacja przyszłości | T: `StatusAndReleaseTest` |
| D-57 | polskie etykiety i format lokalny w historii | T: `FieldLabelsTest`, E |
| D-58 | brak `verify-pwa.php`, walidacja ikon w CI | T: `SecurityTest`, `icons:check` |

## 3. Uwagi i świadome odstępstwa

1. **Zdjęcia przy wydaniu**: plan (D-16) chce jednej ścieżki zdjęć dla przyjęcia, edycji i wydania.
   Zrealizowane: osobny endpoint `POST /services/{id}/photos` (dostępny z karty zlecenia i z formularza
   edycji) oraz upload przy przyjęciu. Formularz wydania nie ma już atrapy uploadu — zdjęcia dodaje się
   z karty zlecenia. To ta sama, jedna implementacja (`AttachServicePhotos`), ale nie ten sam formularz.
2. **Livewire 3** (D1) nie jest użyty — patrz ADR 0004. Reszta stacku bez zmian.
3. **F-56 „anulowanie tylko przez technika/admina”** jest sprzeczne z D9/5.3 (pracownik ma pełny zakres).
   Realizacja idzie za D9: anulować może każda z trzech ról. Sprzeczność w planie, nie w kodzie.
4. **Ponowny wydruk** nie zmienia `confirmation_issued_at` (data oznacza moment wydania potwierdzenia
   klientowi). Plan dopuszczał „ustawia/odświeża” — wybrano semantykę „ustawia przy pierwszym wydruku”.
5. **Wersja `reference` w MySQL**: licznik oparty o maksimum numeru w roku; unikalny indeks chroni
   przed duplikatem, ale dwa jednoczesne przyjęcia mogą zakończyć się błędem zapisu (JSON: licznik pod `flock`).

## 4. Czego nie dało się zweryfikować w tej iteracji

- **Laravel Dusk** — 9 scenariuszy zielonych (`php artisan dusk`, ADR 0011). ChromeDriver pobrany
  ręcznie jako binarka win64; `php artisan dusk:chrome-driver` nie działa na tej maszynie (brak CA w PHP CLI).
- **axe-core** — plik nie jest dołączony do repo; audyt a11y ograniczony do przeglądu semantyki
  (landmarki, etykiety, `aria-*`, focus trap w dialogu) i braku poziomego scrolla (test Dusk).
- **Hosting właściciela** (PHP 8.2, `.htaccess`, cron, limit uploadu) — wymaga testu na staging (T0.2).
- **Import danych produkcyjnych** — brak zrzutu; importer przetestowany na fiksturze
  (`tests/Fixtures/legacy-dump.sql`).
