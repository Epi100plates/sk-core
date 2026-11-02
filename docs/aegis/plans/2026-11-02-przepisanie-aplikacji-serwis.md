# Plan przepisania aplikacji „Studio-Komp Serwis” od nowa

| | |
|---|---|
| **Status** | **FINALNY — decyzje D1–D13 zaakceptowane przez właściciela 2026-11-02** (kod jeszcze nie ruszony) |
| **Data** | 2026-11-02 (wersja 4: magazyn danych JSON/MySQL, uprawnienia pracownika) |
| **Autor** | analiza AI na zlecenie właściciela aplikacji |
| **Źródło** | `old-app/` — 77 plików (PHP/JS/CSS/SQL/MD), ~10 400 niepustych linii kodu (~23 800 linii fizycznych, w tym zduplikowane puste wiersze) |
| **Deliverable** | ten dokument: inwentaryzacja funkcji + architektura docelowa + plan zadań + migracja + kryteria odbioru |
| **Dotyczy** | przepisania całości (big-bang rewrite) z zachowaniem parzystości funkcji |
| **Stack docelowy** | **PHP + HTML/CSS + framework PHP** — bez Node.js i bez Pythona (D1, sekcja 5.1) |
| **Magazyn danych** | **dwa tryby przełączane w konfiguracji: plik JSON (start bez bazy) i MySQL (własna baza)** — D13, sekcja 5.7 |
| **Wdrożenie** | hosting współdzielony właściciela, **podkatalog `epii.pl/serwis`**, deploy przez FTP/panel, bez SSH i bez Composera na serwerze (sekcja 11) |
| **Weryfikacja** | kod **oraz uruchomiona aplikacja**: PHP 8.2.12 + MariaDB 10.4.32, przeglądarka Chromium (390×844 i 1366×768), 30+ przejść UI — wyniki w sekcji 3.6 |

---

## 0. Streszczenie (TL;DR)

Stara aplikacja to jednoosobowy (ale produkcyjny) system warsztatu komputerowego **Studio-Komp**: przyjęcie laptopa do serwisu, kolejka napraw, zdjęcia urządzenia, zmiana statusu, wydanie klientowi, archiwum wydanych, historia zmian, wyszukiwanie po telefonie/plombie oraz wersja PWA na telefon.

**Rdzeń funkcjonalny jest wartościowy i kompletny** — obejmuje cały cykl życia zlecenia. Problemem nie jest brak funkcji, tylko ich **wielokrotna, rozjeżdżająca się implementacja**: 2 dashboardy, 4 ścieżki edycji, 4 ścieżki wydania, 2 systemy konfiguracji bazy, 3 różne definicje schematu SQL, brak testów i brak egzekwowania reguł biznesowych (dowolny status, ciche zerowanie kosztów, wydanie z telefonu zawsze z błędem).

**Rekomendacja:** przepisać jako **monolit Laravel 12 (PHP 8.3+) + Livewire 3 + Bootstrap 5 (plik CSS/JS z repo, bez budowania)** — wyłącznie PHP, HTML i CSS, bez Node.js i Pythona. Jeden model domenowy, jeden UI responsywny (bez osobnych wersji desktop/mobile), szyfrowanie hasła urządzenia, pełny audyt i testy w PHP (Pest + Laravel Dusk). **Magazyn danych jest wymienny: plik JSON (start bez bazy) albo MySQL — przełączany jednym wpisem w konfiguracji.** Szacunek: **11 faz (F0–F10), ~42 dni roboczych** jednego developera, w tym ~4 dni na warstwę wymiennego magazynu i testy w obu trybach.

**Aplikacja została uruchomiona i przeklikana** (PHP 8.2.12 + MariaDB, Chromium). To potwierdziło 18 defektów z analizy kodu i ujawniło **15 nowych**, w tym dwa krytyczne: **każdy zapis formularza przesuwa obiecany klientowi termin o 1–2 h** oraz **wydanie z telefonu, dwie strony „dodaj serwis” i `wydawanie.php` są martwe** (błąd walidacji / HTTP 500 / pusty ekran). Szczegóły i dowody: sekcja 3.6, rejestr D-44…D-58.

**Decyzje właściciela (2026-11-02) — wiążące dla realizacji:** klient **nie** sprawdza statusu sam (moduł publiczny wypada z v1); hosting współdzielony, aplikacja w **podkatalogu `/serwis`**; przy przyjęciu **checkbox „wydrukuj potwierdzenie przyjęcia”** i informacja „potwierdzenie wydane” na karcie zlecenia; **trzy role** — **pracownik wydaje laptopy i zmienia koszty**, technik dostanie dodatkowe prawa w kolejnych funkcjach, admin zarządza kontami; statusy 5 + „anulowane”; dane klienta = **tylko telefon** (bez kartoteki imion); hasło do laptopa **zapisywane** (wersja testowa, bez rygoru RODO); retencja **bezterminowa**; **bazy jeszcze nie ma** — start na pliku JSON, MySQL podłączany później; dane produkcyjne: właściciel dostarczy zrzut, gdy będzie.

---

## 1. Cel, zakres i kryteria akceptacji

### 1.1 Cel biznesowy

Jedno narzędzie, w którym pracownik warsztatu w ≤ 3 dotknięciach na telefonie: przyjmuje laptopa (zdjęcia, usterka, termin, cena), przesuwa go przez etapy naprawy, wydaje klientowi i ma pełną historię — a przy ladzie potrafi w 5 sekund odpowiedzieć „czy ten telefon/plomba jest u nas i na jakim etapie”. System działa też na komputerze w warsztacie i instaluje się jako aplikacja (PWA).

### 1.2 Zakres

**W zakresie (parzystość 1:1 z `old-app/`):** logowanie, przyjęcie zlecenia (wszystkie pola + zdjęcia), kolejka z sortowaniem i wyszukiwaniem, zmiana statusu, edycja, wydanie, archiwum wydanych, historia zmian, wyszukiwanie po telefonie i plombie, galeria z powiększaniem, PWA (instalacja + offline shell), log audytu, narzędzia administracyjne (inicjalizacja, import danych, diagnostyka zdjęć).

**W zakresie (nowe, wymuszone przez naprawę błędów):** hashowanie haseł, egzekwowanie dozwolonych przejść statusów, walidacja i normalizacja telefonu/plomby, transakcyjność operacji, poprawne wydanie z telefonu, ochrona zdjęć, obsługa błędów uploadu, zarządzanie użytkownikami i **trzy role**.

**W zakresie (nowe, na życzenie właściciela):** checkbox **„wydrukuj potwierdzenie przyjęcia”** + widok do druku (potwierdzenie przyjęcia i protokół wydania), **flaga „potwierdzenie wydane”** widoczna na karcie zlecenia, **status „anulowane”**, trzy role z macierzą uprawnień (**pracownik wydaje laptopy i zmienia koszty**; technik — rezerwa na przyszłe funkcje; admin — konta).

**W zakresie (nowe, wymuszone brakiem bazy):** **wymienny magazyn danych** — plik JSON (domyślnie, zero konfiguracji) albo MySQL (własna baza), wybierany wpisem w `config/persistence.php` / `.env`; przenoszenie danych z JSON do MySQL komendą; ten sam zestaw funkcji w obu trybach.

**Poza zakresem v1 (backlog, sekcja 15):** publiczne sprawdzanie statusu przez klienta (świadoma decyzja — na razie nie), fakturowanie/księgowość, integracje SMS/e-mail, portal klienta z logowaniem, podpis na ekranie, statystyki/raporty sprzedażowe, wielooddziałowość, magazyn części.

### 1.3 Kryteria akceptacji całości

1. **Parzystość:** każda pozycja z tabeli F-01…F-57 (sekcja 3.4) ma w nowej aplikacji odpowiednik działający na produkcji albo jawną decyzję o odrzuceniu (kolumna „Decyzja”) — bez cichych braków.
2. **Zero długu z rejestru defektów:** żadna pozycja D-01…D-58 (sekcja 4) nie występuje w nowej aplikacji; dla każdej jest test regresyjny albo wpis w „nie dotyczy” z uzasadnieniem. Publiczne strony (`reklamacja*`, `wyswietlanie*`, `wydane.php`) **nie istnieją** — usunięte, nie „ukryte”.
3. **Dwa magazyny danych:** cały krytyczny zestaw testów przechodzi **zarówno na `DATA_STORE=json`, jak i `DATA_STORE=mysql`**; przełączenie to jedna zmiana w `.env` + `artisan store:check`; aplikacja startuje bez bazy (JSON) i bez utraty funkcji.
4. **Dane:** jeśli właściciel dostarczy zrzut starej bazy — wszystkie zlecenia, zdjęcia i historia są przeniesione z raportem zgodności; jeśli nie dostarczy — aplikacja startuje na pustych danych, a importer pozostaje gotowy i przetestowany na fiksturze.
5. **Jakość:** `php artisan test` zielone (min. 1 test na każde AC i oba tryby magazynu), PHPStan poziom ≥ 6 bez błędów, Pint czysty, e2e w **Laravel Dusk** zielone na Chromium (widok 390×844 i 1366×768). **W projekcie nie ma `package.json`, `node_modules` ani skryptów Pythona.**
6. **Bezpieczeństwo:** brak plaintextowych haseł, brak nieautoryzowanego dostępu do danych klienta (test enumeracji), CSRF na każdej mutacji, rate limit logowania, zdjęcia niedostępne bez sesji, brak XSS w parametrach komunikatów (test na `?error=`), `.env` i `storage/` poza webrootem lub zablokowane w `.htaccess`; **pliki JSON z danymi leżą poza webrootem i mają blokadę zapisu między procesami**.
7. **UX:** wszystkie zadania z sekcji 7 wykonalne na ekranie 390×844 (telefon) i 1366×768 (desktop) bez poziomego scrolla; dostępność: brak błędów krytycznych axe na 5 kluczowych widokach; **termin realizacji nie zmienia się po samym otwarciu i zapisaniu formularza** (regresja D-44); **druk potwierdzenia przyjęcia wychodzi z poprawnym numerem zlecenia i terminem**.
8. **Odcięcie:** stara aplikacja zarchiwizowana i niedostępna publicznie; nowa obsługuje 100% ruchu pod `epii.pl/serwis`; procedura rollbacku przetestowana.

---

## 2. Podstawa planu: metoda i źródła

Plan powstał z **pełnego przeczytania kodu**, nie z dokumentacji projektu (ta jest miejscami nieaktualna — np. `old-app/AUDYT-RAPORT.md` opisuje pliki już usunięte, a `old-app/agents.md` twierdzi, że `wyswietlanie_phone.php` jest zrefaktoryzowany — w kodzie nadal ma surowy SQL i brak autoryzacji).

Przeczytane w całości lub w istotnej części:

- **Rdzeń:** `config/config.php`, `database.php`, `includes/{Database,Auth,SessionManager,Helpers,Service}.php`, `index.php`, `logowanie.php`, `process_login.php`, `logout.php`.
- **UI:** `dashboard.php` (ponad 1500 linii), `dashboard_mobile.php` (ponad 1200), `edit_service.php`, `release_service.php`, `search_by_phone.php`, `search_by_plomba.php`, `dodawanie_laptopa.php`, `edytowanie.php`, `edytowanie_phone.php`, `wydawanie.php`, `wydawanie_phone.php`, `wyswietlanie.php`, `wyswietlanie_phone.php`, `wydane.php`, `main_phone.php`, `reklamacja*.php` (5 plików).
- **Endpointy:** `process_add_service.php`, `process_edit_service.php`, `process_release_service.php`, `process_change_status.php`, `get_service.php`, `get_service_images.php`, `get_service_history.php`, `delete_image.php`.
- **PWA/frontend:** `manifest.json`, `service-worker.js`, `pwa-manager.js`, `pwa-head.php`, `offline.html`, `lightbox.js`, `lightbox.css`, `sort-services.js`, `images/`.
- **Dane/narzędzia:** `setup_database.sql`, `setup_sql7817169.sql`, `add_service_history.sql`, `migrations/add_koszty_column.sql`, `setup.php`, `load_test_data.php`, `test_data_30_laptops.sql`, `diagnose_images.php`, `fix_image_paths.php`, `verify-pwa.php`, `logs/actions.log`, `logs/errors.log` (814 linii).
- **Dokumentacja autora:** `agents.md`, `AUDYT-RAPORT.md`, `IMPROVEMENTS.md`, `TODO-panel.md`, `FIX_REPORT.md`, `COMPLETE.md`, `DOCUMENTATION.md`, `INSTALLATION.md`, `DEPLOYMENT-CHECKLIST.md`, `SETUP.md`, `PWA-*.md`.

### 2.1 Weryfikacja runtime (wykonana)

Aplikacja została **uruchomiona lokalnie i przeklikana**, żeby potwierdzić wnioski z kodu i znaleźć funkcje, których statyczna analiza nie pokazuje. Środowisko:

| Element | Wartość |
|---|---|
| PHP | 8.2.12 (XAMPP, rozszerzenia: mysqli, gd, fileinfo, mbstring, openssl) |
| Baza | MariaDB 10.4.32 — **czysty, tymczasowy data-dir** w `_runtime/mysql-data` (istniejący data-dir XAMPP był uszkodzony: crash InnoDB `Page ... is in the future` + `parse_user`), serwer startowany z `--character-set-server=utf8mb4` |
| Schemat | `setup_database.sql` + ręcznie utworzona `service_history` (bo `add_service_history.sql` zawiera błędny `ALTER`) |
| Aplikacja | `php -S 127.0.0.1:8080` z docrootem, w którym `old-app/` jest podłączony jako `/serwis/` (junction), żeby `BASE_URL='/serwis/'` działał |
| Klient | Chromium przez Playwright MCP (narzędzie audytu, **nie** zależność projektu), widoki 1366×768 i 390×844, dodatkowo kontekst z User-Agentem iPhone |

Wykonane przejścia: logowanie/wylogowanie, kolejka, wyszukiwanie (telefon, plomba, podciąg), przyjęcie z uploadem zdjęcia, galeria + lightbox + usuwanie zdjęcia, zmiana statusu, edycja (desktop, mobile, `edit_service.php`, `edytowanie.php`, `edytowanie_phone.php`), wydanie (desktop i mobile), historia, strony `search_by_*`, `wyswietlanie*`, `wydane.php`, `reklamacja*`, `main_phone.php`, `dodawanie_laptopa.php`, `wydawanie.php`, `verify-pwa.php`, a także: rejestracja Service Workera, zawartość Cache Storage, praca bez sieci, manifest i ikony, zachowanie przy `?error=`.

**Wynik:** 18 defektów z analizy kodu potwierdzonych, **15 nowych znalezionych** (D-44…D-58), 3 wcześniejsze ustalenia skorygowane (szczegóły w 3.6). Środowisko zatrzymane; skrypty startowe są w sekcji 17.2 — można je odtworzyć w każdej chwili.

Czego **nie** ma w repozytorium (i dlatego jest w ryzykach): zrzutu rzeczywistej bazy produkcyjnej, konfiguracji hostingu, `.htaccess`, kopii `.env`, danych ile realnie jest zleceń i zdjęć. Wnioski o środowisku produkcyjnym nadal opierają się na logach (`logs/errors.log`), nie na dostępie do serwera.

---

## 3. Inwentaryzacja starej aplikacji

### 3.1 Jak to działało

- **PHP 8.2 + MySQL**, styl proceduralno-obiektowy: klasy w `includes/`, ale strony pisane „od góry do dołu” z HTML, CSS i JS w jednym pliku.
- **Dwa równoległe frontendy:** `dashboard.php` (desktop, sidebar + formularze inline) i `dashboard_mobile.php` (telefon, zakładki + menu). Routing po User-Agencie w `index.php` i ponownie w `dashboard.php`.
- **Trzeci, „równoległy” zestaw stron** (pozostałość po wcześniejszej wersji): `main_phone.php` → `wyswietlanie_phone.php` → `edytowanie_phone.php`/`wydawanie_phone.php` oraz desktopowe `wyswietlanie.php` → `edytowanie.php`/`wydawanie.php`, plus `dodawanie_laptopa.php`. Część jest osiągalna tylko przez ręczny URL, część przez wyniki wyszukiwania (`search_by_*` → `edit_service.php`).
- **Osobny, publiczny moduł „reklamacja”**: `reklamacja.php` (formularz) → `reklamacja_nr_tel.php` / `reklamacja_plomba.php` (wyniki). **Bez logowania.**
- **Jedna baza `epii`**: `users`, `laptopservice`, `laptop_images`, `service_history`.

### 3.2 Mapa ekranów (stan zastany)

| Ekran | Plik | Dostęp | Co robi |
|---|---|---|---|
| Start / router | `index.php` | zalogowany | redirect: mobile → `dashboard_mobile.php`, desktop → `dashboard.php` |
| Logowanie | `logowanie.php` + `process_login.php` | publiczny | formularz login/hasło, błąd przez `?error=` |
| Wylogowanie | `logout.php` | GET, publiczny | niszczy sesję |
| Dashboard desktop | `dashboard.php` | zalogowany, nie-mobile | kolejka w sidebarze + formularze dodaj/edytuj/wydaj + historia + zdjęcia |
| Dashboard mobile | `dashboard_mobile.php` | zalogowany | zakładki: Lista / Dodaj / Wydawanie oraz menu: Szukaj / Wydane / Wyloguj |
| Edycja (strona) | `edit_service.php` | zalogowany | pełnostronicowy formularz edycji + galeria + usuwanie zdjęć |
| Wydanie (strona) | `release_service.php` | zalogowany | strona potwierdzenia wydania |
| Szukanie po telefonie | `search_by_phone.php` | zalogowany | wyniki dokładne + link „Edytuj” |
| Szukanie po plombie | `search_by_plomba.php` | zalogowany | jak wyżej, po `plomba` |
| Dodawanie (stare) | `dodawanie_laptopa.php`, `main_phone.php` | zalogowany | formularze POST → `process_add_service.php` |
| Edycja (stare) | `edytowanie.php`, `edytowanie_phone.php` | zalogowany (mobile: po wypluciu HTML) | formularze POST → `process_edit_service.php` |
| Wydanie (stare) | `wydawanie.php`, `wydawanie_phone.php` | zalogowany (mobile: po wypluciu HTML) | formularze POST → `process_release_service.php` |
| Listy (stare) | `wyswietlanie.php`, `wyswietlanie_phone.php`, `wydane.php` | **publiczny** | surowy SQL, karty z „Edytuj”/„Wydaj” |
| Reklamacja – formularz | `reklamacja.php`, `reklamacja_phone.php` | publiczny | dwa formularze GET (telefon, plomba) |
| Reklamacja – wyniki | `reklamacja_nr_tel.php`, `reklamacja_plomba.php` | publiczny | karty z danymi klienta **i hasłem do laptopa** |
| Weryfikacja PWA | `verify-pwa.php` | publiczny | skrypt diagnostyczny |
| Instalator | `setup.php`, `load_test_data.php` | publiczny | tworzy tabele / wstawia 30 rekordów testowych |

### 3.3 Model danych zastany i jego rozjazdy

Tabela `users`: `id`, `Login` (UNIQUE), `Haslo` (plaintext!), `created_at`, `updated_at`.

Tabela `laptopservice` — jedno zlecenie = jeden wiersz, klient utożsamiany wyłącznie numerem telefonu:

| Kolumna | Typ | Znaczenie / uwagi |
|---|---|---|
| `Id` | INT PK AI | używane też jako nazwa katalogu zdjęć `uploads/{Id}/` |
| `nr_tel` | VARCHAR(20) | telefon klienta, **bez normalizacji i walidacji** |
| `model_laptopa` | VARCHAR(100) | model urządzenia |
| `usterka` | LONGTEXT | opis usterki |
| `haslo` | VARCHAR(100) | hasło do systemu klienta — **plaintext, pokazywane też publicznie** |
| `serial_number` | VARCHAR(100) | S/N |
| `express` | TINYINT(1) | wyróżnienie w kolejce |
| `potwierdzenie_odbioru` | TINYINT(1) | flaga — **nigdzie nieużywana w logice** |
| `uwagi` | LONGTEXT | uwagi wewnętrzne |
| `planowany_czas_wydania` | DATETIME | termin obiecany klientowi |
| `cena` | DECIMAL(10,2) | cena dla klienta |
| `koszty` | DECIMAL(10,2) | koszt części (dodane migracją 2026-02-14) |
| `plomba` | VARCHAR(100) | numer plomby/naklejki |
| `status` | VARCHAR(50) | `oczekujacy`, `w_trakcie`, `czeka_na_czesci`, `do_wydania`, `wydany` |
| `godzina_wydania` | DATETIME | moment wydania |
| `przyjety_przez` | VARCHAR(50) | login przyjmującego, FK → `users.Login` |
| `created_at` / `updated_at` | TIMESTAMP | data przyjęcia / modyfikacji |

Tabela `laptop_images`: `id`, `laptop_id` (FK → `laptopservice.Id`, CASCADE), `image_path`, `uploaded_at`. Ścieżka zapisywana **niespójnie** (`uploads/5/x.jpg`, `/uploads/5/x.jpg`, `/serwis/uploads/5/x.jpg`) i łatana w locie przez `Helpers::getImageSrc()`.

