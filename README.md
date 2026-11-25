# Studio-Komp Serwis

Przepisana aplikacja warsztatu komputerowego: przyjęcie laptopa, kolejka napraw pogrupowana
po etapach, zdjęcia, zmiana statusu, wydanie klientowi, archiwum wydanych, historia zmian,
jedno pole szukania w nagłówku (filtruje kolejkę na żywo) oraz wersja PWA. Następca katalogu
`old-app/` (stara aplikacja PHP, zarchiwizowana i wyłączona z repozytorium).

Plan przepisania i wszystkie decyzje: [`docs/aegis/plans/2026-11-02-przepisanie-aplikacji-serwis.md`](docs/aegis/plans/2026-11-02-przepisanie-aplikacji-serwis.md).
Decyzje architektoniczne: [`docs/aegis/decisions/`](docs/aegis/decisions/).

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
| php artisan backup:database | ZIP z danymi JSON i zdjęciami albo zrzut SQL + zdjęcia |
| php artisan bench:queue [--services=200] | pomiar czasu renderowania kolejki (p95) na danych tymczasowych |

## Bramki jakości

```bash
vendor/bin/pint --test
vendor/bin/phpstan analyse --memory-limit=1G
php artisan test                 # 309 testów: json + mysql
php artisan dusk                 # 17 scenariuszy przeglądarkowych + axe (ADR 0011)
```

Stan na 2026-11-25: **309 testów zielonych w obu trybach magazynu** + **17 scenariuszy Dusk (z axe)**,
Pint czysty, PHPStan poziom 6 bez błędów.
Raport parzystości (F-01…F-57, D-01…D-58): [`docs/aegis/verification/parity-report.md`](docs/aegis/verification/parity-report.md).
Wyrównanie wizualne ze stroną firmową: [docs/aegis/verification/visual-alignment.md](docs/aegis/verification/visual-alignment.md).
Treść, przepływy i bezpieczeństwo (przebudowa ekranów): [docs/aegis/verification/content-and-security-pass.md](docs/aegis/verification/content-and-security-pass.md).
Kolejka, filtr etapów i jedno wyszukiwanie: [docs/aegis/verification/queue-usability-pass.md](docs/aegis/verification/queue-usability-pass.md).
Aktualizacje w miejscu (filtr, paginacja, „Dalej”, zdjęcia): [docs/aegis/verification/async-ui-pass.md](docs/aegis/verification/async-ui-pass.md).

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

## Bezpieczeństwo w skrócie

Hasła bcrypt, wszystkie trasy za `auth`, brak publicznego sprawdzania statusu (decyzja D4),
CSRF na każdej mutacji, throttling loginu 5/15 min, zdjęcia poza webrootem serwowane przez
kontroler z polityką, hasło urządzenia szyfrowane w magazynie, CSP bez `unsafe-inline`,
nagłówki HSTS/nosniff/DENY, dane JSON poza `public/`.
