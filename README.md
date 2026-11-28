# Studio-Komp Serwis

Przepisana aplikacja warsztatu komputerowego: przyjęcie laptopa, kolejka napraw pogrupowana
po etapach, zdjęcia, zmiana statusu, wydanie klientowi, archiwum wydanych, historia zmian,
jedno pole szukania w nagłówku (filtruje kolejkę na żywo) oraz wersja PWA. Następca katalogu
`old-app/` (stara aplikacja PHP, zarchiwizowana i wyłączona z repozytorium).

Plan przepisania i wszystkie decyzje: [`docs/aegis/plans/2026-11-02-przepisanie-aplikacji-serwis.md`](docs/aegis/plans/2026-11-02-przepisanie-aplikacji-serwis.md).
Decyzje architektoniczne: [`docs/aegis/decisions/`](docs/aegis/decisions/).

## Repozytoria

Kod projektu jest podzielony na trzy publiczne repozytoria GitHub:

| Repozytorium | Zawartość |
|---|---|
| **sk-core** (to repozytorium) | aplikacja warsztatu dla serwisantów: kolejka napraw, zdjęcia, statusy, wydruki A4, PWA (Laravel 12, ten katalog) |
| [sk-serwis](https://github.com/Epi100plates/sk-serwis) | strona serwisu studio-komp.pl + panel CMS + publiczna strona statusu naprawy (`Piotrekv3/`) |
| [sk-klient](https://github.com/Epi100plates/sk-klient) | samodzielna strona statusu dla klienta (czyta publiczny JSON z panelu) |

W tym repozytorium nie ma: `vendor/`, `.env`, `storage/app/data`, `storage/app/photos`,
`old-app/` (stara aplikacja PHP, archiwum) i `Piotrekv3/` (strona i panel CMS).
Wszystko to odtwarza `composer install`, `php artisan app:install` i klon
odpowiedniego repozytorium.

## Stos

- **PHP 8.2+ / Laravel 12** (na maszynie deweloperskiej PHP 8.4.27).
- **Bootstrap 5.3** — pliki CSS/JS leżą w `public/css` i `public/js` (żadnego CDN, żadnego buildu).
- **Bez Node.js i bez Pythona.** W repozytorium nie ma `package.json`, `node_modules` ani `.py`.
- Testy: **Pest** (feature/unit) w dwóch magazynach danych; jakość: **Pint**, **PHPStan 6** (Larastan).

## Warstwa wizualna

Aplikacja używa tokenów marki ze strony **studio-komp.pl** (decyzja
[ADR 0014](docs/aegis/decisions/0014-warstwa-wizualna.md)): grafit `#2d343a`, żółty sygnałowy
`#ffd400`, tło `#f4f5f2`, promień 1 px, włoskowate linie zamiast cieni.

| Element | Gdzie |
|---|---|
| Tokeny, komponenty, siatka | `public/css/app.css` (jeden arkusz; Bootstrap dostarcza tylko grid i utility) |
| Fonty (lokalnie, bez CDN) | `public/fonts`: Schibsted Grotesk (variable) + IBM Plex Mono 400/500, łacina i latin-ext |
| Logo i znak marki | `public/brand`: wersja na ciemnym tle (nagłówek, logowanie) i znak SK (ikony PWA, wydruki) |
| Ikony interfejsu | inline SVG w `resources/views/components/icon.blade.php` (1,5 px kreska, `currentColor`) |
| Wydruki A4 | `public/css/print.css` z tymi samymi tokenami i fontami |

Zmiana CSS/JS wymaga podbicia `ASSET_VERSION` w `.env` — od tego zależą `?v=` w adresach oraz
cache service workera. Wizualne zasady i granice: [`docs/aegis/verification/visual-alignment.md`](docs/aegis/verification/visual-alignment.md).

## Magazyn danych (F-57)

Dane trzyma się w jednym z dwóch magazynów, wybieranym w `.env`:

| Tryb | Kiedy | Co trzeba |
|---|---|---|
| `DATA_STORE=json` (domyślnie) | start bez bazy | zapisywalny `storage/` |
| `DATA_STORE=mysql` | gdy jest baza | `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` |

Przełączenie na bazę: `php artisan store:check` → `php artisan migrate --force` → `php artisan store:json-to-mysql --verify`.

## Szybki start

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan app:install          # tworzy pliki danych i konto admina (hasło pokazane raz)
php artisan serve
```

Dane demonstracyjne (30 zleceń, tylko poza produkcją): `php artisan db:seed --class=DemoSeeder`.

## Komendy

| Komenda | Co robi |
|---|---|
| `php artisan app:install` | inicjalizacja magazynu i konto administratora (bezpieczna wielokrotnie) |
| `php artisan store:check` | aktywny magazyn, ścieżki, liczby rekordów, ostrzeżenie o skali |
| `php artisan store:json-to-mysql [--verify]` | przeniesienie danych z plików JSON do MySQL (idempotentne) |
| `php artisan icons:generate` / `icons:check` | ikony PWA rysowane w GD / weryfikacja wymiarów |
| `php artisan photos:verify [--fix]` | spójność metadanych zdjęć z plikami (kwarantanna, miniatury) |
| `php artisan legacy:import [--dry-run] [--source=] [--uploads=]` | import starej bazy z raportem CSV |
| `php artisan legacy:verify [--source=]` | porównanie liczności i sum po imporcie |
| `php artisan backup:database` | ZIP z danymi JSON i zdjęciami albo zrzut SQL + zdjęcia |
| `php artisan bench:queue [--services=200]` | pomiar czasu renderowania kolejki (p95) na danych tymczasowych |
| `php artisan services:sync-status [--all] [--service=ID]` | brakujące linki statusu klienta w CMS (bezpieczne do powtarzania) |
| `php artisan users:owner <login>` | nadaje istniejącemu kontu rolę Szef (zatwierdza wydanie) |

## Status dla klienta (integracja z CMS)

Aplikacja jest źródłem prawdy o zleceniu. Po przyjęciu i po każdej zmianie statusu
wysyła podpisany HMAC-em snapshot do CMS (`sk.mati-it.pl`), który prowadzi publiczną
stronę `/status/<token>/` i kolejkę SMS. CMS zwraca gotowy link; aplikacja zapisuje
go w `services.client_status_url` i pokazuje na karcie zlecenia (kopiuj / otwórz).
Klient dostaje ten sam link SMS-em (`client_accepted` przy przyjęciu,
`client_ready` przy pierwszym „Do wydania”); treści SMS i tryb testowy są w panelu
CMS → Ustawienia → Formularz.

| Zmienna | Znaczenie |
|---|---|
| `CLIENT_STATUS_ENABLED` | `true` włącza wysyłkę; brak sekretu = integracja wyłączona |
| `CLIENT_STATUS_URL` | bazowy adres CMS; aplikacja dokleja `/api/service-sync.php`. Na VPS: `http://127.0.0.1:8088` (nie przez Cloudflare) |
| `CLIENT_STATUS_SECRET` | ten sam sekret co `service_sync_secret` w `/srv/studio-komp-cms/config.php` |
| `CLIENT_STATUS_TIMEOUT` | sekundy; brak odpowiedzi nie blokuje przyjęcia ani wydania |

Błąd CMS nie blokuje operacji: zlecenie zostaje zapisane, panel pokazuje ostrzeżenie,
a link można utworzyć ponownie z karty zlecenia (`POST /services/{id}/client-status`)
albo komendą `services:sync-status`. Decyzja: [ADR 0019](docs/aegis/decisions/0019-integracja-statusu.md);
pełna dokumentacja strony statusu: `docs/status-klienta.md` w repozytorium `sk-serwis`.

## Akceptacja wydania (Szef i Serwisant admin)

Serwisant kończy naprawę i klika **Gotowy do wydania** (`in_progress` → `awaiting_approval`,
„Oczekuje na akceptację”). Laptop trafia do normalnego panelu edycji z przyciskiem
**Potwierdź**; tylko rola **Szef** (`owner`) może zatwierdzić wydanie albo zwrócić je
do kolejki z krótkimi uwagami (`with_remarks`, „Z uwagami”). Po potwierdzeniu status
zmienia się na `ready_for_pickup` („Do wydania”) i wydanie działa jak dotąd.

| Rola | Etykieta | Zatwierdza wydanie | Uwagi |
|---|---|---|---|
| `owner` | Szef | tak | pełny zakres + konta |
| `admin` | Serwisant admin | nie | pełny zakres pracy + konta (dotychczasowy `admin` działa bez zmian) |
| `technician` | Technik | nie | pełny zakres pracy |
| `employee` | Pracownik | nie | pełny zakres pracy |

Po wdrożeniu na istniejącej instalacji trzeba **raz** nadać jedno konto jako Szef:

```bash
php artisan users:owner admin        # albo inny login właściciela
```

Bez tego nikt nie może zatwierdzić wydania (aplikacja działa, ale kolejka „Oczekuje na
akceptację” nie ma osoby decyzyjnej). Konto `admin` pozostaje w pełni sprawne — zmienia
się tylko jego etykieta i brak prawa akceptacji. Role można też zmienić w panelu
Użytkownicy. Przebieg: historia zmian zapisuje `Zgłoszono do wydania`, `Zatwierdzono
wydanie`, `Zwrócono z uwagami` i `Wznowiono naprawę`. Powiadomienia są w aplikacji
(plakietka przy „Kolejka”, licznik etapów, znacznik w tytule strony); e-mail/push
wymagają osobnej integracji — patrz `docs/aegis/work/2026-11-27-release-approval-and-intake/checkpoint.md`.

## Zgłoszenia z formularza strony publicznej

Formularz na `sk.mati-it.pl` zapisuje zgłoszenie w SQLite CMS, a następnie wysyła je
podpisane do aplikacji. Aplikacja pokazuje je pod „Wydane” → **Zgłoszenia** (z liczbą
nowych) i pozwala oznaczyć kontakt, odrzucić z powodem albo przyjąć do serwisu —
formularz przyjęcia otwiera się wypełniony telefonem, modelem i opisem usterki,
a po zapisaniu zgłoszenie dostaje status „Przyjęte” i link do zlecenia.

Kontrakt (ten sam po stronie CMS):

```text
POST /api/intake-tickets
Content-Type: application/json
X-SK-Timestamp: <unix seconds>
X-SK-Signature: base64url(HMAC-SHA256(secret, timestamp . "\n" . rawBody))

{"number":"SK-2026-000123","name":"Jan","phone":"600100200",
 "device":"Lenovo IdeaPad 5","problem":"...","source_page":"/kontakt/",
 "created_at":"2026-11-27 10:00:00"}

200 {"ok":true,"status":"created"}     # nowe zgłoszenie
200 {"ok":true,"status":"duplicate"}   # ten sam numer, bez drugiego wiersza
400 {"ok":false,"error":"invalid_json"|"invalid_payload"|"payload_too_large"}
401 {"ok":false,"error":"invalid_signature"|"stale_timestamp"}
429                                    # limit żądań
503 {"ok":false,"error":"integration_disabled"}  # pusty INTAKE_SYNC_SECRET
```

Wymagane: `number`, `phone`, `problem`. Idempotencja po `number`. Endpoint jest poza
sesją i CSRF, ma limit rozmiaru ciała, okno czasu i throttling; nie loguje imienia,
telefonu ani treści problemu.

| Zmienna | Znaczenie |
|---|---|
| `INTAKE_SYNC_SECRET` | wspólny sekret HMAC (ten sam w CMS); pusty = endpoint zwraca 503 |
| `INTAKE_SYNC_MAX_BODY` | maksymalny rozmiar ciała w bajtach (domyślnie 20000) |
| `INTAKE_SYNC_CLOCK_SKEW` | dopuszczalna różnica czasu w sekundach (domyślnie 300) |
| `INTAKE_SYNC_RATE_LIMIT` | limit żądań na minutę z jednego IP (domyślnie 60) |

## Bramki jakości

```bash
vendor/bin/pint --test
vendor/bin/phpstan analyse --memory-limit=1G
php artisan test                 # 388 przypadków: json + mysql
php artisan dusk                 # 17 scenariuszy przeglądarkowych + axe (ADR 0011)
```

Stan na 2026-11-27: **249 passed (1004 asercje) lokalnie** — warianty `mysql` są
pomijane, gdy baza nie jest dostępna (136 skipped); CI uruchamia oba magazyny
(388 przypadków). Dusk: **17 passed (70 asercji)**, w tym 6 audytów axe. Pint czysty
(192 pliki), PHPStan poziom 6 bez błędów.
Raport parzystości (F-01…F-57, D-01…D-58): [`docs/aegis/verification/parity-report.md`](docs/aegis/verification/parity-report.md).
Wyrównanie wizualne ze stroną firmową: [docs/aegis/verification/visual-alignment.md](docs/aegis/verification/visual-alignment.md).
Przyjęcie, wydanie i status klienta (2026-11-26): [plan i notatki](docs/aegis/work/2026-11-26-servis-ui-status/plan.md).
Treść, przepływy i bezpieczeństwo (przebudowa ekranów): [docs/aegis/verification/content-and-security-pass.md](docs/aegis/verification/content-and-security-pass.md).
Kolejka, filtr etapów i jedno wyszukiwanie: [docs/aegis/verification/queue-usability-pass.md](docs/aegis/verification/queue-usability-pass.md).
Aktualizacje w miejscu (filtr, paginacja, „Dalej”, zdjęcia): [docs/aegis/verification/async-ui-pass.md](docs/aegis/verification/async-ui-pass.md).
Jeden pasek zamiast nagłówka (szukanie, akcje i filtr etapów w jednej karcie): [ADR 0018](docs/aegis/decisions/0018-jeden-pasek-zamiast-naglowka.md).
Zgłoszenia z formularza + akceptacja wydania (2026-11-27): [notatki i decyzje](docs/aegis/work/2026-11-27-release-approval-and-intake/checkpoint.md), [ADR 0020](docs/aegis/decisions/0020-akceptacja-wydania-i-zgloszenia.md).

### Testy przeglądarkowe (Dusk)

`php artisan dusk` nie uruchamia serwera sam. Potrzebne są dwa okna:

```bash
php artisan serve --host=127.0.0.1 --port=8000      # z DATA_STORE_PATH z .env.dusk.local
php artisan dusk --without-tty
```

ChromeDriver musi być w `vendor/laravel/dusk/bin/chromedriver-win.exe` (pobrany ręcznie
dla wersji Chrome, bo `dusk:chrome-driver` wymaga CA w PHP CLI).

## Wdrożenie na hosting współdzielony

```powershell
pwsh ./build-release.ps1          # ZIP z vendor/, bez .env i danych z dev
```

Potem FTP/panel: rozpakowanie pod `/serwis`, `.env` z `APP_URL`, `INSTALL_TOKEN`, jednorazowo
`/serwis/__install?token=…`, po instalacji `INSTALL_TOKEN=` (puste). Pełna procedura:
[`docs/aegis/runbook-deploy-ftp.md`](docs/aegis/runbook-deploy-ftp.md), cutover i rollback:
[`docs/aegis/runbook-cutover.md`](docs/aegis/runbook-cutover.md), [`docs/aegis/runbook-rollback.md`](docs/aegis/runbook-rollback.md).

## Struktura

```
app/Domain/            statusy, role, telefon, kwoty, workflow, diff audytu
app/Contracts/         interfejsy magazynu danych (JSON albo MySQL)
app/Actions/           jedna operacja zapisu = jedna klasa, transakcyjnie
app/Infrastructure/    implementacje Json (flock, rename) i Eloquent (MySQL)
app/Http/               kontrolery, FormRequesty, middleware (CSP, wymuszona zmiana hasła)
resources/views/       Blade: kolejka, formularze, historia, wydruki A4, panel kont
public/                jedyny webroot: CSS, JS, ikony, service worker
docs/                  plan, ADR-y, runbooki, instrukcja użytkownika, raporty
old-app/               stara aplikacja (archiwum, poza repozytorium i webrootem)
```

## Wdrożenie na VPS (serwis.mati-it.pl)

Aplikacja działa na VPS w `/srv/sk-serwis` (PHP-FPM pool `skserwis`, osobna instancja
nginx `nginx-serwis` na `127.0.0.1:8090`, Cloudflare Tunnel). **Budowanie i testy
wykonują się wyłącznie lokalnie** — na VPS nie ma Composera ani Node.js.

```powershell
php artisan test
powershell -ExecutionPolicy Bypass -File deploy/deploy-vps.ps1          # źródła + kopia + clear cache
powershell -ExecutionPolicy Bypass -File deploy/deploy-vps.ps1 -SkipTests
```

Skrypt pakuje `app/`, `config/`, `database/`, `public/`, `resources/`, `routes/` i pliki
projektu, wysyła przez scp i rozpakowuje w `/srv/sk-serwis`; **nie rusza** `vendor/`,
`storage/`, `.env` ani nie uruchamia composera. Po zmianie CSS/JS podbij
`ASSET_VERSION` w `/srv/sk-serwis/.env` (cache service workera). Wpisy
`CLIENT_STATUS_*` opisano wyżej; przy `DATA_STORE=mysql` uruchom dodatkowo
`php artisan migrate --force` (kolumny statusu klienta i uwag akceptacji są nullable).

Wdrożenie z akceptacją wydania i zgłoszeniami (2026-11-27) wymaga jeszcze:

```bash
php artisan migrate --force            # tylko DATA_STORE=mysql: approval_remarks + intake_tickets
php artisan users:owner admin          # raz: konto właściciela zatwierdza wydanie
# .env: INTAKE_SYNC_SECRET=<ten sam sekret co w CMS>; pusty = endpoint zgłoszeń wyłączony
```

Nowe kolekcje JSON (`intake_tickets.json`) tworzą się same przy pierwszym zapisie
(`store:check` również je dopełnia), więc instalacja na plikach nie wymaga migracji.

## Bezpieczeństwo w skrócie

Hasła bcrypt, wszystkie trasy za `auth`, brak publicznego sprawdzania statusu w samej
aplikacji (decyzja D4) — publiczna strona statusu żyje w CMS i wymaga podpisanego tokenu,
CSRF na każdej mutacji, throttling loginu 5/15 min, zdjęcia poza webrootem serwowane przez
kontroler z polityką, hasło urządzenia szyfrowane w magazynie, CSP bez `unsafe-inline`,
nagłówki HSTS/nosniff/DENY, dane JSON poza `public/`.