Tabela `service_history`: `id`, `service_id`, `action_type` (`accepted`, `edited`, `status_changed`, `released`), `performed_by`, `performed_at`, `description`, `previous_values` (JSON), `new_values` (JSON).

**Rozjazdy schematu (dowód: 3 pliki SQL):**

| Problem | Szczegóły |
|---|---|
| Trzy warianty schematu | `setup_database.sql` (baza `epii`, **bez** `service_history`), `setup_sql7817169.sql` (baza hostingowa, `service_history` w **niekompatybilnym** kształcie `old_status/new_status/changed_by/change_date/notes`), `add_service_history.sql` (właściwy kształt używany przez kod) |
| `setup.php` starszy od wszystkich | nie tworzy `koszty` ani `service_history` → „świeża instalacja” natychmiast psuje dodawanie/edycję i historię |
| Nieistniejąca kolumna | `add_service_history.sql` robi `ALTER TABLE ... ADD INDEX (data_przyjecia)`, ale kolumny `data_przyjecia` nie ma w żadnym dumpie |
| Redundantna migracja | `add_koszty_column.sql` dubluje kolumnę już obecną w obu dumpach |
| `users.id` | wg logów produkcyjnych tabela `users` nie miała kolumny `id` (błąd `Unknown column 'id'`) — czyli live schema ≠ dump |
| Brak wersjonowania | brak tabeli migracji; stan bazy ustalany ręcznie |

### 3.4 Pełna lista funkcji (inwentarz do parzystości)

Legenda decyzji: **Z** = zachowaj 1:1, **Z+** = zachowaj i wzmocnij (walidacja/bezpieczeństwo), **P** = przeprojektuj (ta sama potrzeba, inne UI/reguły), **O** = odrzuć, **B** = backlog poza v1.

**Uwaga po weryfikacji runtime:** przeklikanie aplikacji (sekcja 3.6) **nie ujawniło żadnej dodatkowej funkcji użytkownika** poza listą F-01…F-54 — potwierdziło ją i doprecyzowało zachowania (data wydania, brak akcji dla wydanych, zachowanie pól przy zapisie), które są teraz kryteriami akceptacji w sekcji 7. Jedyne „ukryte” elementy to funkcje niedokończone: nawigacja legacy `main_phone.php` → `wyswietlanie_phone.php`/`reklamacja_phone.php` (martwa przez D-49) i strona potwierdzenia `release_service.php` (duplikat wydania).

**A. Uwierzytelnianie i sesja**

| ID | Funkcja | Gdzie w starej apce | Decyzja |
|---|---|---|---|
| F-01 | Logowanie login + hasło, komunikat błędu, redirect na start | `logowanie.php`, `process_login.php`, `Auth::login()` | Z+ (hash, throttling, generyczny komunikat) |
| F-02 | Wylogowanie | `logout.php` | Z+ (POST + CSRF, czyszczenie cookie) |
| F-03 | Ochrona widoków i endpointów przed anonimem | `Auth::requireLogin()` w większości plików — **nie** w `reklamacja*`, `wyswietlanie*`, `wydane.php` | Z+ (wszędzie, polityki) |
| F-04 | Rotacja ID sesji po logowaniu | `SessionManager::regenerate()` | Z |
| F-05 | Tokeny CSRF na formularzach POST | `SessionManager::getCSRFToken()/verifyCSRFToken()`; brak na logowaniu i `delete_image.php` | Z+ (wszystkie mutacje, w tym login) |
| F-06 | Zarządzanie użytkownikami (dodanie pracownika) | **brak** (ręczny SQL) | Z+ (panel admina, **trzy role: admin / technik / pracownik; pracownik wydaje i zmienia koszty**) |

**B. Przyjęcie zlecenia (create)**

| ID | Funkcja | Gdzie | Decyzja |
|---|---|---|---|
| F-07 | Telefon klienta (wymagany) | `process_add_service.php`, oba dashboardy | Z+ (normalizacja E.164 + walidacja) |
| F-08 | Model laptopa (wymagany) | jw. | Z |
| F-09 | Opis usterki (wymagany, długi tekst) | jw. | Z |
| F-10 | Hasło do systemu klienta (opcjonalne) | jw. + `haslo` | Z+ (szyfrowane, maskowane, ujawnienie = zdarzenie w audycie) |
| F-11 | Numer seryjny (opcjonalny) | jw. | Z |
| F-12 | Uwagi (opcjonalne) | jw. | Z |
| F-13 | Planowany termin wydania (wymagany, data+godzina) | jw.; desktop domyślnie „jutro 15:00” | Z+ (domyślka + walidacja, bez przesunięcia strefy) |
| F-14 | Cena dla klienta | jw. (`cena`) | Z+ (walidacja liczbowa, grosze) |
| F-15 | Koszt części | jw. (`koszty`), pole tylko na desktopie | Z+ (wszędzie, nie zerowane przy edycji) |
| F-16 | Znacznik „express” (priorytet w kolejce) | jw. (`express`) | Z |
| F-17 | Znacznik „potwierdzenie odbioru” | jw. (`potwierdzenie_odbioru`) | **P** — decyzja D6: pole przeznaczone na **flagę „potwierdzenie przyjęcia wydane”**, widoczną na karcie zlecenia |
| F-18 | Wiele zdjęć przy przyjęciu (aparat w telefonie) | `zdjecie[]`, `process_add_service.php` | Z+ (limity, re-encode, miniatury) |
| F-19 | Status początkowy `oczekujacy` + autor przyjęcia + data | `Service::addService()` | Z |
| F-20 | Wpis „przyjęto” w historii | `addServiceHistory('accepted', ...)` | Z |

**C. Kolejka i praca bieżąca (read)**

| ID | Funkcja | Gdzie | Decyzja |
|---|---|---|---|
| F-21 | Kolejka: wszystkie zlecenia poza `wydany` + 10 ostatnich wydanych | `dashboard.php` (PHP + `array_merge`) | Z+ (paginacja/limity, serwerowe filtry) |
| F-22 | Sortowanie: express → priorytet statusu → najbliższy termin | `dashboard.php` (usort), `sort-services.js` | Z (jedna implementacja serwerowa) |
| F-23 | Karta zlecenia: model, badge statusu, telefon, termin, skrót usterki, hasło, cena | oba dashboardy | Z+ (hasło domyślnie ukryte) |
| F-24 | Przycisk „następny etap” na karcie | `dashboard.php` → `process_change_status.php` (AJAX) | Z+ (walidacja przejść) |
| F-25 | Przycisk „Wydaj” dla `do_wydania` | `dashboard.php` → formularz wydania | Z |
| F-26 | Lista „Wydane” (50/100 ostatnich, grupowanie po dniu) | `dashboard_mobile.php` (LIMIT 50), `wydane.php` (LIMIT 100, grupowanie) | Z+ (zalogowani, paginacja, filtry) |

**D. Wyszukiwanie**

| ID | Funkcja | Gdzie | Decyzja |
|---|---|---|---|
| F-27 | Globalna wyszukiwarka: podciąg ≥ 3 znaków po telefonie/plombie/modelu/usterce/serialu/hasle | `dashboard.php` (`data-search`, JS) | P (serwerowo, jeden wspólny mechanizm) |
| F-28 | Wyszukiwanie dokładne po telefonie (strona wyników) | `search_by_phone.php`, `Service::getServicesByPhone()` | P (jeden ekran wyszukiwania) |
| F-29 | Wyszukiwanie dokładne po plombie | `search_by_plomba.php`, `Service::getServicesByPlomba()` | P |
| F-30 | Publiczne sprawdzenie statusu po telefonie | `reklamacja_nr_tel.php` (bez auth) | **O** — decyzja D4: klient na razie nie sprawdza statusu (backlog B6) |
| F-31 | Publiczne sprawdzenie statusu po plombie | `reklamacja_plomba.php` (bez auth, SQL injection) | **O** — jak wyżej; cały moduł `reklamacja*` usunięty |
| F-32 | Filtrowanie po statusie (uśpione: przyciski filtrów usunięte, funkcje zostały) | `filterServices()`, `filterServicesMobile()` | P (filtry statusu w nowym UI) |

**E. Zmiana statusu i edycja**

| ID | Funkcja | Gdzie | Decyzja |
|---|---|---|---|
| F-33 | Zmiana statusu AJAX z walidacją listy statusów + wpis w historii | `process_change_status.php` | Z+ (maszyna stanów) |
| F-34 | Edycja wszystkich pól zlecenia | `dashboard.php` (inline), `edit_service.php`, `edytowanie(.phone).php` | P (jeden formularz) |
| F-35 | Zmiana statusu z formularza edycji | `edit_service.php`, `edytowanie*.php` | Z+ (jako osobna akcja z audytem) |
| F-36 | Edycja numeru plomby | formularze edycji | Z+ (normalizacja, ostrzeżenie o duplikacie) |
| F-37 | Galeria zdjęć w edycji + usuwanie zdjęcia | oba dashboardy, `edit_service.php`, `delete_image.php` | Z+ (CSRF, autoryzacja, soft-delete pliku) |
| F-38 | Historia zmian: typ zdarzenia, autor, czas, opis, diff pól | `service_history`, panel w `dashboard.php`, `get_service_history.php` | Z+ (pełny dziennik, także mobile, paginacja) |

**F. Wydanie zlecenia**

| ID | Funkcja | Gdzie | Decyzja |
|---|---|---|---|
| F-39 | Formularz wydania z potwierdzeniem, danymi tylko-do-odczytu, edycją ceny/uwag/plomby/hasła/S-N | `dashboard.php` (inline), `release_service.php`, `wydawanie(.phone).php` | P (jedna ścieżka, dwustopniowe potwierdzenie) |
| F-40 | Wydanie ustawia status `wydany` i znacznik czasu | `Service::releaseService()` | Z |
| F-41 | Wpis „wydano” w historii | `process_release_service.php` | Z |
| F-42 | Wydanie z telefonu (uproszczony formularz: data + notatka) | `dashboard_mobile.php` | **P** — w starej wersji **zawsze błąd** (kontrakt `service_id` vs `id`); w nowej jedna ścieżka dla obu urządzeń |

**G. PWA, UI, infrastruktura**

| ID | Funkcja | Gdzie | Decyzja |
|---|---|---|---|
| F-43 | Instalacja jako aplikacja (manifest, ikony, standalone, skróty) | `manifest.json`, `pwa-manager.js` | Z+ (komplet ikon, poprawne skróty) |
| F-44 | Offline: strona offline + cache powłoki, baner online/offline | `service-worker.js`, `offline.html`, `pwa-manager.js` | P (decyzja D5: shell read-only vs kolejka zapisów) |
| F-45 | Powiadomienia push | `service-worker.js` (handler bez subskrypcji serwerowej) | **B** (atrapa w starej; brak backendu push) |
| F-46 | Background sync formularzy | `service-worker.js` (tylko `showNotification`) + `pwa-manager.js` (`registration.sync`) | **B** (decyzja D5) |
| F-47 | Powiększanie zdjęć (lightbox: zoom, strzałki, ESC, swipe, licznik) | `lightbox.js`, `lightbox.css` | Z |
| F-48 | Osobny UX telefonu (zakładki, menu) i desktopu (sidebar) | `dashboard_mobile.php` vs `dashboard.php` | P (jeden responsywny UI) |
| F-49 | Automatyczny wybór wersji po User-Agencie | `index.php`, `Helpers::isMobile()` | **O** (zastąpione responsywnym UI) |
| F-50 | Log akcji (audyt) | `logs/actions.log`, `Helpers::logAction()` | Z+ (tabela audytu w DB, bez duplikatów) |
| F-51 | Log błędów | `logs/errors.log` (zalewany debugiem) | Z+ (kanały logów, rotacja) |
| F-52 | Inicjalizacja bazy / konto startowe | `setup.php` | P (komenda `artisan app:install` lokalnie + **jednorazowy route `/__install` z tokenem** na hostingu bez SSH, wyłączany po użyciu) |
| F-53 | Dane testowe (30 laptopów) | `load_test_data.php`, `test_data_30_laptops.sql` | P (factory + seeder, tylko poza produkcją) |
| F-54 | Diagnostyka i naprawa ścieżek zdjęć | `diagnose_images.php`, `fix_image_paths.php` (oba **nie działają** — `new Database()` na prywatnym konstruktorze) | P (komenda `artisan photos:verify`) |
| **F-55** | **Druk potwierdzenia przyjęcia i protokołu wydania** (nowe, na życzenie właściciela) | brak w starej apce | **N** — checkbox „wydrukuj potwierdzenie przyjęcia” przy przyjęciu, widok do druku A4 (numer zlecenia, model, usterka, termin, telefon), przycisk „drukuj ponownie” na karcie zlecenia |
| **F-56** | **Anulowanie zlecenia** (nowe, wynika z decyzji o statusach) | brak w starej apce (jedynym obejściem było usunięcie wiersza) | **N** — status `cancelled` z powodem, wpis w historii, zlecenie znika z kolejki, zostaje w archiwum |
| **F-57** | **Wymienny magazyn danych** (nowe, bo bazy jeszcze nie ma) | brak w starej apce (na sztywno MySQL + drugi `database.php`) | **N** — `config/persistence.php` z opcjami `json` (plik, start bez bazy) i `mysql` (własna baza), przełączanie w `.env`, komendy `store:check` i `store:json-to-mysql` |

**Uwaga o numeracji:** lista F liczy **57 pozycji** (F-01…F-54 przeniesione/odrzucone ze starej aplikacji + F-55/F-56/F-57 nowe); numeracja powyżej jest wiążąca dla reszty dokumentu.

### 3.5 Kod „uśpiony” — funkcje zadeklarowane, nigdy nieużywane

To nie są funkcje do przeniesienia, ale dowód, że stara aplikacja ma niedokończone zamierzenia. W nowej wersji albo je realizujemy, albo świadomie odrzucamy:

| Element | Stan | Decyzja |
|---|---|---|
| `Service::getStatistics()` | metoda istnieje, **zero wywołań** | B (statystyki w v2) |
| `Helpers::validatePhone()`, `Helpers::validateEmail()` | istnieją, **zero wywołań** — telefon nigdy nie jest walidowany | Z+ (używamy przy F-07) |
| `displayServiceDetails()` (dashboard) | odwołuje się do `#service-details-container`, którego **nie ma w HTML** | O (martwy) |
| `changeServiceStatus()` (mobile) | zdefiniowana, **żaden element jej nie wywołuje** | Z+ (realizujemy jako F-24 na mobile) |
| `filterServices()`, `showAllServices()`, `searchServices()` (dashboard) | filtry usunięte z UI w „Fazie 2.1”, funkcje zostały | P (F-32 w nowym UI) |
| `editService()` (mobile) | redirect do `edit_service.php`, nieużywany | O |
| `pwa-head.php` | **nie jest dołączany przez żadną stronę** (mimo dokumentacji, że jest) | O (zastąpione komponentem layoutu) |
| `verify-pwa.php` | narzędzie deweloperskie, publiczne | O (zastąpione testem/manifestem) |
| Widoki `v_today_pending`, `v_to_release_today`, `v_service_statistics` i procedury `sp_overdue_parts_services`, `sp_daily_statistics` | zdefiniowane w dumpach SQL, **nigdzie nie odpytywane przez aplikację** (jedyne wystąpienia to `setup.php`, który je tworzy) | B (statystyki w v2) |

### 3.6 Co pokazało uruchomienie aplikacji (dowody runtime)

Poniżej **wyniki klikania**, nie analizy kodu. Każdy punkt był obserwowany w przeglądarce lub w bazie po akcji.

**Potwierdzone błędy funkcjonalne (numeracja D z sekcji 4):**

| Obserwacja | Jak sprawdzone | Numer |
|---|---|---|
| Zapis formularza edycji **bez zmiany żadnego pola** przesuwa `planowany_czas_wydania` z `2026-11-05 16:42:46` na `2026-11-05 15:43:00` (‑1 h, sekundy wyzerowane); historia odnotowuje zmianę, której użytkownik nie zrobił | `SELECT` przed/po zapisie formularza desktop | **D-44** (nowy), wzmacnia D-24 |
| Domyślna data w formularzu przyjęcia to `13:00` zamiast zamierzonego „jutro 15:00” — **przesunięcie o 2 h** (dwa razy `toISOString()`) | odczyt wartości pola + porównanie z `Intl` (strefa Europe/Warsaw) | **D-45** (nowy) |
| Wydany serwis **express** stoi na drugim miejscu kolejki (nad naprawami) i ma przycisk „Next”, który po kliknięciu daje `400` i alert „Błąd: Failed to update status” | klik w kartę wydanego zlecenia, odczyt odpowiedzi sieci | **D-46** (nowy) |
| `edit_service.php` **nie ma pola `koszty`** → zapis zeruje koszt (50,00 → 0,00) | zapis formularza + `SELECT` | **D-47** (nowy), wzmacnia D-14 |
| `edytowanie.php` i `edytowanie_phone.php` **zaznaczają „Express” i „Potwierdzenie odbioru”** dla zlecenia, które ma 0/0 (pole `express` wysyłane jako `on`) | odczyt checkboxów + kod `isset($row['express'])` | **D-48** (nowy), wzmacnia D-13 |
| `dodawanie_laptopa.php` i `main_phone.php` → **HTTP 500**: `Uncaught Error: Class "Database" not found in includes/Auth.php:21` (brak `require` Database.php) | sweep HTTP wszystkich stron | **D-49** (nowy) |
| `wydawanie.php?id=4` → **200, ~4 KB (obcięte na buforze wyjścia PHP), brak formularza**, brak sesji: `session_start(): Session cannot be started after headers have already been sent` + `Cannot modify header information` | sweep + `logs/errors.log` | **D-50** (nowy) |
| Cena wpisana jako `199,99` zapisana jako `199.00` (grosze obcięte przez MySQL) | przyjęcie z formularza + `SELECT` | **D-51** (nowy), wzmacnia D-23 |
| Telefon zapisany dosłownie jako `600 100 200`; wyszukiwanie `505 123 456` → „Nie znaleziono serwisów” | wyszukiwanie po dwóch formatach | **D-20** (potwierdzony) |
| `reklamacja_plomba.php?plomba=PLO-001-2024` → **HTTP 500** (`Unknown column 'PLO'`); `plomba=0 OR 1=1` → **wszystkie 4 zlecenia z hasłami** | dwa wejścia na publiczną stronę | **D-03** (potwierdzony, krytyczny) |
| `logowanie.php?error=` i `dashboard_mobile.php?error=` → **wykonany `alert()`** (XSS bez logowania) | podwójnie zakodowany payload w URL | **D-04** (potwierdzony, krytyczny) |
| Service Worker zarejestrowany, ale **precache nie zawiera `offline.html`**; offline na niecache'owanej stronie → `net::ERR_FAILED` zamiast strony offline | `caches.keys()`/`caches.match` + `context.setOffline(true)` | **D-27** (potwierdzony) |
| Cache `skomp-v1` (29 wpisów) zawiera **HTML dashboardu z telefonami i hasłami**, JSON `get_service.php` i **zdjęcie klienta**; po wylogowaniu nadal czytelne | odczyt Cache Storage przed/po `logout.php` | **D-28** (potwierdzony, krytyczny) |
| 3 z 4 ikon manifestu → **404** (`192-maskable`, `512`, `512-maskable`), a przycisk „Zainstaluj aplikację” i tak się pokazuje | `fetch` ikon z manifestu w kontekście strony | **D-29** (potwierdzony) |
| Wydanie z telefonu (UA iPhone) → `?error=Nieprawidłowy ID serwisu`, czerwony alert; z UA desktop na wąskim oknie → **redirect na `dashboard.php` i cisza** (parametr ignorowany) | dwa konteksty przeglądarki | **D-10**, **D-12** (potwierdzone) |
| Usunięcie zdjęcia: rekord i plik znikają, ale **brak wpisu w historii** (jest tylko w `actions.log` jako `IMAGE_DELETED`); katalog `uploads/4/` zostaje pusty | `SELECT` + `Get-ChildItem` | **D-14/D-38** (potwierdzone) |
| Każda operacja biznesowa logowana **dwukrotnie** (`ADD_SERVICE` + `SERVICE_ADDED`, `UPDATE_SERVICE` + `SERVICE_UPDATED`, `RELEASE_SERVICE` + `SERVICE_RELEASED`), `Details` zawiera numery telefonów klientów | `logs/actions.log` po kliknięciach | **D-38** (potwierdzony) |

**Funkcje i zachowania, których statyczna analiza nie wychwyciła (do uwzględnienia w nowej wersji):**

