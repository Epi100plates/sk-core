# Runbook: kopie zapasowe i odzyskiwanie

Dotyczy decyzji D8 (retencja bezterminowa) i zadania T7.5.

## Co jest kopiowane

| Tryb | Zawartość kopii |
|---|---|
| `json` | ZIP: `data/*.json` (zlecenia, zdarzenia, zdjęcia-meta, konta) + `photos/**` (pliki zdjęć) + `manifest.json` |
| `mysql` | ZIP: `database.sql` (INSERT-y wygenerowane w PHP, bez potrzeby `mysqldump`) + `photos/**` + `manifest.json` |

Zdjęcia zawsze leżą w `storage/app/private/photos/` (poza webrootem), więc są w każdej kopii.

## Wykonanie kopii

```bash
php artisan backup:database
# → storage/app/backups/backup-YYYYMMDD-HHMMSS.zip
```

Kopię można też uruchomić z panelu hostingu (cron lub ręcznie) — komenda nie wymaga SSH.

## Harmonogram (zalecany)

| Kiedy | Co |
|---|---|
| codziennie po zamknięciu | `backup:database`, kopia ściągnięta z hostingu na dysk lokalny |
| co tydzień | kopia trzymana poza hostem (pendrive / chmura właściciela) |
| co kwartał | **próbne odtworzenie** na kopii staging według procedury poniżej |

## Odtworzenie — tryb JSON

1. Zatrzymaj aplikację (tryb serwisowy hostingu lub zmiana `.htaccess`).
2. Rozpakuj ZIP do katalogu tymczasowego.
3. Podmień `storage/app/data` na katalog `data` z archiwum; wgraj `photos` do `storage/app/private/photos`.
4. Sprawdź spójność: `php artisan store:check` (liczby rekordów) i `php artisan photos:verify`.
5. Zdejmij tryb serwisowy, zaloguj się, sprawdź 3 losowe zlecenia i jedno wydanie.

## Odtworzenie — tryb MySQL

1. Zatrzymaj aplikację.
2. `mysql -u … -p baza < database.sql` (albo import przez phpMyAdmin).
3. Wgraj zdjęcia do `storage/app/private/photos`.
4. `php artisan migrate --force` (gdy schemat jest starszy), `php artisan store:check`, `php artisan photos:verify`.

## Czego kopia NIE zawiera

- `.env` (APP_KEY!) — bez tego samego `APP_KEY` zaszyfrowane hasła urządzeń i sesje są nieczytelne.
  **Klucz przechowuj oddzielnie** (menedżer haseł właściciela), inaczej hasła do laptopów przepadną.
- logów aplikacji (nie są potrzebne do odtworzenia danych).
