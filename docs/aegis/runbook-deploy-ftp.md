# Runbook: wdrożenie przez FTP/panel (bez SSH, bez Composera na serwerze)

Dotyczy decyzji D2 i zadania T9.2. Aplikacja działa pod `https://epii.pl/serwis`.

## 1. Zbuduj paczkę na maszynie deweloperskiej

```powershell
pwsh ./build-release.ps1
# → release-YYYYMMDD-HHmm.zip (zawiera vendor/, bez .env i bez danych z dev)
```

Skrypt robi: `composer install --no-dev --optimize-autoloader`, `config:cache`, `route:cache`,
`view:cache`, `icons:generate`, czyszczenie `storage/` z danych deweloperskich.

## 2. Wgraj na hosting

**Wariant A (zalecany, gdy hosting pozwala):** rozpakuj ZIP do `~/serwis-app/` i podłącz
katalog `public/` jako webroot `public_html/serwis` (symlink lub ustawienie domeny/podkatalogu).

**Wariant B (cała aplikacja w webroocie):** rozpakuj do `public_html/serwis/`, a następnie
skopiuj `deploy-htaccess.txt` jako `.htaccess` do `public_html/serwis/` (blokuje `.env`,
`storage/`, `vendor/`, `bootstrap/`, `database/`, `tests/`, `docs/`).

## 3. Utwórz `.env` na serwerze

Na podstawie `.env.example`:

```dotenv
APP_NAME="Studio-Komp Serwis"
APP_ENV=production
APP_DEBUG=false
APP_KEY=                       # php artisan key:generate lokalnie i wklej wynik
APP_URL=https://epii.pl/serwis
APP_TIMEZONE=UTC
APP_DISPLAY_TIMEZONE=Europe/Warsaw

DATA_STORE=json                # start bez bazy
INSTALL_TOKEN=losowy-dlugi-token
ASSET_VERSION=1                # podbijaj przy każdym wdrożeniu

SESSION_DRIVER=file
SESSION_LIFETIME=120
QUEUE_CONNECTION=sync
CACHE_STORE=file
LOG_CHANNEL=daily
LOG_LEVEL=info
```

Po włączeniu MySQL: `DATA_STORE=mysql` + `DB_*`, potem `migrate --force` (patrz runbook cutover).

## 4. Zainicjalizuj dane (raz)

Wejdź na `https://epii.pl/serwis/__install?token=<INSTALL_TOKEN>`:

- tryb `json`: tworzy `storage/app/data/*.json` i konto `admin` (hasło pokazane **raz**),
- tryb `mysql`: uruchamia migracje i tworzy konto `admin`.

Następnie:

1. zaloguj się, ustaw nowe hasło (aplikacja wymusi zmianę),
2. **usuń token**: `INSTALL_TOKEN=` (puste) — route `/__install` zwróci 404,
3. utwórz konta pracowników w panelu **Użytkownicy**.

## 5. Sprawdź po wdrożeniu

```text
[ ] https://epii.pl/serwis/login                     → formularz logowania
[ ] https://epii.pl/serwis/manifest.webmanifest      → 200, JSON
[ ] https://epii.pl/serwis/icons/icon-512.png        → 200
[ ] https://epii.pl/serwis/storage/app/data/services.json → 403/404
[ ] https://epii.pl/serwis/.env                      → 403/404
[ ] https://epii.pl/serwis/__install                 → 404
[ ] logowanie każdym kontem, przyjęcie i wydanie testowego zlecenia
[ ] `php artisan store:check` (lokalnie ze wskazaniem na katalog hostingu) lub panel
```

## 6. Uprawnienia

| Katalog/plik | Uprawnienia |
|---|---|
| `storage/`, `bootstrap/cache/` | zapisywalne dla PHP (755/775) |
| `storage/app/data` (JSON) | **0600 pliki / 0700 katalog** — dane klientów |
| `.env` | 0600, poza webrootem lub zablokowany |

## 7. Aktualizacja aplikacji

1. Nowy ZIP z podbitym `ASSET_VERSION` (klienci odświeżą cache PWA).
2. Kopia `storage/app` (lub bazy) przed podmianą.
3. Wgranie plików **bez** nadpisywania `storage/` i `.env`.
4. `php artisan config:cache` nie jest potrzebny (cache jest w paczce), ale po zmianie `.env`
   usuń `bootstrap/cache/config.php` i wejdź na stronę, aby go odtworzyć — jeśli hosting nie ma SSH,
   usuń plik przez panel FTP.