1. **Wydanie z formularza nie ma pola daty wydania** — czas zawsze bierze się z serwera. Formularz mobilny *ma* pole daty, ale jest ono ignorowane. Nowa wersja: jedno pole „data wydania” z domyślną wartością „teraz” (F-39).
2. **„Potwierdzenie odbioru” jest zbierane dwa razy** (przy przyjęciu i przy wydaniu), a nigdzie nie jest używane. **Decyzja D6:** pole zmienia znaczenie na „potwierdzenie przyjęcia wydane” i jest widoczne na karcie zlecenia.
3. **Karta wydanego zlecenia** pokazuje przycisk akcji, którego nie powinno być (dla `wydany` brak wpisu w mapie etykiet → „Next”). Nowa wersja: brak akcji dla zakończonych (F-24).
4. **Panel historii jest tylko na desktopie** i pokazuje surowe nazwy kolumn (`planowany_czas_wydania`), daty w formacie ISO. Nowa wersja: polskie etykiety pól, format lokalny, panel także na telefonie (F-38).
5. **`?logout=1` nic nie robi** — brak komunikatu „wylogowano”. Drobiazg UX do dodania (F-02).
6. **`upload` zapisuje oryginał bez miniatury i bez re-encode** (47 KB zdjęcie trafiło na serwer 1:1; plik 568 KB też by trafił). Nowa wersja: miniatury + re-encode (F-18).
7. **Plik zdjęcia jest publicznie dostępny bez sesji** (`/serwis/uploads/...` → 200). Nowa wersja: serwowanie przez kontroler z polityką (F-37).
8. **Akcje są dwuścieżkowe:** `process_change_status.php` (AJAX) vs `process_release_service.php` (formularz) — po wydaniu nie da się już nic zrobić, a „Next” nadal jest widoczny.
9. **Sortowanie po wyszukaniu przenosi dopasowane elementy na koniec DOM** — kolejność wizualna pojedynczego wyniku jest przypadkowa (nie ma to znaczenia przy 1 wyniku, ale psuje kolejność przy filtrach częściowych). Nowa wersja: sortowanie wyłącznie serwerowe.
10. **`verify-pwa.php` jest publiczny** i raportuje 21 „success” mimo 3 brakujących ikon (fałszywe poczucie poprawności).

**Skorygowane wcześniejsze ustalenia:**

| Wcześniej | Faktycznie |
|---|---|
| „`edytowanie_phone.php` ma błąd `isset()` na checkboxach” | Dotyczy **także desktopowego `edytowanie.php`** — każdy zapis oznacza zlecenie jako express |
| „`koszty` zerowane tylko na mobile” | Zerowane przez **każdy** formularz bez pola `koszty`: `edit_service.php`, `edytowanie.php`, `edytowanie_phone.php` (formularze w dashboardach mają to pole i zachowują wartość) |
| „`backend nie obsługuje zdjęć przy wydaniu`” | Dodatkowo: `wydawanie.php` w ogóle nie renderuje formularza (D-50), a `wydawanie_phone.php` renderuje, ale plik jest ignorowany |

---

## 4. Rejestr defektów i długu (co nowa aplikacja musi wyeliminować)

Każda pozycja ma dowód w kodzie lub logach. Kolumna „W nowej aplikacji” mówi, jaka reguła to zamyka — to jest podstawa testów regresyjnych.

### 4.1 Bezpieczeństwo

| ID | Defekt | Dowód | W nowej aplikacji |
|---|---|---|---|
| D-01 | Hasła użytkowników w plaintext i porównanie `===` | `includes/Auth.php`, seed `admin/admin` | `password_hash` (bcrypt/Argon2id), brak plaintext w bazie i seedach |
| D-02 | Anonimowy dostęp do danych klientów (telefon, usterka, **hasło do laptopa**) | `reklamacja_nr_tel.php`, `reklamacja_plomba.php`, `wyswietlanie.php`, `wyswietlanie_phone.php`, `wydane.php` — brak `requireLogin()` | każda trasa za `auth` + polityką; publiczny lookup tylko po decyzji D4 i bez hasła |
| D-03 | SQL injection w wyszukiwaniu po plombie | `reklamacja_plomba.php`: `WHERE plomba = $plomba` (bez cudzysłowów, `mysqli::query`) | wyłącznie Eloquent/Query Builder z bindingami; zakaz `DB::raw` z inputem |
| D-04 | XSS reflected i stored | `logowanie.php`: `errorDiv.innerHTML = '<ul><li>' + decodeURIComponent(errorMsg)`; `dashboard_mobile.php`: `alertDiv.innerHTML = '❌ ' + decodeURIComponent(error)` (podwójne dekodowanie); brak escapingu w `wyswietlanie*`, `wydane.php`, `reklamacja_plomba.php` | Blade `{{ }}` (auto-escape), zakaz `{!! !!}` dla danych użytkownika, CSP bez `unsafe-inline` |
| D-05 | Brak CSRF na usuwaniu zdjęć i logowaniu; brak autoryzacji obiektowej | `delete_image.php` nie weryfikuje tokenu (desktop go nie wysyła); brak sprawdzenia, czy zdjęcie należy do dozwolonego zlecenia | CSRF globalnie (Laravel), `Policy` na każdą akcję na zdjęciu |
| D-06 | Publiczne narzędzia administracyjne | `setup.php`, `load_test_data.php` (nadpisuje hasło admina na `admin`), `diagnose_images.php`, `fix_image_paths.php`, `verify-pwa.php` | tylko komendy Artisan / panel admina za rolą; zero skryptów instalacyjnych w webroot |
| D-07 | Brak limitu prób logowania, brak timeoutu sesji, wylogowanie GET-em bez CSRF, enumeracja kont (różne komunikaty) | `Auth::login()`, `logout.php`, `SessionManager` | throttle 5/15 min, komunikat „Nieprawidłowy login lub hasło”, POST+CSRF na logout, `session.expire_on_close`/idle timeout, `SameSite=Lax`, `Secure` |
| D-08 | Sesja niszczona bez czyszczenia cookie i cache PWA | `SessionManager::logout()`, `logout.php` | pełny logout: session invalidate + regenerate token + czyszczenie Cache Storage po stronie klienta |
| D-09 | Hasło urządzenia jawne w DB, w JSON API i w cache PWA | `get_service.php` (`SELECT *`), HTML dashboardu w cache | szyfrowanie kolumny (`encrypted` cast), maskowanie w UI, ujawnienie = wpis w audycie, brak hasła w cache/offline |

### 4.2 Błędy funkcjonalne (użytkownik traci dane albo funkcja nie działa)

| ID | Defekt | Dowód | W nowej aplikacji |
|---|---|---|---|
| D-10 | **Wydanie z telefonu zawsze kończy się błędem** — formularz wysyła `service_id`, backend czyta `id` i wymaga 4 pól | `dashboard_mobile.php` (formularz wydania) vs `process_release_service.php` | jedna akcja `ReleaseService` z jednym kontraktem; test e2e na mobile |
| D-11 | Data wydania wpisana przez użytkownika jest ignorowana (serwer zawsze `NOW()`) | `Service::releaseService()` | `released_at` z formularza z walidacją (nie z przyszłości) lub jawnie „teraz” |
| D-12 | Desktop nie pokazuje żadnego potwierdzenia po dodaniu/edycji/wydaniu (`?added=1`, `?updated=1`, `?released=1`, `?error=` ignorowane) | `dashboard.php` (brak obsługi parametrów), `dashboard_mobile.php` obsługuje tylko `error`/`added` | flash messages po każdej akcji, wspólne dla obu szerokości |
| D-13 | Checkboxy „Express” i „Potwierdzenie odbioru” **zawsze zaznaczone** przy edycji/wydaniu — także na desktopie (potwierdzone runtime) | `edytowanie.php`, `edytowanie_phone.php`, `wydawanie.php`, `wydawanie_phone.php`: `isset($row['express'])` zamiast wartości; pole `express` wysyła `on` | stan z modelu (`$service->is_express`), test na round-trip |
| D-14 | Edycja **zeruje koszty** w każdym formularzu bez pola `koszty` (potwierdzone runtime: 50,00 → 0,00) | `edit_service.php`, `edytowanie.php`, `edytowanie_phone.php` (brak pola); `process_edit_service.php` podstawia `'0'` | jeden formularz ze wszystkimi polami; brak pola = brak zmiany (partial update) |
| D-15 | Formularze mobile wstawiają `Y-m-d H:i:s` do `datetime-local` → pole puste → zapis odrzucony | `edytowanie_phone.php`, `wydawanie_phone.php` | konwersja formatu w warstwie widoku + test formatu |
| D-16 | Upload zdjęć przy wydaniu to atrapa (input jest, backend nie czyta `$_FILES`) | `wydawanie_phone.php` vs `process_release_service.php` | jedna ścieżka zdjęć dla przyjęcia/edycji/wydania |
| D-17 | Brak transakcji: zlecenie + historia + zdjęcia zapisywane osobno; błędy uploadu ignorowane (`$_FILES['error']` nieczytane), częściowy sukces bez komunikatu | `process_add_service.php`, `process_edit_service.php` | `DB::transaction`, obsługa `UploadedFile`, raport częściowych niepowodzeń |
| D-18 | Workflow statusów nieegzekwowany: dowolny status z listy (także `wydany → oczekujacy`), brak walidacji przejść, wydanie i zwykła zmiana statusu to dwie różne ścieżki, a zmiana statusu przez formularz edycji nie tworzy zdarzenia `status_changed` | `process_change_status.php`, `process_edit_service.php`, `Helpers::getNextStatus()` | enum + maszyna stanów (`pending → in_progress → ready_for_pickup → released`, `awaiting_parts` w obie strony), przejście tylko przez akcję z audytem |
| D-19 | Trzy różne priorytety sortowania (PHP desktop: `do_wydania`=2, PHP mobile i JS: `do_wydania`=3) → lista przeskakuje po wyszukaniu | `dashboard.php`, `dashboard_mobile.php`, `sort-services.js` | jedno źródło kolejności (serwer), test kolejności |
| D-20 | Telefon bez walidacji i normalizacji; wyszukiwanie dokładne → ten sam klient wpisany inaczej jest „nieznaleziony” | `Helpers::validatePhone()` nieużywane; `WHERE nr_tel = ?` | walidacja PL + E.164 + kolumna znormalizowana do wyszukiwania |
| D-21 | Niespójne wyszukiwanie: 3 mechanizmy, desktop widzi tylko 10 ostatnich wydanych | `dashboard.php` (client-side), `search_by_phone.php`, `search_by_plomba.php` | jeden serwis wyszukiwania (telefon/plomba/model/SN/usterka), pełny zakres, paginacja |
| D-22 | Plomba bez normalizacji i kontroli duplikatów | wszędzie `plomba` | trim + upper + ostrzeżenie o duplikacie, indeks |
| D-23 | Kwoty: stringi w formularzach, mobile `step="1"` + `Math.round()` przy edycji → utrata groszy | oba dashboardy | `decimal:2` cast, walidacja `numeric` i `min:0`, formatowanie `pl_PL` |
| D-24 | Domyślny termin „jutro 15:00” liczony przez `toISOString()` (UTC) → przesunięcie o 1–2 h; `DATETIME` bez strefy | `roundToFullMinutes()` w obu dashboardach | `Carbon` w `Europe/Warsaw`, zapis UTC, test DST |
| D-25 | Brak możliwości zarządzania użytkownikami (nowy pracownik = ręczny SQL), brak ról | brak ekranu; `users` bez roli | panel admina, role `admin`/`technician`, polityki |
| D-26 | Flaga `potwierdzenie_odbioru` bez znaczenia w procesie | pole istnieje, logika nie używa | decyzja D6: albo proces (podpis/przycisk przy wydaniu), albo usunięcie pola |

### 4.3 PWA i frontend

| ID | Defekt | Dowód | W nowej aplikacji |
|---|---|---|---|
| D-27 | Precache martwy: `cache.addAll` odrzucone (brakujące `style.css`, `script.js`, `style_main.css` + 302 z `/serwis/` i `dashboard.php`), błąd połknięty → brak offline | `service-worker.js` `STATIC_ASSETS` | precache tylko istniejących, zbudowanych assetów; wersjonowany cache; test offline w Playwright |
| D-28 | Cache PWA trzyma dane osobowe (HTML z hasłami/telefonami, JSON `SELECT *`, zdjęcia `cache-first`) i nie jest czyszczony przy wylogowaniu | `service-worker.js`, `get_service.php`, `logout.php` | cache tylko shella i assetów; dane zawsze network-only; czyszczenie przy logout |
| D-29 | PWA nieinstalowalna: w `images/` tylko `icon-192x192.png`; brak 512 → `beforeinstallprompt` nigdy nie wystąpi; screenshoty i `shortcuts` wskazują nieistniejące pliki | `manifest.json`, `images/` | komplet ikon (96/192/512 + maskable), poprawne skróty, walidacja manifestu w CI |
| D-30 | `share_target` kieruje POST na `process_add_service.php` (wymaga sesji + CSRF) → nie działa | `manifest.json` | usunięte albo obsłużone dedykowaną trasą (B) |
| D-31 | Auto-reload po aktualizacji SW (`location.reload()` po 2 s) → utrata wpisywanego formularza | `pwa-manager.js::notifyUpdate()` | baner „odśwież”, decyzja użytkownika, bez automatycznego reloadu |
| D-32 | Background sync to atrapa (tylko powiadomienie), push bez subskrypcji serwerowej | `service-worker.js`, `pwa-manager.js` | jawnie: offline = tylko odczyt; zapisy zablokowane z komunikatem; sync w backlogu (D5) |
| D-33 | Mojibake w komunikatach SW; klasy `online-mode`/`offline-mode`/`pwa-mode` bez CSS | `service-worker.js`, brak CSS | UTF-8 w całym repo + test kodowania; style zdefiniowane |
| D-34 | Lightbox: `refresh()` dokłada kolejne listenery (kumulacja), brak `role="dialog"`/pułapki fokusu, ten sam `z-index: 9999` co przycisk instalacji | `lightbox.js`, `lightbox.css` | dostępny dialog (focus trap, ESC, aria), `refresh()` idempotentny |
| D-35 | ~500–800 linii CSS w każdym pliku PHP, kopiowane 4–5×; klasy bez definicji (`images-grid`) | wszystkie widoki | Bootstrap 5 + jedna warstwa `app.css` + komponenty Blade, zero CSS w plikach logiki |

### 4.4 Dane, infrastruktura, proces

| ID | Defekt | Dowód | W nowej aplikacji |
|---|---|---|---|
| D-36 | Trzy rozbieżne schematy SQL, nieistniejąca kolumna `data_przyjecia`, redundantna migracja `koszty`, `setup.php` bez `koszty`/`service_history` | `setup_database.sql`, `setup_sql7817169.sql`, `add_service_history.sql`, `migrations/add_koszty_column.sql`, `setup.php` | jedna historia migracji (Laravel), schemat odtwarzalny z zera, test `migrate:fresh` |
| D-37 | `database.php` (root, puste hasło) jako drugie źródło konfiguracji, używany przez 4 pliki | `database.php` | wyłącznie `.env` + `config/*`; brak danych dostępowych w repo |
| D-38 | Log `errors.log` zalewany debugiem (760 z 814 linii to zrzuty SQL, **z hasłami klientów i telefonami**); `actions.log` duplikuje zdarzenia | `includes/Database.php` (log na każdym zapytaniu), `logs/*` | kanały logów (daily), poziom `info` na produkcji, brak danych wrażliwych w logach, audyt w DB |
| D-39 | Brak testów, CI i wersjonowania schematu; dokumentacja niezgodna z kodem | cały projekt | Pest + Dusk + GitHub Actions; dokumentacja generowana z kodu |
| D-40 | `getAllServices()` bez paginacji + `json_encode` całej listy do JS | `dashboard.php`, `dashboard_mobile.php` | serwerowa paginacja/filtry, brak pełnego dumpu do JS |
| D-41 | Duplikacja UI: 2 dashboardy, 4 ścieżki edycji, 4 wydania, różne zestawy pól (koszty tylko desktop) | `dashboard*`, `edit_service`, `edytowanie*`, `release_service`, `wydawanie*` | jeden responsywny UI + jedna akcja na operację |
| D-42 | Martwy kod mylący przy utrzymaniu (`displayServiceDetails`, `changeServiceStatus` mobile, `filterServices*`, `getStatistics`, `validatePhone`) | sekcja 3.5 | usunięte albo zrealizowane, zero martwych ścieżek |
| D-43 | Brak możliwości anulowania zlecenia (klient rezygnuje) i brak wydruku potwierdzenia dla klienta | brak w kodzie | anulowanie jako status + wydruk A4/PDF w backlogu (B) |

### 4.5 Defekty wykryte dopiero przez uruchomienie aplikacji

| ID | Defekt | Dowód | W nowej aplikacji |
|---|---|---|---|
| D-44 | **Każdy zapis formularza przesuwa termin realizacji o ~1 h** (i zeruje sekundy), nawet bez zmiany pola; historia odnotowuje fikcyjną zmianę. Przy kilku edycjach obiecany klientowi termin ucieka o godziny | `16:42:46 → 15:43:00` po zapisie bez zmian (`roundToFullMinutes()` + `toISOString()`) | czas trzymany jako `Carbon` w UTC, konwersja tylko do wyświetlania; test: „zapis bez zmian nie zmienia żadnego pola” |
| D-45 | Domyślna data przyjęcia jest przesunięta **o 2 h** (dwa razy `toISOString()`), więc nowe zlecenie od razu ma zły termin | pole pokazuje `13:00` zamiast `15:00` | jedna funkcja `plannedAtDefault()` z testem DST i testem wartości |
| D-46 | Wydane zlecenie **express** stoi nad aktywnymi naprawami i ma martwy przycisk „Next” → `400` + „Failed to update status” | klik w kartę `status=wydany`, `express=1` | zakończone poza kolejką aktywną; brak akcji dla `released`; API zwraca `409` z komunikatem po polsku |
| D-47 | `edit_service.php` nie ma pola `koszty` → zapis **zeruje koszt części** | 50,00 → 0,00 po zapisie | partial update: brak pola = brak zmiany; test regresyjny na wszystkich formularzach |
| D-48 | `edytowanie*.php` zawsze zaznaczają „Express” i „Potwierdzenie odbioru” → samo otwarcie i zapis **zmienia dane zlecenia** | pole `express` = `on`, `isset($row['express'])` zawsze prawdziwe | wartości z modelu, test: „zapis bez zmian zachowuje flagi” |
| D-49 | `dodawanie_laptopa.php` i `main_phone.php` → **HTTP 500** (`Class "Database" not found`) | sweep HTTP + log serwera | jeden formularz przyjęcia w nowej aplikacji; brak stron-sierot |
| D-50 | `wydawanie.php?id=X` → **pusty ekran, 200, 4096 B**: HTML przed `session_start()`, nagłówki już wysłane, brak sesji | sweep + `logs/errors.log` (`Session cannot be started after headers...`) | sesja i auth **przed** jakimkolwiek outputem (middleware), test: „każda strona renderuje się zalogowanemu i przekierowuje anonima” |
| D-51 | Cena z przecinkiem (`199,99`) zapisana jako `199.00` — grosze tracone bez komunikatu | przyjęcie + `SELECT` | normalizacja `,`→`.` i walidacja `numeric`, test `199,99 → 199.99` |
| D-52 | Zdjęcia serwisy są **publiczne**: `/uploads/{id}/{plik}` to plik statyczny serwowany bezpośrednio z dysku, bez kontroli sesji (i trafia do cache przeglądarki) | struktura katalogów + żądania w logu serwera | pliki poza webrootem, serwowane przez kontroler z polityką |
| D-53 | Po usunięciu zdjęcia **nie ma wpisu w historii** (jest tylko w `actions.log`), a pusty katalog `uploads/{id}/` zostaje | usunięcie zdjęcia + `SELECT history` | jedno źródło audytu (DB), wpis `photo_deleted`, sprzątanie katalogów |
| D-54 | Każda operacja logowana **dwukrotnie** (`ADD_SERVICE`+`SERVICE_ADDED`, ...), `Details` zawiera telefon klienta | `logs/actions.log` | jeden wpis audytu na operację, bez PII w logach technicznych |
| D-55 | `?logout=1` bez komunikatu; wylogowanie nie czyści cache PWA ani nie pokazuje potwierdzenia | klik „Wyloguj” | toast „Wylogowano”, czyszczenie cache, komunikat na stronie logowania |
| D-56 | Data wydania: formularz desktopowy **nie ma pola**, mobilny ma i jest ignorowany — nie da się zapisać wydania „z wczoraj” | formularze + `Service::releaseService()` | jedno pole `released_at` z domyślną „teraz”, walidacja „nie z przyszłości” |
| D-57 | Historia pokazuje **surowe nazwy kolumn** i format ISO; panel tylko na desktopie | odczyt panelu historii | polskie etykiety pól, format `d.m.Y H:i`, panel na obu szerokościach |
| D-58 | `verify-pwa.php` (publiczny) raportuje sukces przy 3 brakujących ikonach → fałszywe poczucie poprawności | uruchomienie skryptu + `fetch` ikon | walidacja manifestu/ikon w CI, narzędzie usunięte |

---

## 5. Architektura docelowa

### 5.1 Stack (decyzja D1 — rekomendacja)

**Warunek twardy od właściciela: wyłącznie PHP + HTML + CSS + frameworki PHP. Bez Node.js (npm, Vite, Tailwind CLI, Playwright) i bez Pythona.** Wszystko poniżej spełnia ten warunek.

| Warstwa | Wybór | Uzasadnienie |
|---|---|---|
| Backend | **PHP 8.3/8.4 + Laravel 12.x** (Composer) | ten sam hosting PHP/MySQL co dziś, bez zmiany infrastruktury; wbudowane: auth, walidacja, polityki, migracje, kolejki, testy; PHP 8.4.27 CLI jest już na maszynie deweloperskiej |
| **Magazyn danych** | **wymienny: `json` (plik, domyślnie) albo `mysql`** — wybór w `config/persistence.php` / `.env` (D13) | bazy jeszcze nie ma, więc aplikacja musi wystartować bez niej; gdy baza się pojawi, przełączenie to jedna linia + import danych z JSON |
| UI | **Blade + Livewire 3 + Alpine.js** | interaktywność (kolejka, wyszukiwanie, modale, galeria) bez SPA i bez buildu: Livewire serwuje własne, gotowe pliki JS z pakietu Composer (`@livewireScripts`), Alpine jest w nich zawarty |
| Styl | **Bootstrap 5.3** (plik CSS + JS **skopiowany do `public/` z repo**, nie z CDN) | zero budowania, sprawdzony responsywny grid i komponenty; własna warstwa `app.css` na wierzchu zamiast 800-liniowych `<style>` w każdym pliku |
| Baza (tryb `mysql`) | **MySQL 5.7+/MariaDB 10.3+** (utf8mb4) — **opcjonalna**, włączana konfiguracją | gdy baza będzie dostępna: indeksy, transakcje, skalowanie; do tego czasu wszystko działa na JSON |
| Pliki | Laravel Storage, **dysk prywatny** + kontroler z polityką | zdjęcia poza webrootem (dziś `uploads/` jest publiczne) |
| Obrazy | **GD (rozszerzenie PHP)** — miniatury, re-encode, strip EXIF, **generowanie ikon PWA** | zastępuje `generate-icons.py` (Python) i zewnętrzne narzędzia; GD jest już wymagane przez aplikację |
| Testy | **Pest** (unit/feature) + **Laravel Dusk** (e2e w PHP, ChromeDriver) + `axe-core` jako plik JS **wstrzykiwany z Dusk** | całość w PHP/Composerze, bez `npx playwright`; axe to statyczny plik JS, nie runtime Node |
| Jakość | **Pint**, **Larastan (PHPStan) poziom 6**, `composer audit`, GitHub Actions (setup-php + MySQL) | bramki bez Node |
| PWA | **ręcznie napisany `service-worker.js`** + `manifest.webmanifest` + ikony z GD | brak Workbox/vite-plugin-pwa; wersja cache podbijana ręcznie przy deployu |
| Kolejki | `database` driver + cron `schedule:run` | bez Redisa i bez Node |

**Czego świadomie nie używamy:** npm/pnpm/yarn, Vite, Webpack, Tailwind CLI, Playwright, Puppeteer, Python, ImageMagick-CLI. Jeśli w przyszłości pojawi się potrzeba buildu CSS — dopuszczalny jest **Tailwind standalone CLI** (pojedynczy plik binarny, bez Node), ale nie jest to potrzebne przy Bootstrapie.

**Alternatywa (odrzucona ze względu na warunek):** Next.js/React + PostgreSQL (wymaga Node), Laravel + Inertia + React (wymaga Vite/npm), Tailwind + npm (wymaga Node). Jeśli warunek „bez Node” kiedyś zniknie, najbliższa ścieżka to Tailwind standalone CLI.

**Wersje pinujemy w Fazie 0** (`composer.lock`) — plan nie zamraża numerów, żeby nie starzał się przed startem.

**Wdrożenie na hostingu współdzielonym (decyzja właściciela):** aplikacja działa pod `https://epii.pl/serwis`, pliki wgrywane przez FTP/panel, **bez SSH i bez Composera na serwerze**. Konsekwencje techniczne:

- `APP_URL=https://epii.pl/serwis`; `asset()`/`route()` generują prefiks automatycznie, ale w szablonach **żadnych ścieżek zakorzenionych w `/`** (stara apka miała na sztywno `/serwis/` i `BASE_URL` w 6 miejscach).
- `vendor/` powstaje lokalnie (`composer install --no-dev --optimize-autoloader`) i jest wysyłany razem z aplikacją; na serwerze nie ma Composera.
- Migracje: lokalnie generowany SQL (`php artisan schema:dump` / eksport) **albo** jednorazowy, zabezpieczony tokenem route instalatora, wyłączany po użyciu (szczegóły 11.2).
- Cron: jeśli panel hostingu nie oferuje zadań cron, scheduler uruchamiany przez zewnętrzny „pinger” HTTP z tokenem (11.2).
- `.env`, `storage/`, `vendor/`, `bootstrap/cache/`: albo nad `public_html`, albo zablokowane w `.htaccess` (`Require all denied`).

### 5.2 Warstwy i granice

```
app/
├── Domain/                     # reguły biznesowe bez zależności od HTTP
│   ├── Enums/ServiceStatus.php # jedno źródło statusów (z „cancelled”), etykiet, kolorów, dozwolonych przejść
│   ├── Enums/Role.php          # admin / technician / employee + macierz uprawnień
│   ├── ValueObjects/Phone.php  # normalizacja + walidacja PL/E.164
│   ├── ValueObjects/Money.php  # kwoty w groszach, formatowanie pl_PL
│   └── ServiceWorkflow.php     # canTransition(), nextStatus(), requiresReleaseConfirmation()
├── Contracts/                  # interfejsy magazynu: Service, ServiceEvent, ServicePhoto, User repository
├── Actions/                    # jedna klasa = jedna operacja zapisu, transakcyjna (nie zna źródła danych)
│   ├── AcceptService.php
│   ├── UpdateService.php
│   ├── AdvanceServiceStatus.php
│   ├── ReleaseService.php
│   ├── CancelService.php
│   ├── AttachServicePhotos.php
│   └── DeleteServicePhoto.php
├── Infrastructure/
│   ├── Json/                   # implementacje plikowe (flock, rename, meta.json, next_id)
│   └── Eloquent/               # implementacje na modelach + migracjach MySQL
├── Models/                     # Eloquent: Service, ServicePhoto, ServiceEvent, User (tylko tryb mysql)
├── Policies/                   # ServicePolicy, ServicePhotoPolicy, UserPolicy
├── Http/
│   ├── Livewire/               # komponenty ekranów (kolejka, formularz, wyszukiwanie, historia)
│   ├── Controllers/            # tylko: zdjęcia (podgląd), wydruk potwierdzeń, instalator
│   └── Requests/               # walidacja wejścia
├── Events/ + Listeners/        # zapis audytu, czyszczenie cache
└── Support/                    # Search, PhotoStore, Audit, ReceiptRenderer
```

**Zasady (egzekwowane w review):**

1. Blade nie zawiera logiki biznesowej — komponent dostaje gotowe dane.
2. Każda mutacja przechodzi przez `Action` w transakcji; kontroler/Livewire tylko woła akcję i mapuje wynik na komunikat.
3. Statusy i role wyłącznie z enumów; brak stringów `'wydany'`/`'admin'` rozsianych po kodzie.
4. Każda zmiana pola zlecenia generuje `ServiceEvent` (diff pól) — bez wyjątków, także przy wydaniu, anulowaniu i usuwaniu zdjęcia.
5. Polityki zamiast sprawdzeń w widokach; brak danych wrażliwych w odpowiedziach JSON, których klient nie potrzebuje.
6. Wyszukiwanie i sortowanie po stronie serwera; klient nie dostaje całej bazy.
7. Ścieżki i URL-e wyłącznie przez `route()`/`asset()` — aplikacja musi działać w podkatalogu bez zmian w kodzie.

### 5.3 Konwencje

| Obszar | Reguła |
|---|---|
| Język | kod, nazwy tabel/kolumn, commity: **angielski**; UI, komunikaty, dokumentacja użytkownika: **polski** |
| Czas | zapis w UTC, prezentacja w `Europe/Warsaw`, `Carbon` wszędzie; testy DST (zmiana czasu) |
| Pieniądze | `DECIMAL(10,2)` w DB, cast `decimal:2`, formatowanie `number_format(..., 2, ',', ' ') . ' zł' |
| Telefon | wejście dowolne (spacje, `+48`, myślniki) → normalizacja do E.164 (+48…), surowa wartość zachowana, kolumna `phone_normalized` do wyszukiwania; **bez kartoteki klientów** (decyzja D3) |
| Plomba | `trim` + `strtoupper`, dozwolone `[A-Z0-9-/]`, ostrzeżenie o duplikacie (nie blokada — może się zdarzyć ponowne użycie naklejki) |
| Numer zlecenia | wewnętrzny `id` + `reference` (`SRV-2026-000123`) pokazywany klientowi i na wydruku |
| Statusy | enum `pending`, `in_progress`, `awaiting_parts`, `ready_for_pickup`, `released`, `cancelled`; mapowanie starych wartości przy imporcie |
| Role | `admin` (konta użytkowników + wszystko), `employee` (**pełna praca operacyjna: przyjęcie, edycja, status, zdjęcia, wyszukiwanie, wydanie, zmiana ceny i kosztów, anulowanie**), `technician` (**na razie to samo co employee** — prawa zarezerwowane na przyszłe funkcje; macierz w jednym enumie, więc dodanie uprawnienia to jedna linia) |
| ID historyczne | zachowane jako `legacy_id` (do korespondencji i migracji zdjęć), nieużywane w UI |

### 5.4 Wymagania bezpieczeństwa (twarde, wchodzą do AC)

1. Hasła: `bcrypt`/`argon2id`, minimum 12 znaków, brak plaintext w bazie, seedach i logach; wymuszona zmiana hasła startowego.
2. Autoryzacja: każda trasa w grupie `auth`; `ServicePolicy` (view/update/release/cancel/delete-photo) + macierz ról z 5.3.
3. CSRF: globalny middleware; brak wyjątków.
4. Walidacja: `FormRequest` dla każdej mutacji; długości zgodne ze schematem; liczby `min:0`; daty w rozsądnym zakresie.
5. Upload: whitelist MIME **i** rozszerzeń, limit 10 MB/plik i 10 plików, re-encode przez GD (strip EXIF, ochrona przed polyglot), losowa nazwa, zapis poza webrootem, serwowanie przez kontroler z polityką.
6. Hasło urządzenia: `encrypted` cast (szyfrowanie „za darmo”), **widoczne w UI bez maskowania** — decyzja właściciela (wersja testowa); audyt ujawnienia pominięty.
7. **Brak publicznego sprawdzania statusu** (D4): żadnych tras poza `auth`; cały moduł `reklamacja*` usunięty.
8. Nagłówki: HSTS, `X-Content-Type-Options`, `X-Frame-Options: DENY`, `Referrer-Policy`, CSP bez `unsafe-inline` (Livewire wymaga nonce — skonfigurować).
9. Rate limiting: logowanie 5/15 min, mutacje 60/min.
10. Audyt: kto, co, kiedy, z jakiego IP/UA; wpisy nieedytowalne (brak `update`/`delete` w modelu).
11. RODO: **retencja bezterminowa — świadoma decyzja właściciela (D8)**; z tego wynikają dwa obowiązki: minimalizacja (nie zbieramy nic ponad telefon, model, usterkę, hasło urządzenia) i ochrona dostępu (wszystko za `auth`, zdjęcia przez kontroler). Brak komendy anonimizującej; jeśli w przyszłości polityka się zmieni, kolumna `released_at` pozwala ją dodać bez migracji.
12. Kopie: dzienne (panel/phpMyAdmin lub `backup:database`) + archiwum `storage/`, test odtworzenia raz na kwartał.

### 5.5 Wydajność i dostępność

- Cel: p95 odpowiedzi < 300 ms dla listy 200 zleceń; TTI < 2,5 s na 3G; audyt PWA w Chrome DevTools (Lighthouse) ≥ 90.
- Indeksy: `status`, `planned_at`, `released_at`, `phone_normalized`, `seal_number`, `(status, planned_at)`.
- Miniatury zdjęć (400 px) generowane przy uploadzie; oryginał pobierany dopiero w lightboxie.
- Lista: 50 pozycji + „wczytaj więcej”; wyszukiwanie z debounce 300 ms.
- Dostępność: aplikacja musi działać przy braku internetu w trybie tylko-do-odczytu (cache shella + ostatnia lista?), decyzja D5.

### 5.6 Strategia PWA (decyzja D5)

**Wariant rekomendowany (v1): „offline shell + jawna blokada zapisów”.**

- Precache: zbudowane JS/CSS, ikony, `offline.html`, strona logowania (bez danych).
- Network-only: wszystkie trasy zwracające dane klientów (HTML dashboardu, JSON, zdjęcia) — **zero cache**.
- Offline: baner „Brak połączenia — zmiany niemożliwe”, formularze zablokowane, link „ponów”.
- Logout czyści `caches` i `localStorage`.
- Manifest: ikony 96/192/512 + maskable, `start_url`/`scope` z `config('app.url')`, bez `share_target`, skróty do istniejących tras.

**Wariant rozszerzony (backlog B1):** kolejka zapisów offline (IndexedDB + Background Sync) z rozwiązywaniem konfliktów (wersjonowanie rekordu). Koszt: +5–8 dni i realne ryzyko konfliktów przy dwóch urządzeniach — dlatego poza v1.

### 5.7 Wymienny magazyn danych: JSON i MySQL (decyzja D13)

Bazy jeszcze nie ma, więc **domyślnie aplikacja działa bez bazy** — dane trzyma w plikach JSON pod `storage/app/data/`. Gdy baza się pojawi, wystarczy zmienić jedną wartość w konfiguracji. Oba tryby obsługują **ten sam zestaw funkcji** i przechodzą ten sam zestaw testów.

**Plik konfiguracyjny** `config/persistence.php` (wartości można nadpisać w `.env`):

```php
return [
    // 'json'  — dane w plikach, zero konfiguracji, start od razu
    // 'mysql' — własna baza MySQL/MariaDB
    'driver' => env('DATA_STORE', 'json'),

    'json' => [
        'path'    => env('DATA_STORE_PATH', storage_path('app/data')),
        'pretty'  => (bool) env('DATA_STORE_PRETTY', false), // czytelne pliki (większe)
        'lock'    => true,                                    // flock na zapis
        'backup_on_schema_change' => true,                    // kopia .bak przed migracją plików
    ],

    'mysql' => [
        'connection' => env('DB_CONNECTION', 'mysql'),
    ],
];
```

**Struktura plików w trybie `json`** (katalog poza webrootem, prawa `0700`/`0600`):

```
storage/app/data/
├── meta.json             # schema_version, next_id per kolekcja, last_write
├── services.json         # lista zleceń (te same pola co tabela `services`)
├── service_events.json   # audyt
├── service_photos.json   # metadane zdjęć (pliki w storage/app/photos/{id}/)
└── users.json            # konta (hasła hashowane, jak w MySQL)
```

**Zasady trybu JSON (żeby nie zrobić drugiej „starej apki”):**

| Obszar | Reguła |
|---|---|
| Zapis | atomowo: plik tymczasowy → `rename()`, wokół całości `flock` (jeden pisarz naraz); brak częściowych zapisów |
| ID | licznik `next_id` w `meta.json`, inkrementowany pod tym samym lockiem |
| Odczyt | walidacja `schema_version`; przy niezgodności kopia `.bak` i kontrolowany błąd, nie „ciche” nadpisanie |
| Współbieżność | zapisy serializowane; UI pokazuje komunikat „zapis w toku”, konflikt wersji → `409` z odświeżeniem |
| Skala | komfortowo do ~5 000 zleceń (cała lista w pamięci); `store:check` ostrzega powyżej progu i sugeruje przełączenie na MySQL |
| Backup | `storage/app/data` wchodzi do archiwum backupu razem ze zdjęciami; `backup:database` w trybie JSON pakuje katalog danych |
| Bezpieczeństwo | katalog poza `public/`, zablokowany w `.htaccess`, pliki nie są serwowane żadną trasą |

**Przełączanie trybów (jedna komenda + jedna linia):**

```bash
# 1. start bez bazy — nic nie trzeba robić, DATA_STORE=json jest domyślne
php artisan app:install

# 2. gdy pojawi się baza
#    .env: DATA_STORE=mysql + DB_HOST/DB_DATABASE/DB_USERNAME/DB_PASSWORD
php artisan store:check            # sprawdza konfigurację i połączenie
php artisan migrate --force        # tworzy tabele (tylko tryb mysql)
php artisan store:json-to-mysql    # przenosi dane z plików JSON do bazy (idempotentnie)
```

**Warstwa kodu** (żeby wymienność nie przeciekła do widoków):

- `app/Contracts/{ServiceRepository,ServiceEventRepository,ServicePhotoRepository,UserRepository}.php` — małe interfejsy domenowe (find/search/paginate/save/delete, append event, photos, users).
- `app/Infrastructure/Json/*` — implementacje plikowe (jeden czytnik/zapis z lockiem, filtr/sort w pamięci).
- `app/Infrastructure/Eloquent/*` — implementacje na modelach i migracjach MySQL.
- `AppServiceProvider` wiąże interfejsy z implementacją na podstawie `config('persistence.driver')`.
- **Actions i Livewire nie wiedzą, gdzie leżą dane** — nie ma `DB::` ani `Storage::` w komponentach; wyjątkiem jest `PhotoStore`, który zawsze zapisuje pliki na dysku.

**Migracje i seedery działają tylko w trybie `mysql`.** W trybie `json` `app:install` tworzy pliki z `schema_version` i konto admina, a `migrate` jest pomijane (komenda sprawdza driver i wyświetla stosowny komunikat).

**Testy:** krytyczne testy feature są uruchamiane dwukrotnie (Pest dataset `['json','mysql']`), a CI ma dwa joby — jeden bez MySQL, drugi z MySQL. Dzięki temu „działa u mnie na pliku, nie działa na bazie” nie przejdzie.

---

## 6. Docelowy model danych

Zasada: **jedno zlecenie = jeden rekord**. Klient jest utożsamiany **tylko telefonem** (decyzja właściciela: bez imion i bez kartoteki `customers`) — telefon trzymamy w zleceniu plus znormalizowaną kopię do wyszukiwania. Bez tabeli `devices` (B2). **Ten sam model obowiązuje w obu magazynach:** w MySQL to tabele, w trybie JSON — dokumenty o identycznych polach (`services.json` itd.), więc przełączenie nie zmienia kodu akcji ani widoków.

### 6.1 Tabele

**`users`** — pracownicy

| Kolumna | Typ | Uwagi |
|---|---|---|
| `id` | bigint PK | |
| `name` | varchar(100) | imię/nazwisko lub pseudonim |
| `login` | varchar(50) UNIQUE | login do logowania |
| `password` | varchar(255) | hash |
| `role` | enum(`admin`,`technician`,`employee`) | macierz uprawnień w `Role` |
| `is_active` | boolean | blokada konta bez usuwania |
| `last_login_at` | timestamp null | |
| `timestamps` | | |

**`services`** — zlecenie serwisowe

| Kolumna | Typ | Uwagi |
|---|---|---|
| `id` | bigint PK | |
| `legacy_id` | int null UNIQUE | mapowanie ze starej bazy |
| `reference` | varchar(20) UNIQUE | `SRV-2026-000123` |
| `phone` | varchar(30) | telefon jak wpisany |
| `phone_normalized` | varchar(20) | E.164 do wyszukiwania (indeks) |
| `device_model` | varchar(100) | |
| `serial_number` | varchar(100) null | |
| `issue` | text | |
| `device_password` | text null | **szyfrowane** (`encrypted` cast), widoczne w UI |
| `notes` | text null | uwagi wewnętrzne |
| `is_express` | boolean default false | |
| `planned_at` | datetime | termin obiecany |
| `price` | decimal(10,2) default 0 | |
| `cost` | decimal(10,2) default 0 | |
| `seal_number` | varchar(50) null | znormalizowany |
| `status` | varchar(30) default `pending` | enum w kodzie |
| `accepted_by` | bigint FK users | |
| `accepted_at` | datetime | = data przyjęcia (dziś `created_at`) |
| `confirmation_issued_at` | datetime null | **wydano potwierdzenie przyjęcia** (checkbox przy przyjęciu / ponowny druk) |
| `released_at` | datetime null | |
| `released_by` | bigint FK users null | |
| `cancelled_at` | datetime null | |
| `cancelled_by` | bigint FK users null | |
| `cancellation_reason` | varchar(255) null | |
| `version` | int default 0 | optimistic locking (Livewire) |
| `timestamps` | | |
| `deleted_at` | timestamp null | soft delete tylko dla admina (z audytem) |

Indeksy: `status`, `planned_at`, `released_at`, `seal_number`, `phone_normalized`, `(status, planned_at)`, `(released_at)`.

**`service_photos`**

| Kolumna | Typ | Uwagi |
|---|---|---|
| `id` | bigint PK | |
| `service_id` | bigint FK CASCADE | |
| `disk` | varchar(20) | `local` (prywatny) |
| `path` | varchar(255) | losowa nazwa |
| `thumb_path` | varchar(255) | miniatura 400 px |
| `mime` | varchar(50) | |
| `size` | int | bajty |
| `width`,`height` | int | |
| `checksum` | char(64) | SHA-256, do wykrywania duplikatów |
| `uploaded_by` | bigint FK users | |
| `created_at` | | |

**`service_events`** — audyt (zastępuje `service_history`)

| Kolumna | Typ | Uwagi |
|---|---|---|
| `id` | bigint PK | |
| `service_id` | bigint FK CASCADE | |
| `type` | varchar(30) | `accepted`, `updated`, `status_changed`, `released`, `photo_added`, `photo_deleted`, `device_password_revealed`, `cancelled` |
| `actor_id` | bigint FK users null | null = system |
| `from_status`,`to_status` | varchar(30) null | |
| `changes` | json null | `{pole: {from, to}}` |
| `description` | text null | |
| `ip`,`user_agent` | varchar | kontekst |
| `created_at` | timestamp | brak `updated_at` — wpis niezmienny |

**`login_attempts`** (opcjonalnie, jeśli nie używamy cache do throttlingu) — `login`, `ip`, `successful`, `created_at`.

### 6.2 Mapowanie stare → nowe

| Stara kolumna | Nowa | Reguła migracji |
|---|---|---|
| `laptopservice.Id` | `services.id` | zachowane 1:1 (ułatwia weryfikację); dodatkowo `legacy_id` |
| `nr_tel` | `phone` + `phone_normalized` | normalizacja: usuń spacje/myślniki/nawiasy, `0048`→`+48`, 9 cyfr→`+48…`; `phone` zachowuje zapis oryginalny; brak możliwości normalizacji → `phone_normalized = NULL` + flaga do ręcznego przeglądu |
| `model_laptopa` | `device_model` | trim, obcięcie do 100 znaków |
| `usterka` | `issue` | bez zmian |
| `haslo` | `device_password` | `Crypt::encryptString()`, pusty → null |
| `serial_number` | `serial_number` | trim, pusty → null |
| `express` | `is_express` | 0/1 → bool |
| `potwierdzenie_odbioru` | `confirmation_issued_at` **(D6)** | 1 → `accepted_at` (przyjmujemy, że potwierdzenie wydano), 0 → null; każda wartość trafia do raportu migracji z ostrzeżeniem, że stare znaczenie pola było niejasne |
| `uwagi` | `notes` | bez zmian |
| `planowany_czas_wydania` | `planned_at` | interpretacja jako `Europe/Warsaw` → UTC; NULL → `accepted_at + 3 dni` + flaga w raporcie |
| `cena`, `koszty` | `price`, `cost` | `DECIMAL`, zaokrąglenie do groszy |
| `plomba` | `seal_number` | trim + upper; duplikaty do raportu |
| `status` | `status` | `oczekujacy`→`pending`, `w_trakcie`→`in_progress`, `czeka_na_czesci`→`awaiting_parts`, `do_wydania`→`ready_for_pickup`, `wydany`→`released`; nieznany → `pending` + raport |
| `godzina_wydania` | `released_at` | NULL przy `status=wydany` → ustaw `accepted_at` + flaga w raporcie (dziś `load_test_data` takie rekordy tworzy) |
| `przyjety_przez` | `accepted_by` | mapowanie po `users.login`; brak konta → konto techniczne `legacy-import`, oryginalna nazwa zapisana w opisie zdarzenia `accepted` |
| `created_at` | `accepted_at` | NULL → data z `Id`? nie da się wiarygodnie → `accepted_at = updated_at` i flaga |
| `users.Haslo` | `users.password` | **hashujemy plaintext przy imporcie** (`Hash::make`), wymuszona zmiana przy pierwszym logowaniu |
| `laptop_images` | `service_photos` | ścieżka → plik; kopiowanie do storage prywatnego, wyliczenie `checksum`, miniatur; brak pliku → raport „osierocone” |
| `service_history` | `service_events` | `action_type` mapowane 1:1; `previous_values`/`new_values` → `changes`; `performed_by` → `actor_id` (mapowanie po loginie) |

---

## 7. Macierz parzystości i kryteria akceptacji

Legenda testów: **U** unit, **F** feature (HTTP/akcja), **E** e2e (Laravel Dusk), **A** axe/a11y, **M** migracja danych.

### 7.1 Uwierzytelnianie

| F | Ekran/endpoint | Kryterium akceptacji | Test |
|---|---|---|---|
| F-01 | `GET/POST /login` | poprawny login+hasło → redirect na kolejkę; błędny → jeden generyczny komunikat, brak różnicy między „nie ma konta” a „złe hasło”; 6. próba w 15 min → 429; **`?error=<img onerror>` nie wykonuje JS** (tekst escapowany, bez `innerHTML`) | F,E |
| F-02 | `POST /logout` | CSRF wymagany; sesja unieważniona, ciasteczka wyczyszczone, cache PWA wyczyszczony | F,E |
| F-03 | middleware `auth` | anonim na każdej trasie (w tym `/services/*`, `/photos/*`, `/print/*`) → 302/403, zero danych w odpowiedzi; **nie istnieje żadna trasa publiczna poza `/login`** | F |
| F-04 | — | po logowaniu nowe `session_id`, stare nieważne | F |
| F-05 | wszystkie mutacje | POST bez tokenu → 419; token z innej sesji → 419 | F |
| F-06 | panel `/admin/users` | admin tworzy/edytuje/dezaktywuje konto i nadaje jedną z 3 ról; technik i pracownik → 403; dezaktywacja blokuje logowanie; **macierz uprawnień pokryta testem dla każdej roli i każdej akcji** — w szczególności: **pracownik może wydać laptop i zmienić cenę/koszt**, technik ma dziś ten sam zakres (rezerwa na przyszłe funkcje) | F,E |

### 7.2 Przyjęcie zlecenia

| F | Ekran/endpoint | Kryterium akceptacji | Test |
|---|---|---|---|
| F-07 | formularz przyjęcia | telefon wymagany; `505 123 456`, `+48 505 123 456`, `0048505123456` → ten sam `+48505123456`; śmieci → błąd walidacji | U,F |
| F-08 | jw. | model wymagany, ≤100 znaków, trim | F |
| F-09 | jw. | usterka wymagana, ≤5000 znaków, zachowane znaki nowej linii | F |
| F-10 | jw. | hasło opcjonalne, szyfrowane w DB (test: wartość w bazie ≠ plaintext), puste → null | F,U |
| F-11 | jw. | S/N opcjonalny, ≤100 | F |
| F-12 | jw. | uwagi opcjonalne, ≤5000 | F |
| F-13 | jw. | termin wymagany, format akceptowany z UI, domyślnie jutro 15:00 czasu lokalnego (bez przesunięcia UTC), nie wcześniej niż dziś − 1 dzień | U,F,E |
| F-14 | jw. | cena ≥ 0, 2 miejsca dziesiętne, `12,50` i `12.50` → `12.50` | U,F |
| F-15 | jw. | koszt ≥ 0, obecny na desktopie i mobile | F,E |
| F-16 | jw. | express → pozycja na górze kolejki, wyróżniona wizualnie | F,E |
| F-17 | jw. | checkbox „wydrukuj potwierdzenie przyjęcia” (zaznaczony → po zapisie otwiera się wydruk i ustawia `confirmation_issued_at`); karta zlecenia pokazuje **„🖨 potwierdzenie wydane”**; rezygnacja z checkboxa nie blokuje przyjęcia | F,E |
| F-18 | jw. | 1–10 zdjęć; JPEG/PNG/WebP/GIF; ≤10 MB; EXIF usunięty; miniatura powstała; plik poza webrootem; 11. plik → błąd | F,U |
| F-19 | — | status `pending`, `accepted_by` = zalogowany, `accepted_at` = teraz | F |
| F-20 | — | zdarzenie `accepted` z pełnym snapshotem pól | F |

### 7.3 Kolejka i praca

| F | Ekran/endpoint | Kryterium akceptacji | Test |
|---|---|---|---|
| F-21 | `GET /` (kolejka) | widoczne wszystkie poza `released` + 10 ostatnich wydanych **na końcu listy (express nie wynosi wydanych na górę)**; licznik na status; paginacja 50 | F,E |
| F-22 | jw. | kolejność: express → `in_progress` → `pending` → `awaiting_parts` → `ready_for_pickup` → `released`, potem termin rosnąco; identyczna po każdym wyszukaniu | U,F,E |
| F-23 | karta | model, badge statusu (kolor z enuma), telefon, termin, skrót usterki, cena; hasło ukryte z przyciskiem „pokaż” | E |
| F-24 | `POST /services/{id}/advance` | przycisk prowadzi do następnego dozwolonego statusu; niedozwolone przejście → 422; **dla `released`/`cancelled` nie ma żadnego przycisku**; po akcji toast + odświeżenie wiersza bez przeładowania | F,E |
| F-25 | karta `ready_for_pickup` | przycisk „Wydaj” otwiera formularz wydania z danymi zlecenia | E |
| F-26 | `GET /released` | lista wydanych, filtr zakresu dat, wyszukiwanie, grupowanie po dniu, paginacja | F,E |

### 7.4 Wyszukiwanie

| F | Ekran/endpoint | Kryterium akceptacji | Test |
|---|---|---|---|
| F-27 | pole w nagłówku | ≥3 znaki → wyniki po telefonie/plombie/modelu/S-N/usterce; 2 znaki → brak zapytania; wynik obejmuje też wydane | F,E |
| F-28 | `GET /search?phone=` | znajduje po fragmencie i po formacie znormalizowanym; brak wyników → empty state z podpowiedzią | F,E |
| F-29 | `GET /search?seal=` | jw. dla plomby (case-insensitive) | F |
| F-30 | — | **nie dotyczy v1** (D4: klient nie sprawdza statusu); test regresyjny: trasa `/lookup` nie istnieje (404) i żaden widok nie linkuje do publicznego sprawdzania | F |
| F-31 | — | **nie dotyczy v1** (jak wyżej); moduł `reklamacja*` usunięty razem z plikami | — |
| F-32 | filtry statusu | wielokrotny wybór statusów filtruje listę serwerowo, stan w URL (można udostępnić link) | F,E |

### 7.5 Edycja i historia

| F | Ekran/endpoint | Kryterium akceptacji | Test |
|---|---|---|---|
| F-33 | `POST /services/{id}/status` | tylko dozwolone przejścia; zdarzenie `status_changed` z `from`/`to`; `released` tylko przez akcję wydania | U,F |
| F-34 | `GET/PUT /services/{id}/edit` | wszystkie pola edytowalne; **zapis bez zmian nie zmienia żadnego pola, w szczególności terminu, kosztu i flag**; walidacja jak przy przyjęciu | F,E |
| F-35 | formularz edycji | zmiana statusu w formularzu tworzy `status_changed` (nie tylko `updated`); **nie można ustawić `released` przez edycję** — tylko przez akcję wydania (dziś `edytowanie.php` pozwala obejść proces wydania) | F |
| F-36 | jw. | plomba normalizowana; duplikat → ostrzeżenie, ale zapis możliwy | F |
| F-37 | galeria w edycji | usunięcie zdjęcia: CSRF, polityka, plik + miniatura usunięte, zdarzenie `photo_deleted`; zdjęcie innego zlecenia → 403 | F,E |
| F-38 | panel historii | lista zdarzeń malejąco, autor, czas lokalny, opis, diff pól z **polskimi nazwami**; paginacja; dostępna też na telefonie | F,E |
| **F-56** | `POST /services/{id}/cancel` | anulowanie tylko przez technika/admina; wymaga powodu; `status=cancelled`, data i autor; zlecenie znika z aktywnej kolejki, widoczne w archiwum; zdarzenie `cancelled`; brak możliwości anulowania zlecenia już wydanego | F,E |

### 7.6 Wydanie

| F | Ekran/endpoint | Kryterium akceptacji | Test |
|---|---|---|---|
| F-39 | `GET/POST /services/{id}/release` | dwustopniowe potwierdzenie; dane tylko-do-odczytu; edytowalne: cena, **koszt (pracownik i technik mają prawo)**, uwagi, plomba, hasło, S/N, **data wydania (domyślnie „teraz”, walidacja „nie z przyszłości”)**; checkbox „drukuj protokół wydania” | F,E |
| F-40 | — | po wydaniu `status=released`, `released_at` z formularza (domyślnie teraz), `released_by` = zalogowany | F |
| F-41 | — | zdarzenie `released` z `from`/`to` | F |
| F-42 | ten sam ekran na mobile | wydanie z telefonu kończy się sukcesem (test regresyjny D-10) i jest dostępne dla pracownika oraz technika; konto bez sesji → przekierowanie na login, nie pusty ekran | E |

### 7.7 PWA i UI

| F | Ekran/endpoint | Kryterium akceptacji | Test |
|---|---|---|---|
| F-43 | `/manifest.webmanifest` | waliduje się; ikony 96/192/512 + maskable istnieją i zwracają 200; `start_url` zgodny z `APP_URL`; brak martwych skrótów | F,E |
| F-44 | offline | po odcięciu sieci: aplikacja pokazuje stan offline, blokuje zapisy, nie pokazuje danych klientów z cache; po powrocie sieci wraca normalnie | E |
| F-45 | push | **nie w v1** (brak backendu push w starej wersji) | — |
| F-46 | sync | **nie w v1** (decyzja D5) | — |
| F-47 | galeria | klik w miniaturę → dialog z powiększeniem, strzałki, ESC, swipe; fokus uwięziony w dialogu; brak podwójnych listenerów po odświeżeniu | E,A |
| F-48 | layout | jeden UI: 390 px → dolna nawigacja, 1366 px → sidebar; wszystkie funkcje dostępne na obu | E,A |
| F-49 | — | brak wykrywania urządzenia (responsywność zamiast dwóch wersji) | — |
| F-50 | audyt | każda zmiana ma wpis: kto, co, kiedy; brak duplikatów zdarzeń | F |
| F-51 | logi | `storage/logs/laravel-YYYY-MM-DD.log`, poziom `info`, brak telefonów/haseł w logach | F + przegląd |
| F-52 | `php artisan app:install` / `GET /__install?token=` | tworzy schemat, konto admina z losowym hasłem wyświetlonym raz, kończy się kodem 0; drugie uruchomienie bezpieczne; **bez tokenu w `.env` route zwraca 404**, a po instalacji token jest pusty | F |
| F-53 | `php artisan db:seed --class=DemoSeeder` | tylko poza produkcją (guard `app()->isProduction()` → abort), 30 zleceń z realistycznymi danymi | F |
| F-54 | `php artisan photos:verify` | raport: pliki bez rekordu, rekordy bez pliku, błędne ścieżki; `--fix` przenosi do storage | F |

### 7.8 Drukowanie (nowe — F-55)

| F | Ekran/endpoint | Kryterium akceptacji | Test |
|---|---|---|---|
| F-55 | `GET /services/{id}/print/acceptance`, `GET /services/{id}/print/release` | widok A4 (`@media print`, bez nawigacji i przycisków) z: numerem `reference`, modelem, S/N, opisem usterki, telefonem, terminem, ceną (przy wydaniu), datą i pracownikiem; automatyczne `window.print()` po otwarciu; „drukuj ponownie” na karcie zlecenia ustawia/odświeża `confirmation_issued_at`; **strona wymaga sesji** i renderuje się poprawnie w trybie druku (test Dusk z `printToPDF` lub asercja na strukturę) | F,E |

### 7.9 Magazyn danych (nowe — F-57)

| F | Ekran/endpoint | Kryterium akceptacji | Test |
|---|---|---|---|
| F-57 | `config/persistence.php` + `DATA_STORE` | `DATA_STORE=json` (domyślnie): aplikacja startuje **bez bazy**, `app:install` tworzy pliki w `storage/app/data`, wszystkie funkcje działają; `DATA_STORE=mysql`: te same funkcje na bazie; `artisan store:check` pokazuje aktywny tryb i ewentualne braki konfiguracji; `store:json-to-mysql` przenosi dane idempotentnie (drugie uruchomienie nie duplikuje); pliki JSON są poza webrootem i niedostępne przez HTTP | F,E (oba tryby) |

---

## 8. Plan zadań

Zasady: jedno zadanie = jedna weryfikowalna zmiana; zadanie kończy się zielonymi bramkami (`test`, `pint`, `phpstan`, `dusk`); kolejność faz jest zależnościowa, zadania w fazie mogą iść równolegle, jeśli nie dotykają tych samych plików.

Suma szacunków: **~42 dni roboczych** jednego doświadczonego developera (kalendarzowo: 8–9 tygodni przy pełnym etacie, 14 tygodni przy 3 dniach w tygodniu).

### Faza 0 — Decyzje i przygotowanie (3 dni)

| ID | Zadanie | Deliverable / pliki | Kryterium akceptacji | Weryfikacja |
|---|---|---|---|---|
| T0.1 | Domknięcie decyzji D1–D13 (sekcja 12) | `docs/aegis/decisions/0001-stack.md` … po jednym ADR na decyzję | każda decyzja ma status „zaakceptowana” z datą (właściciel odpowiedział 2026-11-02) | przegląd z właścicielem |
| T0.2 | Audyt produkcji i **możliwości hostingu** | `docs/aegis/legacy/prod-inventory.md` + `hosting-check.md` (zrzut bazy — **gdy będzie**, na razie brak danych) | sprawdzone: wersja PHP, MySQL, **czy można pisać nad `public_html`**, czy jest cron, czy działa `.htaccess`, sposób wgrywania plików, limit `upload_max_filesize`; zrzut starej bazy **opcjonalnie** | test wgrania pliku testowego pod `/serwis/` + `php artisan about` |
| T0.3 | Kopia bezpieczeństwa starej wersji | `backups/2026-11-02-app.zip` + (jeśli jest) `db.sql` + SHA-256 w `backups/CHECKSUMS.txt` | kopia odtwarzalna na czystym środowisku; brak bazy nie blokuje startu | restore na staging |
| T0.4 | Repozytorium i szkielet | katalog `app/` (Laravel), `.gitignore` (`.env`, `storage/`, `vendor/`, `old-app/`, `_runtime/`, `storage/app/data`), `config/persistence.php`, `README.md` | `git log` czysty, brak sekretów; **brak `package.json`**; config zawiera oba tryby magazynu z komentarzami | `git secrets` + `git ls-files` + `php artisan store:check` |
| T0.5 | CI i bramki jakości | `.github/workflows/ci.yml`: dwa joby — `json` (bez MySQL) i `mysql`, oba: `composer install`, Pint, PHPStan, Pest | CI zielone na pustym projekcie, bez kroku `npm` | uruchomienie na PR |
| T0.6 | Środowiska | `.env.example` z `DATA_STORE=json` (domyślnie) i zakomentowanym blokiem MySQL, konfiguracja local i staging | `php artisan about` + `store:check` pokazują aktywny tryb `json` bez bazy; po zmianie na `mysql` — połączenie działa | `artisan migrate:status` (tylko mysql) |

**Bramka F0:** decyzje podpisane, kopia zapasowa zweryfikowana, CI zielone w obu trybach.

### Faza 1 — Fundament: użytkownicy, logowanie, layout (4 dni)

| ID | Zadanie | Pliki | Kryterium akceptacji | Weryfikacja |
|---|---|---|---|---|
| T1.1 | Repozytorium użytkowników + oba magazyny | `app/Contracts/UserRepository.php`, `app/Infrastructure/Json/JsonUserRepository.php`, `app/Infrastructure/Eloquent/EloquentUserRepository.php`, `app/Domain/Enums/Role.php` | konto działa w obu trybach; `role`, `is_active`, `last_login_at`; hasło hashowane | test F w obu trybach + `Hash::check` |
| T1.2 | Instalator aplikacji (CLI + web dla hostingu) | `app/Console/Commands/InstallApp.php`, `app/Console/Commands/StoreCheck.php`, `app/Http/Controllers/WebInstallController.php`, `database/seeders/AdminSeeder.php` | `artisan app:install` (JSON: tworzy pliki danych; MySQL: migruje) lub `GET /__install?token=…`; konto admina z losowym hasłem wyświetlonym raz; bez tokenu → 404; powtórka nie psuje danych | test F (F-52) w obu trybach |
| T1.3 | Logowanie/wylogowanie | `app/Http/Controllers/AuthController.php` (lub Fortify), `resources/views/auth/login.blade.php`, `routes/web.php` | generyczny komunikat błędu, throttle 5/15 min, CSRF, POST logout, rotacja sesji | testy F (F-01…F-05) + E |
| T1.4 | Layout i system komponentów | `resources/views/layouts/app.blade.php`, `resources/views/components/{status-badge,money,phone-input,empty-state,toast}.blade.php`, `public/css/bootstrap.min.css`, `public/js/bootstrap.bundle.min.js`, `public/css/app.css` (wersje plików skopiowanych z pakietu Bootstrap — bez npm) | jeden layout: sidebar ≥1024 px, dolna nawigacja <1024 px; brak poziomego scrolla na 390 px; **żadnego `<style>` blokowego w widokach** | E (2 rozmiary okna) + axe |
| T1.5 | Panel użytkowników (admin) | `app/Livewire/Admin/UserList.php`, `UserForm.php`, `app/Policies/UserPolicy.php`, `app/Domain/Enums/Role.php` | admin CRUD kont i nadaje jedną z 3 ról; technik i pracownik → 403; dezaktywacja blokuje logowanie; macierz uprawnień przetestowana dla każdej roli | testy F (F-06) |

**Bramka F1:** logowanie działa na staging, testy auth zielone, brak danych wrażliwych w logach.

### Faza 2 — Domena zleceń i warstwa magazynu JSON/MySQL (8 dni)

| ID | Zadanie | Pliki | Kryterium akceptacji | Weryfikacja |
|---|---|---|---|---|
| T2.1 | Migracje schematu (tryb mysql) | `database/migrations/*_create_{users,services,service_photos,service_events}_tables.php` | schemat zgodny z sekcją 6.1 (bez tabeli `customers`, ze `confirmation_issued_at`, `cancelled_*`), indeksy i FK na miejscu, `migrate:fresh` przechodzi | `artisan migrate:fresh --seed` |
| T2.2 | Enumy i value objects | `app/Domain/Enums/ServiceStatus.php`, `app/Domain/ValueObjects/{Phone,Money}.php`, `app/Domain/ServiceWorkflow.php` | statusy z etykietami/kolorami/przejściami; telefon normalizuje 4 formaty; kwoty w groszach | testy U |
| T2.3 | **Kontrakty magazynu + implementacja JSON** | `app/Contracts/{Service,ServiceEvent,ServicePhoto,User}Repository.php`, `app/Infrastructure/Json/*` (lock, `rename()`, `meta.json`/`next_id`, walidacja `schema_version`) | ten sam interfejs obsługuje JSON; zapis atomowy; dwa równoległe zapisy nie gubią danych (test współbieżności); uszkodzony plik → kopia `.bak` + kontrolowany błąd | testy U/F + test współbieżności |
| T2.4 | Implementacja MySQL + wybór drivera | `app/Infrastructure/Eloquent/*`, `app/Models/*`, `database/factories/*`, wiązanie w `AppServiceProvider`, `config/persistence.php` | `DATA_STORE=json` / `DATA_STORE=mysql` przełącza magazyn bez zmian w akcjach i widokach; casty: `encrypted`, `decimal:2`, `datetime` | testy F w obu trybach |
| T2.5 | Akcje zapisu | `app/Actions/{AcceptService,UpdateService,AdvanceServiceStatus,ReleaseService,CancelService}.php` | każda transakcyjna (JSON: zapis pod lockiem; MySQL: `DB::transaction`), każda pisze `ServiceEvent`, walidacja przejść, brak zerowania pól przy partial update, anulowanie wymaga powodu | testy F (F-19, F-20, F-33, F-40, F-41, F-56) |
| T2.6 | **Migracja JSON → MySQL** | `app/Console/Commands/JsonToMysql.php` | przenosi zlecenia, zdjęcia (metadane + pliki), historię i użytkowników; idempotentnie (drugie uruchomienie nie duplikuje); raport przeniesionych rekordów | test F (F-57) na fiksturze |
| T2.7 | Importer danych legacy (**opcjonalnie, gdy będzie zrzut**) | `app/Console/Commands/ImportLegacy.php`, `app/Support/Legacy/*`, `tests/Fixtures/legacy-dump.sql` | `--dry-run` daje raport CSV; import idempotentny; nie blokuje startu bez danych | test M na fiksturze |
| T2.8 | Raport zgodności importu (gdy jest zrzut) | `app/Console/Commands/VerifyImport.php` | porównuje liczność i sumy; różnica > 0 → kod wyjścia ≠ 0 | uruchomienie na staging |
| T2.9 | **Testy regresyjne z audytu runtime (oba tryby)** | `tests/Feature/Regression/*` | zamknięte testem: brak dryfu terminu (D-44/D-45), koszt niezerowany (D-47), flagi niezmieniane (D-48), cena `199,99 → 199.99` (D-51), normalizacja telefonu (D-20), brak akcji dla wydanych (D-46), brak XSS w `?error=` (D-04) — w trybie `json` i `mysql` | `php artisan test --filter=Regression` |

**Bramka F2:** aplikacja zapisuje i czyta dane w obu trybach, przełączenie drivera nie wymaga zmian w kodzie, testy domeny zielone; import z JSON do MySQL idempotentny.

### Faza 3 — Ekrany pracy (6 dni)

| ID | Zadanie | Pliki | Kryterium akceptacji | Weryfikacja |
|---|---|---|---|---|
| T3.1 | Kolejka z sortowaniem i filtrami | `app/Livewire/ServiceQueue.php`, `resources/views/livewire/service-queue.blade.php` | sortowanie i kolejność zgodne z F-22; filtry statusu w URL; paginacja 50 | testy F + E (F-21, F-22, F-32) |
| T3.2 | Formularz przyjęcia | `app/Livewire/ServiceForm.php` (create), `resources/views/livewire/service-form.blade.php` | wszystkie pola z F-07…F-18; walidacja inline; upload 1–10 zdjęć; toast po zapisie | testy F + E |
| T3.3 | Karta i szybkie akcje | komponent `service-card.blade.php`, `POST /services/{id}/advance` | „następny etap” bez przeładowania; niedozwolone przejście → komunikat; hasło ukryte | testy F + E (F-23, F-24) |
| T3.4 | Wyszukiwanie | `app/Support/ServiceSearch.php`, `app/Livewire/ServiceSearch.php` | jeden mechanizm dla nagłówka i strony wyników; ≥3 znaki; pełny zakres; empty state | testy F + E (F-27…F-29) |
| T3.5 | Edycja zlecenia | `app/Livewire/ServiceForm.php` (edit), `app/Policies/ServicePolicy.php` | pełna edycja, partial update (koszty niezerowane), walidacja jak przy przyjęciu | testy F + E (F-34…F-36) |
| T3.6 | Wydanie | `app/Livewire/ReleaseServiceForm.php` | dwustopniowe potwierdzenie, dane readonly, data wydania z formularza, sukces na mobile i desktopie | testy F + E (F-39…F-42) |
| T3.7 | Archiwum wydanych | `app/Livewire/ReleasedServices.php` | filtr dat, wyszukiwanie, grupowanie po dniu, paginacja | testy F + E (F-26) |
| T3.8 | Scenariusze e2e 1–6 | `tests/Browser/*.php` (Dusk) | pełny cykl: przyjęcie → status → edycja → wydanie, w oknach 390×844 i 1366×768 | `php artisan dusk` |

**Bramka F3:** pracownik wykonuje cały cykl dnia bez sięgania do bazy; e2e zielone.

### Faza 4 — Zdjęcia (3 dni)

| ID | Zadanie | Pliki | Kryterium akceptacji | Weryfikacja |
|---|---|---|---|---|
| T4.1 | Przechowywanie i serwowanie | `app/Support/PhotoStore.php`, `app/Http/Controllers/ServicePhotoController.php`, dysk `local` | plik poza webrootem; dostęp tylko przez politykę; `Content-Type` z DB; nagłówek `Cache-Control: private` | testy F (403/200) |
| T4.2 | Upload z przetwarzaniem | `app/Actions/AttachServicePhotos.php` | MIME + rozszerzenie, ≤10 MB, re-encode (strip EXIF), miniatura 400 px, SHA-256, raport odrzuconych plików | testy U/F (polyglot, spoofing) |
| T4.3 | Galeria i lightbox | `resources/views/components/photo-gallery.blade.php`, `resources/js/lightbox.js` | galeria z usuwaniem (potwierdzenie), dialog z focus trap, ESC/strzałki/swipe, brak kumulacji listenerów | E + axe (F-37, F-47) |
| T4.4 | Narzędzie weryfikacji | `app/Console/Commands/VerifyPhotos.php` | raport: pliki bez rekordu, rekordy bez pliku, złe ścieżki; `--fix` przenosi/naprawia | test F (F-54) |

### Faza 5 — Historia, drukowanie, anulowanie (3 dni)

| ID | Zadanie | Pliki | Kryterium akceptacji | Weryfikacja |
|---|---|---|---|---|
| T5.1 | Panel historii | `app/Livewire/ServiceHistory.php` | wszystkie typy zdarzeń, diff pól z polskimi etykietami, autor, czas lokalny, paginacja, dostępne na mobile | testy F + E (F-38) |
| T5.2 | Hasło urządzenia | komponent `device-password.blade.php` | wartość widoczna w szczegółach (decyzja właściciela), szyfrowana w bazie, bez maskowania i bez audytu ujawnienia | testy F (D-09, decyzja) |
| T5.3 | **Drukowanie potwierdzeń** | `app/Support/ReceiptRenderer.php`, `resources/views/print/{acceptance,release}.blade.php`, `app/Http/Controllers/PrintController.php` | checkbox „wydrukuj potwierdzenie przyjęcia” przy przyjęciu → po zapisie otwiera się A4 z `window.print()`; „drukuj ponownie” ustawia `confirmation_issued_at`; wydruk wydania z ceną i datą; brak nawigacji w wydruku | testy F + E (F-55) |
| T5.4 | **Anulowanie zlecenia** | `app/Livewire/CancelServiceDialog.php`, akcja `CancelService` | technik/admin podaje powód → `cancelled`, zniknięcie z kolejki, wpis w historii, blokada dla zlecenia wydanego | testy F + E (F-56) |

### Faza 6 — PWA (3 dni)

| ID | Zadanie | Pliki | Kryterium akceptacji | Weryfikacja |
|---|---|---|---|---|
| T6.1 | Manifest i ikony | `public/manifest.webmanifest`, `app/Console/Commands/GenerateIcons.php` (GD: 96/192/192-maskable/512/512-maskable), `public/icons/*`, meta w layoucie | `php artisan icons:generate` tworzy komplet ikon z jednego źródła (PNG/SVG→PNG); manifest waliduje się; wszystkie ikony 200; brak martwych skrótów | test F + `php artisan icons:check` |
| T6.2 | Service worker | `public/service-worker.js` (ręcznie, bez buildu), `public/js/pwa.js`, `app/Support/AssetVersion.php` | precache tylko istniejących assetów (`bootstrap.min.css`, `app.css`, `lightbox.js`, `offline.html`…); wersja cache z `config('app.asset_version')`; **network-only dla HTML/JSON/zdjęć**; baner aktualizacji bez auto-reload | E (offline) + test cache |
| T6.3 | Zachowanie offline | `resources/views/components/offline-banner.blade.php`, `public/js/pwa.js` | offline: baner, formularze zablokowane, brak danych klientów z cache; logout czyści `caches` | E (F-44) |
| T6.4 | Test braku PII w cache | `tests/Browser/PwaCacheTest.php` (Dusk + `executeScript`) | po zalogowaniu i wylogowaniu w Cache Storage nie ma HTML z telefonem/hasłem ani JSON zlecenia | E |

### Faza 7 — Bezpieczeństwo, logi, wydajność (2,5 dnia)

| ID | Zadanie | Pliki | Kryterium akceptacji | Weryfikacja |
|---|---|---|---|---|
| T7.1 | Nagłówki i CSP | `app/Http/Middleware/SecurityHeaders.php` | HSTS, `X-Frame-Options: DENY`, `nosniff`, CSP z nonce dla Livewire, brak błędów CSP w konsoli | E (konsola bez błędów) |
| T7.2 | Logi i redakcja | `config/logging.php`, `app/Support/LogRedactor.php` | kanał daily, poziom `info`; telefon/hasło nigdy w logach; rotacja 14 dni | test F (asercja na pliku logu) |
| T7.3 | Rate limiting i audyt dostępu | `app/Providers/RouteServiceProvider.php` (limity), `service_events.ip/user_agent` | limity z sekcji 5.4; każda mutacja ma IP/UA w audycie | testy F |
| T7.4 | Wydajność | indeksy, eager loading, `config:cache`/`route:cache`, miniatury | p95 < 300 ms dla 200 zleceń; brak N+1 (Larastan/Debugbar) | test obciążeniowy (`ab`/k6 przez HTTP) + Larastan |
| T7.5 | **Kopie zapasowe i odzyskiwanie** (retencja bezterminowa — D8) | `app/Console/Commands/BackupDatabase.php` (mysqldump lub PHP-owy eksport), `docs/aegis/runbook-backup.md` | komenda tworzy spójny zrzut bazy + archiwum `storage/` do pobrania z panelu; procedura backup/restore opisana i **przetestowana (przywrócenie na staging)**; brak anonimizacji (decyzja właściciela) | test F + próbny restore |

### Faza 8 — Testy kompleksowe (oba magazyny), a11y, UAT (3 dni)

| ID | Zadanie | Deliverable | Kryterium akceptacji | Weryfikacja |
|---|---|---|---|---|
| T8.1 | Domknięcie pokrycia | raport `docs/aegis/verification/parity-report.md` | każde F-xx ma wskazany test lub jawny wpis „nie dotyczy” z powodem; raport pokazuje wynik w **obu trybach magazynu** | przegląd macierzy 1:1 |
| T8.2 | E2E „dzień warsztatu” | `tests/Browser/FullDayTest.php` (10 kroków) | przechodzi w oknie 1366×768 i 390×844 (Dusk `resize`), na `DATA_STORE=json` | CI |
| T8.3 | Dostępność | `tests/Browser/A11yTest.php` + `tests/Browser/axe.min.js` (plik wstrzykiwany przez `executeScript`) | axe: 0 błędów krytycznych na 5 widokach; pełna obsługa klawiaturą | CI |
| T8.4 | Import starej bazy (**gdy będzie zrzut**) | raport `migration-run-YYYYMMDD.md` | `ImportLegacy` + `VerifyImport` = 100% zgodności; bez zrzutu — test na fiksturze | uruchomienie |
| T8.5 | UAT z właścicielem | checklista 20 zadań podpisana | wszystkie zadania wykonane na telefonie i desktopie; **pracownik wydaje laptop i zmienia koszt** | spotkanie 60 min |

### Faza 9 — Migracja i odcięcie (5 dni)

| ID | Zadanie | Deliverable | Kryterium akceptacji | Weryfikacja |
|---|---|---|---|---|
| T9.1 | Staging (pusty start, opcjonalny import) | środowisko + `app:install` w trybie `json` | aplikacja działa na staging **bez bazy**; jeśli pojawi się zrzut starej bazy — dodatkowo `ImportLegacy` + `VerifyImport` (100%) | smoke test + `store:check` |
| T9.2 | **Pakiet wdrożeniowy na hosting współdzielony** | `docs/aegis/runbook-deploy-ftp.md`, skrypt `build-release.ps1` (zip z `vendor/`), `public/.htaccess`, `config/persistence.php` w paczce | rozpakowany ZIP pod `/serwis` działa bez SSH i **bez bazy** (JSON): strona się otwiera, `APP_URL` poprawny, `.env`/`storage`/dane JSON niedostępne z przeglądarki | test na subdomenie staging |
| T9.3 | **Inicjalizacja danych na hostingu** | `GET /__install?token=…` (tworzy pliki JSON + konto admina) **albo** `schema.sql` przy trybie `mysql` | dane utworzone bez SSH; po instalacji route wyłączony (`INSTALL_TOKEN=` puste) | wejście na `/serwis/__install?token=…` → 404 po zabezpieczeniu |
| T9.4 | Okno przełączenia | `docs/aegis/runbook-cutover.md` | procedura krok po kroku z czasami i osobami odpowiedzialnymi | próba generalna na staging |
| T9.5 | Przełączenie i smoke test | nowa aplikacja pod `epii.pl/serwis` | 5 zleceń testowych: dane, zdjęcia, historia, wydanie, wydruk; logowanie każdego użytkownika; `store:check` wskazuje `json` | lista kontrolna |
| T9.6 | Rollback | `docs/aegis/runbook-rollback.md` | powrót do starej wersji < 15 min (poprzedni ZIP + kopia `storage/app/data`), przetestowany na staging | próba |
| T9.7 | Archiwizacja starej wersji | tag `legacy-final`, `old-app.zip` (+ `db.sql`, jeśli jest), pliki starej aplikacji usunięte z `public_html/serwis` | stara aplikacja nieosiągalna publicznie, dane zabezpieczone | `curl` na stare URL → 404 |

### Faza 10 — Domknięcie (1,5 dnia)

| ID | Zadanie | Deliverable | Kryterium akceptacji |
|---|---|---|---|
| T10.1 | Dokumentacja | `README.md`, ADR-y D1–D12, `docs/aegis/runbook-*.md` | nowy developer wdraża projekt w < 30 min według README (łącznie z wgraniem przez FTP) |
| T10.2 | Szkolenie i ściąga | `docs/user-guide.md` + 30-min sesja | pracownicy wykonują 10 typowych zadań bez pomocy, w tym druk potwierdzenia |
| T10.3 | Backlog | `docs/aegis/backlog.md` (B1–B11) | pozycje opisane z kosztem i wartością |
| T10.4 | Zamknięcie | tag `v1.0.0`, wpis w changelogu | definicja ukończenia (sekcja 14) spełniona |

### Sposób realizacji (execution handoff)

- **Inline (jedna sesja/sekwencyjnie):** F0, F1, F2, F5, T7.x, T9.x — decyzje i migracje wymagają ciągłego kontekstu.
- **Równolegle (subagenty):** T3.x i T4.x po zamknięciu T2.4 (niezależne pliki), T6.x po T1.4, T8.x po F3–F7.
- **Jedna osoba na plik:** konfliktogenne pliki (`routes/web.php`, `ServiceForm.php`, `app.css`) mają jednego właściciela w danej iteracji.
- Każde zadanie: najpierw test (dla nowej logiki), potem implementacja, potem bramki CI; commit po zadaniu, nie po fazie.

---

## 9. Migracja danych — procedura (opcjonalna)

> **Stan na dziś: brak bazy i brak danych do przeniesienia.** Aplikacja startuje na pustych danych (tryb JSON), a ten rozdział jest **instrukcją na moment, gdy pojawi się zrzut starej bazy**. Importer i weryfikacja są przygotowane i przetestowane na fiksturze, ale **nie blokują startu** (T2.7/T2.8 są opcjonalne, Faza 9 nie ma bramki „100% danych”).

### 9.1 Kolejność

1. **Zamrożenie starej aplikacji** na czas importu (najlepiej w oknie po godzinach; alternatywa: tryb read-only przez 30 min).
2. Zrzut bazy **z panelu hostingu / phpMyAdmin** (`Eksport` → SQL, UTF-8) + kopia katalogu `uploads/` przez FTP.
3. Import do tymczasowej bazy `legacy_import` (nie ruszamy oryginału).
4. Uruchomienie `ImportLegacy --dry-run` → raport CSV do przejrzenia (konflikty, braki, nieznane statusy).
5. Korekta reguł mapowania na podstawie raportu (bez ręcznego SQL na danych).
6. `ImportLegacy` (realny) na staging → `VerifyImport`.
7. Testy UAT na staging (Faza 8) → import na produkcję w oknie przełączenia.
8. Jeśli praca rozpoczęła się na JSON, a baza pojawia się później: `store:json-to-mysql` (idempotentne) zamiast kroków 1–6.

### 9.2 Reguły czyszczenia i raportowania

| Sytuacja w danych | Postępowanie |
|---|---|
| Telefon niemożliwy do normalizacji | zapis `phone` (oryginał), `phone_normalized = NULL`, wpis w raporcie „do ręcznej korekty” |
| Ten sam telefon w wielu zleceniach | bez zmian — każdy wiersz to osobne zlecenie (brak kartoteki klientów, decyzja D3); w raporcie liczba „powracających” numerów jako informacja |
| Duplikat plomby | zapis bez blokady + ostrzeżenie w raporcie |
| `status` spoza listy | `pending` + wpis w raporcie |
| `status = wydany` bez `godzina_wydania` | `released_at = accepted_at` + wpis w raporcie |
| `potwierdzenie_odbioru = 1` | `confirmation_issued_at = accepted_at` + wpis w raporcie (stare znaczenie pola niejasne — patrz D6) |
| `planowany_czas_wydania` NULL | `planned_at = accepted_at + 3 dni` + wpis |
| `created_at` NULL | `accepted_at = updated_at` + wpis |
| Zdjęcie: rekord bez pliku | rekord pominięty, zdjęcie w raporcie „osierocone rekordy” |
| Zdjęcie: plik bez rekordu | plik przeniesiony do `storage/quarantine/`, wpis w raporcie |
| `przyjety_przez` bez konta | konto techniczne `legacy-import` + oryginalna nazwa w opisie zdarzenia `accepted` |
| Hasło użytkownika | `Hash::make()` z plaintextu; **wymuszona zmiana przy pierwszym logowaniu** (flaga `must_change_password`) |
| Wartości `cena`/`koszty` z „brudnymi” zapisami | normalizacja `,`→`.`, obcięcie do 2 miejsc, wpis w raporcie jeśli wartość się zmieniła |

### 9.3 Weryfikacja (musi przejść przed cutover)

```sql
-- liczności (wartości muszą być identyczne, poza rekordami jawnie pominiętymi)
SELECT COUNT(*) FROM legacy_import.laptopservice;                    -- vs services
SELECT COUNT(*) FROM legacy_import.laptop_images;                    -- vs service_photos
SELECT COUNT(*) FROM legacy_import.service_history;                  -- vs service_events
SELECT SUM(cena), SUM(koszty) FROM legacy_import.laptopservice;      -- vs SUM(price), SUM(cost)
SELECT status, COUNT(*) FROM legacy_import.laptopservice GROUP BY status; -- vs nowe statusy
```

Dodatkowo: 10 losowych zleceń porównanych pole-po-polu (skrypt `VerifyImport --sample=10`), 5 zdjęć otwartych i porównanych wizualnie z oryginałem, 3 użytkowników logujących się na staging.

---

## 10. Strategia testów

### 10.1 Piramida

| Poziom | Narzędzie | Zakres | Cel pokrycia |
|---|---|---|---|
| Unit | Pest | enumy, telefon, kwoty, workflow, mapowanie legacy, **normalizacja daty (D-44/D-45)** | 90% klas domenowych |
| Feature | Pest (JSON: katalog tymczasowy; MySQL: `RefreshDatabase`) | każda akcja i trasa: happy path, walidacja, autoryzacja, CSRF, rate limit | 1 test na AC z macierzy F, **w obu trybach magazynu** |
| Magazyn | Pest dataset `['json','mysql']` | te same scenariusze na JSON i MySQL; osobne testy JSON: atomowość, `flock`, `next_id`, uszkodzony plik, `store:json-to-mysql` | 100% kontraktów repozytoriów |
| E2E | **Laravel Dusk** (PHP + ChromeDriver) | 16 scenariuszy na 2 rozmiarach okna | krytyczne ścieżki |
| A11y | **axe-core** wstrzykiwany z Dusk | 5 widoków | 0 błędów krytycznych |
| Migracja | Pest + fikstura dumpa | importer, mapowania, idempotencja (gdy pojawi się zrzut) | 100% reguł z 9.2 |
| Wydajność | skrypt PHP (`bench:queue`) + `ab`/`curl` | lista 200 zleceń, wyszukiwanie | p95 < 300 ms |

### 10.2 Scenariusze e2e (minimalny zestaw)

1. Logowanie → kolejka → wylogowanie (happy path + złe hasło + throttle).
2. Przyjęcie z 3 zdjęciami (telefon) → zlecenie widoczne w kolejce z miniaturami.
3. **Przyjęcie z zaznaczonym „wydrukuj potwierdzenie”** → wydruk otwiera się, `confirmation_issued_at` ustawione, karta pokazuje „🖨 potwierdzenie wydane”.
4. Przesunięcie statusu 3× (pending → in_progress → ready) → historia pokazuje 3 wpisy.
5. **Zapis bez zmian nie zmienia terminu ani flag** (regresja D-44/D-47/D-48).
6. Edycja: zmiana ceny i kosztu → wartości zapisane, koszt nie wyzerowany (regresja D-14).
7. Wydanie z telefonu (regresja D-10): formularz mobilny → status `released`, data z formularza, protokół wydania do druku.
8. Wyszukiwanie po fragmencie telefonu i po plombie → poprawne wyniki, także dla wydanych.
9. Usunięcie zdjęcia → zniknęło z galerii, plik niedostępny (także bez sesji), wpis w historii.
10. Offline: odcięcie sieci → baner, brak zapisów, brak danych klienta w cache; powrót → normalna praca.
11. **Anulowanie zlecenia** z powodem → znika z kolejki, jest w archiwum, historia ma wpis `cancelled`; anulowanie wydanego → 422.
12. **Uprawnienia (3 role)**: pracownik **wydaje laptop i zmienia koszt** (ma przycisk „Wydaj” i pole kosztu); technik działa tak samo; admin dodatkowo wchodzi do panelu użytkowników, a technik/pracownik dostają 403.
13. **Wydany serwis nie ma akcji w kolejce** i nie stoi nad naprawami (regresja D-46).
14. **Brak trasy publicznej**: `GET /lookup`, `/reklamacja*` → 404.
15. **Tryb JSON**: świeża instalacja bez bazy → dodanie zlecenia, zmiana statusu, wydanie i historia działają; w `services.json` jest wpis; `storage/app/data` niedostępny przez HTTP.
16. **Przełączenie na MySQL**: `store:json-to-mysql` → te same dane w bazie, liczba rekordów zgodna, aplikacja działa po zmianie `DATA_STORE`.

### 10.3 Bramki CI (każdy PR, dwa joby)

```bash
# job "json" — bez MySQL (tak jak start u właściciela)
DATA_STORE=json ./vendor/bin/pint --test
DATA_STORE=json ./vendor/bin/phpstan analyse --memory-limit=1G
DATA_STORE=json php artisan test --parallel
DATA_STORE=json php artisan dusk          # wymaga chromedriver (binarka, nie Node)

# job "mysql" — te same testy na bazie
DATA_STORE=mysql php artisan test --parallel
DATA_STORE=mysql php artisan store:json-to-mysql --verify

composer audit
composer validate --strict
```

**Kontrola „bez Node/Python” w CI:** krok `test ! -f package.json && test ! -f yarn.lock && test ! -f pnpm-lock.yaml` oraz brak kroku `setup-node`. W repo nie ma plików `.py`.

---

## 11. Wdrożenie i odcięcie

### 11.1 Środowiska

| Środowisko | Cel | Magazyn danych | Dostęp |
|---|---|---|---|
| local | development | **JSON** domyślnie; MySQL (XAMPP) do testów drugiego trybu (PHP 8.4.27 i 8.2.12, Composer 2.11 — **Node/npm nie są potrzebne**) | deweloper |
| staging | UAT, próba wdrożenia | JSON (jak produkcja) + opcjonalnie MySQL | właściciel + deweloper, `basic auth` |
| production | praca warsztatu | **JSON** na start; MySQL po podłączeniu bazy | pracownicy, `https://epii.pl/serwis` |

**Deploy bez SSH, bez Composera i bez bazy na serwerze:**

1. Lokalnie: `composer install --no-dev --optimize-autoloader`, `php artisan config:cache route:cache view:cache`, spakowanie ZIP-a **razem z `vendor/`** (`build-release.ps1`).
2. Wgranie ZIP-a przez FTP/panel, rozpakowanie **poza** `public_html` (np. `~/serwis-app/`) i podłączenie `public/` do `public_html/serwis` — jeśli hosting na to pozwala.
3. Wariant zapasowy (gdy nie ma dostępu nad webroot): cała aplikacja w `public_html/serwis/`, a `vendor/`, `storage/`, `bootstrap/` i `.env` zablokowane w `.htaccess` (`Require all denied` + `deny from all`); **katalog `storage/app/data` (pliki JSON) musi być niedostępny przez HTTP** — to jest sprawdzane testem.
4. Inicjalizacja danych: **wariant A (start)** — `/serwis/__install?token=…` tworzy pliki JSON + konto admina (żadnej bazy nie potrzeba); **wariant B (gdy jest baza)** — `DATA_STORE=mysql` + SQL z phpMyAdmin albo ten sam instalator robiący `migrate --force`; po instalacji `INSTALL_TOKEN=` (puste) → route 404.
5. Wersjonowanie assetów PWA: `ASSET_VERSION` w `.env` podbijane przy każdym wdrożeniu (bez tego klienci zostaną na starym cache).
6. **Backup = ZIP całego `storage/app`** (dane JSON + zdjęcia); w trybie MySQL dodatkowo eksport bazy z panelu.

### 11.2 Co musi potwierdzić hosting (T0.2, decyzja D2)

| Wymaganie | Minimum | Jeśli brak |
|---|---|---|
| PHP | 8.2+ z `mbstring`, `gd`, `fileinfo`, `openssl`, `intl`; `pdo_mysql` tylko gdy włączysz MySQL | zmiana hostingu |
| MySQL/MariaDB | **opcjonalnie** 5.7+/10.3+, `utf8mb4` (tryb JSON działa bez bazy) | zostajemy na JSON |
| HTTPS | certyfikat na `epii.pl` | PWA nie zadziała poza localhost |
| Zapis plików | `storage/` zapisywalne (755/775) — **krytyczne dla JSON** | zgłoszenie do supportu |
| `.htaccess` | `mod_rewrite` + `Require all denied` | przeniesienie `storage`/`vendor` nad webroot |
| Cron | opcjonalnie; jeśli brak → zewnętrzny pinger HTTP z tokenem | harmonogram tylko „przy okazji” żądań |
| Limit uploadu | `upload_max_filesize`/`post_max_size` ≥ 12 MB | zmniejszenie limitu zdjęć |
| Kopia zapasowa | eksport przez panel/phpMyAdmin (MySQL) lub ZIP `storage/app` (JSON) | własna komenda `backup:database` |

**Wymagania wobec hostingu, które są twarde:** PHP 8.2+, HTTPS, zapisywalny `storage/`, możliwość blokady katalogów w `.htaccess`. **Baza nie jest wymagana.**

### 11.3 Cutover (okno 30–60 min — nie ma danych do przenoszenia)

1. T-24 h: kopia zapasowa starej aplikacji (ZIP plików; baza tylko jeśli istnieje), test restore na staging.
2. T-0: wgranie ZIP-a nowej aplikacji pod `/serwis`; instalacja danych (`/__install` w trybie JSON albo `DATA_STORE=mysql` + import schematu).
3. Jeśli **później** pojawi się zrzut starej bazy: `ImportLegacy --dry-run` → korekta → import → `VerifyImport` (rozdział 9), poza godzinami pracy.
4. Smoke test wg T9.5 (5 zleceń, zdjęcia, historia, wydanie, wydruk), `store:check`.
5. Komunikat do pracowników + numer awaryjny; stara aplikacja zostaje w kopii (nie w webroocie).
6. T+24 h: przegląd logów, brak błędów 500, korekta drobnych uwag.
7. T+7 dni: archiwizacja starej wersji, zamknięcie fazy.

### 11.4 Rollback

- Warunek: błąd blokujący pracę > 30 min lub utrata danych.
- Procedura: wgranie poprzedniego ZIP-a (stara aplikacja trzymana w archiwum do T+7), przywrócenie `storage/app` (JSON + zdjęcia) lub bazy z eksportu, komunikat.
- Dane zapisane w nowej aplikacji po cutoverze: eksport zleceń (JSON/CSV) i ręczne wprowadzenie — dlatego okno cutoveru jest poza godzinami pracy.

---

## 12. Decyzje do podjęcia przed startem

**Wszystkie decyzje są zamknięte** — właściciel odpowiedział 2026-11-02. Poniżej wartości wiążące; żadna nie blokuje już startu.

| ID | Decyzja | Wartość wiążąca | Skutek w planie |
|---|---|---|---|
| **D1** | Stack | **PHP 8.2+ (Laravel 12 + Livewire 3 + Bootstrap 5), bez Node.js i Pythona** | sekcja 5.1, testy Dusk, ikony z GD |
| **D2** | Hosting | **własny hosting współdzielony, aplikacja w podkatalogu `epii.pl/serwis`**, pliki przez FTP/panel, bez SSH/Composera na serwerze | sekcja 11.1 (ZIP z `vendor/`, instalator z tokenem), 11.2 (checklist hostingu) |
| **D3** | Model klienta | **tylko telefon — bez kartoteki `customers`, bez imion** | uproszczony schemat (6.1), migracja bez mapowania klientów |
| **D4** | Publiczne sprawdzanie statusu | **NIE** — klient nie sprawdza; moduł `reklamacja*` usunięty | F-30/F-31 odrzucone, brak tras publicznych, backlog B6 |
| **D5** | Offline | **tylko odczyt shella + blokada zapisów**, bez kolejki offline | F-44, T6.2/T6.3 |
| **D6** | „Potwierdzenie odbioru” | **pole przeznaczone na flagę „potwierdzenie przyjęcia wydane”**, widoczną na karcie zlecenia; przy przyjęciu checkbox druku | F-17, F-55, `confirmation_issued_at` |
| **D7** | Drukowanie | **TAK — checkbox „wydrukuj potwierdzenie przyjęcia”**; dodatkowo protokół wydania i ponowny druk z karty | F-55, T5.3 (+2 dni) |
| **D8** | Retencja | **bezterminowo**, bez anonimizacji | brak komendy anonimizującej; T7.5 = tylko kopie zapasowe |
| **D9** | Role | **admin / pracownik / technik**; **pracownik ma pełną pracę operacyjną, w tym wydawanie laptopów i zmianę kosztów**; technik na razie taki sam — prawa dostanie przy kolejnych funkcjach; admin dodatkowo konta | macierz w 5.3, `Role` enum, testy uprawnień |
| **D10** | Numer zlecenia | `reference` (`SRV-2026-000123`) obok `legacy_id` | schemat 6.1, wydruki |
| **D11** | Testy e2e | **Laravel Dusk** (PHP + ChromeDriver) | sekcja 10.1, CI |
| **D12** | Ikony PWA | **generator PHP/GD** (`php artisan icons:generate`) | T6.1 |
| **D13** | Magazyn danych | **wymienny: `json` (domyślnie, start bez bazy) albo `mysql`**; wybór w `config/persistence.php` / `.env`, przełączanie bez zmian w kodzie, migracja danych komendą `store:json-to-mysql` | sekcja 5.7, F-57, T2.3–T2.6, CI w dwóch jobach |

**Jedyne świadome odstępstwo od dobrych praktyk:** hasło do laptopa jest przechowywane i widoczne w UI bez maskowania i bez audytu ujawnienia — decyzja właściciela („wersja testowa, nie przejmuj się RODO”). W bazie nadal jest szyfrowane, bo to kosztuje zero wysiłku.

---

## 13. Ryzyka i założenia

| ID | Ryzyko | Prawdopodobieństwo / wpływ | Mitygacja |
|---|---|---|---|
| R1 | Rzeczywisty schemat produkcji różni się od wszystkich dumpów (potwierdzone: brak `users.id` wg logów) | wysokie / wysokie | T0.2 `SHOW CREATE TABLE` **przed** projektowaniem migracji; importer odporny na brakujące kolumny |
| R2 | Hosting współdzielony nie pozwala wyjść z aplikacją nad `public_html` ani nie ma SSH/Composera → wdrożenie i migracje są ręczne | wysokie / średnie | T9.2–T9.3: ZIP z `vendor/`, instalator z tokenem albo SQL z phpMyAdmin; wariant zapasowy z `.htaccess`; T0.2 weryfikuje to **przed** pisaniem kodu |
| R3 | Jakość danych: telefony w różnych formatach, NULL-e w datach, rekordy „wydany” bez daty, zdjęcia-sieroty | wysokie / średnie | raport migracji + jawne reguły (9.2), ręczna korekta tylko wyjątków |
| R4 | Wymuszona zmiana haseł u userów (plaintext → hash) | pewne / niskie | hasła hashujemy z plaintextu, więc logowanie działa; wymuszona zmiana tylko dla kont z hasłem startowym |
| R5 | Przyzwyczajenie do dwóch osobnych interfejsów (desktop/mobile) | średnie / średnie | jeden responsywny UI z tymi samymi akcjami; szkolenie + ściąga; test UAT na telefonie |
| R6 | Przestój w oknie cutoveru | średnie / średnie | okno poza godzinami, próba generalna na staging, rollback < 15 min |
| R7 | Oczekiwanie „offline działa jak w dokumentacji” (stara dokumentacja obiecywała kolejkę zapisów, której nie było) | wysokie / niskie | jasna komunikacja zakresu D5; baner offline z wyjaśnieniem |
| R8 | Scope creep (statystyki, faktury, publiczny status) w trakcie | wysokie / średnie | backlog B1–B11 poza v1; zmiany zakresu tylko przez nową decyzję |
| R9 | Dane produkcyjne na staging (telefony, hasła do laptopów) | średnie / średnie | staging na tym samym hostingu za `basic auth` + `.htpasswd`; baza staging usuwana po UAT |
| R10 | Utrata zdjęć przy migracji (pliki poza repo, ścieżki niejednolite) | średnie / wysokie | kopia `uploads/` + `photos:verify` + raport sierot; oryginały w `storage/quarantine/` do 30 dni |
| R11 | Ograniczenie „bez Node” utrudnia nowoczesny CSS (brak Tailwinda) i testy w przeglądarce | średnie / średnie | Bootstrap 5 + własna warstwa CSS (wystarczy dla tej skali UI); Dusk zamiast Playwrighta; w razie potrzeby Tailwind standalone CLI (bez Node) |
| R12 | Dusk wymaga ChromeDrivera i Chrome na CI oraz lokalnie | średnie / niskie | binarka `chromedriver` w repo CI, wersja dopasowana w jednym miejscu; testy e2e uruchamiane w osobnym jobie |
| R13 | Livewire/Alpine ładowane z pakietu Composer — nietypowe dla kogoś przyzwyczajonego do `npm` | niskie / niskie | udokumentować w README: brak `package.json`, assety z `vendor/livewire/livewire/dist`; zakaz edycji plików w `vendor/` |
| R14 | Aplikacja w podkatalogu `/serwis` na hostingu — ryzyko błędnych ścieżek (stara apka miała `BASE_URL` na sztywno) | średnie / średnie | zasada „tylko `route()`/`asset()`”; test Dusk na URL z prefiksem; smoke test po wgraniu |
| R15 | Druk potwierdzeń zależy od ustawień drukarki/przeglądarki pracownika (marginesy, nagłówki) | średnie / niskie | dedykowany arkusz `@media print` (A4, marginesy 10 mm, `@page`), test `printToPDF` w Dusk, szkolenie „Ctrl+P” |
| R16 | Brak cron na hostingu → zadania cykliczne (backup, czyszczenie cache) nie odpalą się same | średnie / niskie | harmonogram przez zewnętrzny pinger HTTP z tokenem; backup uruchamiany ręcznie z panelu aplikacji |
| R17 | Tryb JSON: przy równoczesnym zapisie dwóch urządzeń lub przy dużym pliku rośnie ryzyko wolnego zapisu/konfliktu; uszkodzony plik = utrata danych | średnie / wysokie | `flock` + zapis atomowy przez `rename()`, wersjonowanie (`schema_version`), kopia `.bak` przed zmianą schematu, backup całego `storage/app`; `store:check` ostrzega powyżej ~5000 zleceń i sugeruje MySQL |
| R18 | Dwa tryby magazynu = dwa razy więcej testów i ryzyko rozjazdu zachowania („na JSON działa, na MySQL nie”) | średnie / średnie | jeden zestaw interfejsów + testy uruchamiane w obu trybach (dataset), dwa joby CI, zakaz `DB::`/`Storage::` w akcjach i komponentach |
| R19 | Brak danych startowych: aplikacja ruszy pusta, więc „parzystość funkcji” nie zostanie sprawdzona na realnych rekordach | średnie / średnie | dossier testowe (`DemoSeeder`) odwzorowuje stare dane; po otrzymaniu zrzutu uruchamiamy `ImportLegacy` + `VerifyImport` bez zmian w kodzie |

**Założenia:** jeden warsztat, 1–5 użytkowników, do ~2000 zleceń rocznie, brak integracji zewnętrznych w v1, pracownicy korzystają głównie z telefonów, klient nie loguje się do systemu i nie sprawdza statusu.

---

## 14. Definicja ukończenia (DoD) i checklista parzystości

### 14.1 DoD całego projektu

- [ ] Wszystkie zadania T0–T10 zamknięte, każda faza przeszła swoją bramkę.
- [ ] `php artisan test` zielone; pokrycie klas domenowych ≥ 90%.
- [ ] PHPStan ≥ 6 bez błędów; Pint czysty; **w repo brak `package.json`, `node_modules`, plików `.py`**; `composer validate --strict` OK.
- [ ] Laravel Dusk: 16 scenariuszy × 2 rozmiary okna zielone; axe bez błędów krytycznych.
- [ ] **Oba magazyny danych zielone:** cały krytyczny zestaw testów przechodzi na `DATA_STORE=json` i `DATA_STORE=mysql`; `store:check` poprawnie rozpoznaje tryb; `store:json-to-mysql` przenosi dane bez duplikatów.
- [ ] **Start bez bazy:** świeża instalacja na hostingu (JSON) działa — logowanie, przyjęcie, wydanie, historia, wydruk.
- [ ] Migracja: `VerifyImport` = 100% **gdy pojawi się zrzut**; bez zrzutu — importer przetestowany na fiksturze.
- [ ] Brak pozycji D-01…D-58 w nowej aplikacji (raport z przeglądu).
- [ ] Regresje z runtime zamknięte testami: termin nie dryfuje (D-44/D-45), koszty nie zerowane (D-47), flagi nie zmieniają się same (D-48), wydany nie ma akcji (D-46), XSS w `?error=` niemożliwy (D-04), cache bez PII (D-28).
- [ ] **Nowe funkcje działają:** druk potwierdzenia przyjęcia i protokołu wydania (F-55), anulowanie zlecenia (F-56), macierz 3 ról z wydawaniem i kosztami dla pracownika (F-06), wybór magazynu danych (F-57).
- [ ] **Brak tras publicznych:** `GET /lookup`, `/reklamacja*`, `/wyswietlanie*`, `/wydane.php` → 404; zdjęcie i pliki JSON bez sesji → 403/404.
- [ ] **Wdrożenie na hostingu bez SSH:** ZIP z `vendor/` wgrany przez FTP, aplikacja działa pod `epii.pl/serwis`, instalator wyłączony.
- [ ] Stara aplikacja zarchiwizowana i niedostępna publicznie; rollback przetestowany.
- [ ] Dokumentacja: README, ADR-y D1–D13, runbooki (backup, deploy FTP, cutover, rollback), instrukcja użytkownika.
- [ ] Szkolenie przeprowadzone, pracownicy wykonują 10 zadań bez pomocy (w tym wydruk potwierdzenia i wydanie laptopa).
- [ ] Brak sekretów w repo (`git secrets`/przegląd); `.env.example` kompletny z obydwoma trybami magazynu.

### 14.2 Checklista parzystości (do odhaczania w T8.1)

**Uwierzytelnianie:** [ ] F-01 logowanie [ ] F-02 wylogowanie [ ] F-03 ochrona tras [ ] F-04 rotacja sesji [ ] F-05 CSRF [ ] F-06 użytkownicy i 3 role

**Przyjęcie:** [ ] F-07 telefon [ ] F-08 model [ ] F-09 usterka [ ] F-10 hasło urządzenia [ ] F-11 S/N [ ] F-12 uwagi [ ] F-13 termin [ ] F-14 cena [ ] F-15 koszt [ ] F-16 express [ ] F-17 flaga potwierdzenia wydanego [ ] F-18 zdjęcia [ ] F-19 status początkowy [ ] F-20 wpis historii

**Kolejka:** [ ] F-21 zakres listy [ ] F-22 sortowanie [ ] F-23 karta [ ] F-24 następny etap [ ] F-25 wydaj z karty [ ] F-26 wydane

**Wyszukiwanie:** [ ] F-27 globalne [ ] F-28 telefon [ ] F-29 plomba [ ] F-30 nie dotyczy (D4) [ ] F-31 nie dotyczy (D4) [ ] F-32 filtry statusu

**Edycja i historia:** [ ] F-33 zmiana statusu [ ] F-34 edycja pól [ ] F-35 status z formularza [ ] F-36 plomba [ ] F-37 zdjęcia w edycji [ ] F-38 historia [ ] F-56 anulowanie

**Wydanie:** [ ] F-39 formularz [ ] F-40 status i czas [ ] F-41 wpis historii [ ] F-42 wydanie z telefonu

**Drukowanie:** [ ] F-55 potwierdzenie przyjęcia [ ] protokół wydania [ ] ponowny druk

**PWA i UI:** [ ] F-43 instalacja [ ] F-44 offline [ ] F-45 push (odrzucone) [ ] F-46 sync (backlog) [ ] F-47 lightbox [ ] F-48 jeden responsywny UI [ ] F-49 brak wykrywania UA [ ] F-50 audyt [ ] F-51 logi [ ] F-52 instalator [ ] F-53 dane demo [ ] F-54 diagnostyka zdjęć

**Magazyn danych:** [ ] F-57 tryb JSON (start bez bazy) [ ] F-57 tryb MySQL [ ] `store:check` [ ] `store:json-to-mysql`

---

## 15. Backlog poza v1

| ID | Pozycja | Wartość | Orientacyjny koszt |
|---|---|---|---|
| B1 | Kolejka zapisów offline (IndexedDB + Background Sync) z rozwiązywaniem konfliktów | praca w piwnicy/na wyjeździe bez zasięgu | 5–8 dni |
| B2 | Tabela `devices` (ten sam laptop w wielu naprawach, gwarancja) | mniej duplikacji, historia urządzenia | 2–3 dni |
| B3 | PDF do e-maila (dziś druk przez przeglądarkę) | wysyłka potwierdzenia mailem | 1–2 dni |
| B4 | Powiadomienia SMS/e-mail do klienta („do odbioru”) | mniej telefonów od klientów | 2 dni + koszt bramki |
| B5 | Panel statystyk (przyjęcia/wydania/czas naprawy/przychód) | decyzje biznesowe | 2–3 dni |
| **B6** | **Publiczne sprawdzanie statusu przez klienta** (telefon + plomba, tylko status/termin) — świadomie poza v1 | mniej telefonów „co z moim laptopem”; wymaga decyzji o RODO i osobnej trasy | 1–2 dni |
| B7 | Ewidencja części i kosztów z magazynem | dokładniejsza marża | 5–8 dni |
| B8 | Etykiety z kodem QR/kreskowym (skan przy przyjęciu i wydaniu) | szybsza obsługa, mniej pomyłek | 2–3 dni |
| B9 | Druga lokalizacja / wielu techników z przypisaniem | rozwój firmy | 5–10 dni |
| B10 | Automatyczny backup do chmury (S3/Backblaze) | odporność na awarię | 1 dzień |
| **B11** | **Podpis klienta na ekranie przy wydaniu** (zamiast samej flagi) | mocniejszy dowód wydania | 2–3 dni |
| **B12** | **Import danych ze starej aplikacji** — uruchomienie, gdy pojawi się zrzut bazy (kod gotowy, test na fiksturze) | ciągłość historii napraw | 0,5 dnia |
| **B13** | Eksport zestawienia wydań (CSV/PDF) do rozliczeń | mniej ręcznego przepisywania | 1–2 dni |
| **B14** | Przypisanie zlecenia do konkretnego technika (kolumna + filtry „moje zlecenia”) | porządek przy większej liczbie osób | 1–2 dni |

---

## 16. Załącznik A — klasyfikacja plików starej aplikacji

Wszystkie 77 plików z `old-app/` (plus katalogi) z decyzją, co się z nimi dzieje. „Koncept” = funkcja przenoszona, „Usuń” = nie przenosimy.

### A.1 Warstwa aplikacji (przepisać koncept)

| Plik | Rola | Decyzja |
|---|---|---|
| `index.php` | routing wg urządzenia | Koncept (F-49 odrzucone → responsywny UI) |
| `logowanie.php`, `process_login.php`, `logout.php` | logowanie/wylogowanie | Koncept (F-01, F-02) |
| `dashboard.php` | dashboard desktop | Koncept → jeden UI (F-21…F-26, F-32…F-38, F-42, F-47) |
| `dashboard_mobile.php` | dashboard mobile | Koncept (te same F, bez osobnego pliku) |
| `edit_service.php`, `release_service.php` | edycja/wydanie na stronie | Koncept (F-34, F-39) |
| `search_by_phone.php`, `search_by_plomba.php` | wyniki wyszukiwania | Koncept (F-28, F-29) |
| `process_add_service.php` | przyjęcie (POST) | Koncept (F-07…F-20) |
| `process_edit_service.php` | edycja (POST) | Koncept (F-33…F-36) |
| `process_release_service.php` | wydanie (POST) | Koncept (F-39…F-41) |
| `process_change_status.php` | zmiana statusu (AJAX/JSON) | Koncept (F-24, F-33) |
| `get_service.php` | API: zlecenie + zdjęcia | Koncept (dane do formularza) |
| `get_service_images.php` | API: zdjęcia | Koncept (galeria) |
| `get_service_history.php` | API: historia | Koncept (F-38) |
| `delete_image.php` | usuwanie zdjęcia | Koncept (F-37) |
| `dodawanie_laptopa.php`, `main_phone.php` | formularze przyjęcia (stare) | Koncept (te same F, jeden formularz) |
| `edytowanie.php`, `edytowanie_phone.php` | edycja (stare) | Koncept (jeden formularz) |
| `wydawanie.php`, `wydawanie_phone.php` | wydanie (stare) | Koncept (jedna ścieżka) |
| `wyswietlanie.php`, `wyswietlanie_phone.php`, `wydane.php` | listy (stare, bez auth) | Koncept (F-21, F-26, F-32) — **pliki usunięte** |
| `reklamacja.php`, `reklamacja_phone.php` | formularz sprawdzenia statusu | **Usuń** — decyzja D4: brak publicznego sprawdzania (backlog B6) |
| `reklamacja_nr_tel.php`, `reklamacja_plomba.php` | wyniki sprawdzenia statusu | **Usuń** — D4; dodatkowo SQL injection i wyciek hasła (D-02/D-03) |

### A.2 Warstwa wspólna

| Plik | Decyzja |
|---|---|
| `config/config.php` | Zastąpione `.env` + `config/*` |
| `database.php` | Usuń (duplikat konfiguracji z root/"") |
| `includes/Database.php` | Koncept (bez logowania każdego zapytania) |
| `includes/Service.php` | Koncept → `app/Actions/*` + modele |
| `includes/Auth.php` | Koncept → Laravel auth + hash |
| `includes/SessionManager.php` | Koncept → sesja Laravel + CSRF |
| `includes/Helpers.php` | Koncept częściowy (normalizacja, formatowanie); resztę zastępują helpery frameworka |

### A.3 Frontend/PWA

| Plik | Decyzja |
|---|---|
| `manifest.json` | Przepisać (ikony, skróty, bez `share_target`) |
| `service-worker.js` | Przepisać (Workbox, wersjonowanie, bez cache danych) |
| `pwa-manager.js` | Koncept częściowy (instalacja, status online) — bez auto-reload i bez martwych metod |
| `pwa-head.php` | **Usuń** (nieużywany, duplikuje meta) |
| `offline.html` | Przepisać jako widok offline |
| `lightbox.js`, `lightbox.css` | Koncept → dostępny komponent galerii |
| `sort-services.js` | Koncept → sortowanie serwerowe (jedno źródło) |
| `images/icon-192x192.png` | Zostaje jako baza, dochodzą 96/512/maskable + screenshoty |
| `uploads/` (4 pliki) | Migracja do storage prywatnego |

### A.4 Dane i narzędzia

| Plik | Decyzja |
|---|---|
| `setup_database.sql` | Referencja (archiwum) — zastąpione migracjami Laravel |
| `setup_sql7817169.sql` | Referencja (archiwum) — schemat **niekompatybilny**, nie używać |
| `add_service_history.sql` | Referencja (archiwum) — zawiera błędny `ALTER` na nieistniejącej kolumnie |
| `migrations/add_koszty_column.sql` | Referencja (archiwum) — redundantna |
| `test_data_30_laptops.sql` | Koncept → `DemoSeeder` |
| `setup.php` | **Usuń** → `artisan app:install` |
| `load_test_data.php` | **Usuń** → `artisan db:seed --class=DemoSeeder` |
| `diagnose_images.php`, `fix_image_paths.php` | **Usuń** (nie działają) → `artisan photos:verify --fix` |
| `verify-pwa.php` | **Usuń** → test manifestu w CI |
| `logs/actions.log`, `logs/errors.log` | Archiwum (dowody audytu); nowe logi w `storage/logs` |

### A.5 Dokumentacja i pozostałe

| Plik | Decyzja |
|---|---|
| `agents.md`, `AUDYT-RAPORT.md`, `IMPROVEMENTS.md`, `TODO-panel.md`, `FIX_REPORT.md`, `COMPLETE.md`, `DOCUMENTATION.md`, `INSTALLATION.md`, `DEPLOYMENT-CHECKLIST.md`, `SETUP.md`, `PWA-GUIDE.md`, `PWA-README.md`, `PWA-SUMMARY.md` | Archiwum w `docs/aegis/legacy/` — **nie** przenosić twierdzeń bez weryfikacji z kodem (dokumenty miejscami nieaktualne) |
| `.github/agents/serwis-developer.agent.md` | Usuń (konfiguracja asystenta AI dla starego kodu) |

---

## 17. Załącznik B — komendy weryfikacyjne

### B.1 Środowisko lokalne (sprawdzone w tej sesji)

```
PHP 8.4.27 (cli) — C:\Users\Mati\...\PHP.PHP.NTS.8.4\php.exe
PHP 8.2.12 (XAMPP) — C:\xampp\php\php.exe     ← użyty do uruchomienia starej aplikacji (mysqli, gd, fileinfo)
Composer 2.11.1, git 2.54.0
MariaDB 10.4.32 — C:\xampp\mysql\bin\{mysqld,mysql,mysql_install_db}.exe
Node 24.15 / npm 11.12 są na maszynie, ale projekt ich nie używa (warunek D1)
```

Uwaga: MariaDB 10.4 jest po zakończeniu wsparcia (EOL) i **istniejący data-dir XAMPP był uszkodzony** (`InnoDB: Page ... log sequence number ... is in the future`, crash w `parse_user`). Do testów użyto czystego data-diru w `_runtime/mysql-data`. Lokalnie wystarczy, ale **produkcja powinna dostać MySQL 8.0+/MariaDB 11.x** — do potwierdzenia w D2.

### B.2 Odtworzenie środowiska testowego (bez Node/Pythona)

```powershell
# 1. baza: czysty data-dir + start z utf8mb4 (inaczej polskie znaki z dumpa zamieniają się w "?")
& 'C:\xampp\mysql\bin\mysql_install_db.exe' --datadir='D:\Deepseekapp\serwis\_runtime\mysql-data'
& 'C:\xampp\mysql\bin\mysqld.exe' --basedir='C:\xampp\mysql' --datadir='D:\Deepseekapp\serwis\_runtime\mysql-data' --port=3306 --character-set-server=utf8mb4 --collation-server=utf8mb4_unicode_ci

# 2. schemat (source czyta plik jako UTF-8; przekierowanie "<" w PowerShell psuje znaki)
& 'C:\xampp\mysql\bin\mysql.exe' -u root -h 127.0.0.1 --default-character-set=utf8mb4 -e "source D:/Deepseekapp/serwis/old-app/setup_database.sql"
# + tabela service_history (bez błędnego ALTER z add_service_history.sql — patrz D-36)

# 3. aplikacja pod /serwis/ (junction, bo BASE_URL='/serwis/')
New-Item -ItemType Junction -Path 'D:\Deepseekapp\serwis\_runtime\htdocs\serwis' -Target 'D:\Deepseekapp\serwis\old-app'
& 'C:\xampp\php\php.exe' -S 127.0.0.1:8080 -t 'D:\Deepseekapp\serwis\_runtime\htdocs'
# http://127.0.0.1:8080/serwis/logowanie.php  (admin / admin)
```

### B.3 Bramki projektu (do uruchamiania po każdym zadaniu)

```bash
# tryb domyślny (JSON, bez bazy)
DATA_STORE=json ./vendor/bin/pint --test
DATA_STORE=json ./vendor/bin/phpstan analyse --memory-limit=1G
DATA_STORE=json php artisan test --parallel
DATA_STORE=json php artisan dusk                       # e2e (ChromeDriver)

# tryb mysql (gdy baza jest dostępna)
DATA_STORE=mysql php artisan migrate:fresh --seed && DATA_STORE=mysql php artisan test
composer audit
composer validate --strict
```

### B.4 Komendy aplikacji (docelowe)

```bash
php artisan store:check                     # aktywny magazyn (json/mysql), ścieżki, braki konfiguracji, liczba rekordów
php artisan app:install                     # JSON: pliki danych + konto admina; MySQL: migracje + konto admina
php artisan store:json-to-mysql             # przeniesienie danych z plików do bazy (idempotentne, --verify porównuje liczności)
php artisan icons:generate                  # ikony PWA z GD (zastępuje generate-icons.py)
php artisan legacy:import --dry-run         # raport migracji (gdy pojawi się zrzut starej bazy)
php artisan legacy:import                   # import właściwy
php artisan legacy:verify                   # zgodność liczności i sum
php artisan photos:verify [--fix]           # spójność zdjęć
php artisan backup:database                 # JSON: ZIP storage/app/data + photos; MySQL: zrzut bazy
```

### B.5 Wdrożenie na hosting właściciela (bez SSH, bez bazy)

```powershell
# 1. lokalnie: paczka z vendor/ (bez node_modules i bez .env)
& .\build-release.ps1            # composer install --no-dev, config:cache, zip → release-YYYYMMDD.zip

# 2. przez FTP/panel: rozpakuj do ~/serwis-app/ i podłącz public/ → public_html/serwis
#    (wariant zapasowy: cała aplikacja w public_html/serwis + .htaccess blokujący vendor/storage/.env)

# 3. dane: bez bazy wystarczy jednorazowo
#    https://epii.pl/serwis/__install?token=<INSTALL_TOKEN>     → tworzy storage/app/data + konto admina
#    po instalacji: INSTALL_TOKEN= (puste) → route zwraca 404
#    (jeśli włączasz MySQL: DATA_STORE=mysql w .env + dane bazy, wtedy instalator robi migrate --force)

# 4. sprawdzenie
#    https://epii.pl/serwis/login  → logowanie
#    https://epii.pl/serwis/manifest.webmanifest → 200
#    https://epii.pl/serwis/storage/app/data/services.json → 403/404
#    https://epii.pl/serwis/storage/logs/laravel.log → 403/404
```

### B.6 Weryfikacja po cutoverze (lista kontrolna)

```
[ ] logowanie każdym kontem; pracownik widzi i używa „Wydaj” oraz pola kosztu
[ ] kolejka pokazuje wszystkie aktywne zlecenia (bez wydanych z przyciskiem „Next”)
[ ] 5 zleceń testowych: dane, zdjęcia, historia
[ ] zapis formularza bez zmian nie zmienia terminu (kontrola w pliku/bazie)
[ ] wydanie testowego zlecenia z telefonu i z komputera
[ ] wydruk potwierdzenia przyjęcia i protokołu wydania (Ctrl+P → 1 strona A4)
[ ] wyszukiwanie po telefonie i plombie (także inny format numeru)
[ ] zdjęcie klienta i plik services.json nie otwierają się bez logowania (403/404)
[ ] instalacja PWA na telefonie, tryb offline bez danych klientów
[ ] `php artisan store:check` wskazuje oczekiwany magazyn (json lub mysql)
[ ] brak błędów 500 w logach przez 24 h
```

---

## 18. Jak ten plan został sprawdzony (self-review)

- **Kompletność statyczna:** każdy plik z `old-app/` ma przypisanie w Załączniku A (77 pozycji); każda funkcja znaleziona w kodzie ma ID F-01…F-57 i wpis w macierzy parzystości (sekcja 7); każdy defekt ma ID D-01…D-58 i regułę w nowej aplikacji.
- **Kompletność runtime:** aplikacja została uruchomiona i przeklikana (sekcja 2.1); wyniki w 3.6. Każdy nowy defekt ma dowód (odczyt bazy przed/po, odpowiedź HTTP, zawartość Cache Storage, log serwera), a nie tylko przypuszczenie.
- **Decyzje właściciela odwzorowane w planie:** każda z 13 odpowiedzi (D1–D13) ma odbicie w schemacie/konfiguracji, macierzy parzystości, zadaniach i DoD — sprawdzone przez wyszukanie sprzeczności (brak publicznego lookupu poza backlogiem, brak `customers`, brak anonimizacji, obecny `confirmation_issued_at`, **pracownik ma wydanie i koszty**, **oba magazyny danych w AC i CI**).
- **Brak placeholderów:** w planie nie ma „TODO/TBD”; wszystkie decyzje są zamknięte i mają wartość wiążącą.
- **Spójność liczb:** liczba pozycji F (57) jest zgodna między sekcją 3.4, 7 i 14.2; liczba defektów D (58 = 43 z analizy + 15 z runtime) między sekcją 4 i 14.1; szacunek **42 dni = 3+4+8+6+3+3+3+2,5+3+5+1,5** (suma nagłówków faz w sekcji 8).
- **Zgodność z warunkiem „bez Node/Python”:** stack (5.1), testy (10.1), CI (10.3), deploy (11.1) i DoD (14.1) nie zawierają `npm`, `npx`, Vite ani Pythona; ikony generuje GD, e2e robi Dusk, CSS to plik Bootstrapa w repo. Kontrola braku `package.json` jest wpisana w CI.
- **Wykonalność bez bazy:** sekcja 5.7 i 11 opisują start na JSON (bez MySQL), przełączenie na bazę jednym wpisem w konfiguracji, migrację danych z JSON do MySQL oraz wariant wdrożenia bez SSH i bez Composera na serwerze.
- **Weryfikowalność:** każde zadanie ma komendę lub test potwierdzający; bramki CI są wykonywalne lokalnie (PHP + Composer; MySQL tylko dla drugiego joba; Node nie jest wymagany).
- **Rozdzielenie faktów i hipotez:** zachowania runtime opisane w 3.6 zostały zaobserwowane; to, czego nadal nie zweryfikowano (realne możliwości hostingu, wydruk na konkretnej drukarce, zachowanie JSON przy bardzo dużej liczbie zleceń), jest jawnie w ryzykach R1/R2/R14/R15/R17 i w sekcji 2.1.

---

*Koniec planu (wersja 4 — FINALNA: decyzje właściciela, weryfikacja runtime, dwa magazyny danych). Start: Faza 0 (T0.1–T0.6).*



