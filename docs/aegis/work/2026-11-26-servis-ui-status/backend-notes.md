# Backend aplikacji — notatki wdrożeniowe (2026-11-26)

Właściciel: `sync-app` (zadanie `task-2`). Zakres: presety terminu, pola linku statusu, klient
HMAC do CMS, wywołania w kontrolerach, komenda, testy. Widoki/CSS/JS należą do `ui-forms`.

## 1. Presety terminu (`ServiceRequest`)

- `planned_days`: `nullable|integer|between:1,365` (UI wysyła 1/2/3/7 albo własną liczbę dni),
- `planned_at`: przy `POST` `required_without:planned_days|date`, przy `PUT` `nullable|date`
  (częściowa edycja nie może wymuszać terminu),
- `prepareForValidation()` zamienia liczbę dni na wall clock: `now(display_timezone) + N dni`,
  godzina 15:00, format `Y-m-d\TH:i` — dokładnie tak jak `defaultPlannedAt()`. Preset wygrywa
  z nieaktualnym ukrytym `planned_at` (test: „prefers the preset over a stale hidden date field”),
- `planned_days` jest usuwany w `validatedDetails()`, więc `ServiceDetails`/`UpdateService` nie
  wymagały żadnych zmian,
- `planned_mode` (JS) nie ma reguły walidacji i nigdy nie trafia do `validatedDetails()`.

Wartości spoza zakresu (0, 366, −1, `abc`) dają błąd walidacji `planned_days`; przy wartości
niepoprawnej `planned_at` nie jest wyliczany, więc nic nie trafia do bazy.

## 2. Link statusu klienta w rekordzie

- `ServiceRecord`: `?string $clientStatusUrl`, `?CarbonImmutable $clientStatusSyncedAt` — na końcu
  konstruktora z defaultami `null`, klucze `client_status_url` / `client_status_synced_at`,
- `Service` (Eloquent): `$fillable` + cast `client_status_synced_at => datetime`,
- migracja `2026_11_26_000001_add_client_status_link_to_services_table.php`:
  `client_status_url` string(255) nullable i `client_status_synced_at` timestamp nullable,
  oba po `seal_number`,
- magazyn JSON nie potrzebuje migracji (nowe klucze po prostu pojawiają się w `services.json`).

## 3. Kontrakt żądania do CMS (`App\Support\ClientStatus\ClientStatusSync`)

`POST {url}/api/service-sync.php`, ciało = dokładnie 10 kluczy (CMS odrzuca nadmiarowe):

```json
{
  "reference": "SRV-2026-000123",
  "phone": "505 123 456",
  "device": "Dell Latitude 5420",
  "issue": "Nie ładuje się. (ucięte do 2000 znaków)",
  "status": "ready_for_pickup",
  "planned_at": "2026-11-28 15:00:00",
  "accepted_at": "2026-11-26 10:15:00",
  "express": false,
  "event": "status_changed",
  "note": ""
}
```

Nagłówki:

```
X-SK-Timestamp: 1795684500
X-SK-Signature: base64url(HMAC-SHA256(secret, timestamp."\n".rawBody))   # bez '='
Accept: application/json
Content-Type: application/json
```

- `planned_at`/`accepted_at` to wall clock w `app.display_timezone` (`Y-m-d H:i:s`),
- `issue` jest ucinane `Str::limit(..., 2000, '')` — w aplikacji pole ma 5000 znaków,
- `event`: `accepted` (przyjęcie), `status_changed` (zmiana etapu, ręczne ponowienie, komenda),
  `released` (wydanie), `cancelled` (anulowanie) — tylko te wartości,
- odpowiedź 200 z `{"ok":true,"url":"https://…"}` → `client_status_url` + `client_status_synced_at`
  zapisywane przez `ServiceRepository::save()` na rekordzie odczytanym ponownie (świeża wersja,
  brak konfliktu optymistycznego po transakcji akcji),
- każdy inny wynik (timeout, 5xx, brak `url`, błąd zapisu) → `Log::warning('client-status sync failed',
  ['reference' => …, 'reason' => …])` **bez telefonu i treści usterki** i `ok=false`; wyjątek nigdy
  nie leci do użytkownika.

Konfiguracja (`config/client-status.php`): `CLIENT_STATUS_ENABLED`, `CLIENT_STATUS_URL`
(default `https://sk.mati-it.pl`), `CLIENT_STATUS_SECRET`, `CLIENT_STATUS_TIMEOUT` (5 s).
Sekret pusty = integracja wyłączona: żadnego żądania, żadnego ostrzeżenia w UI.

## 4. Miejsca wywołania

| Miejsce | event | Zachowanie przy błędzie |
|---|---|---|
| `ServiceController@store` (po zdjęciach i potwierdzeniu) | `accepted` | flash `warning`, przekierowanie bez zmian |
| `ServiceController@update` (tylko gdy status się zmienił) | `status_changed` | flash `warning` |
| `AdvanceStatusController` | `status_changed` | flash `warning` (JSON bez zmian) |
| `ReleaseController@store` | `released` | flash `warning` |
| `CancelController` | `cancelled` | flash `warning` |
| `POST /services/{service}/client-status` (`services.client-status.store`) | `status_changed` | flash `warning` z powodem |
| `php artisan services:sync-status` | `status_changed` | podsumowanie + kod wyjścia 1 |

`ServiceController@show` przekazuje do widoku: `clientStatusUrl` (string|null),
`clientStatusEnabled` (bool), `clientStatusNumber` (na razie zawsze `null` — CMS zwraca numer
zgłoszenia, ale aplikacja go nie zapisuje).

## 5. Komenda `services:sync-status`

- bez opcji: tylko aktywne zlecenia bez `client_status_url`,
- `--all`: wszystkie zlecenia,
- `--service=ID`: pojedyncze zlecenie,
- wypisuje `SRV-…: <url>` lub `SRV-…: <powód>` i podsumowanie `Wysłano: n, pominięto: n, błędy: n`;
  nie loguje danych osobowych (tylko numer zlecenia).

## 6. Znane ograniczenia

- ręczne ponowienie z karty używa uprawnienia `update`, więc dla zlecenia wydanego/anulowanego
  odpowiada 403 — takie rekordy wysyła tylko komenda (świadoma decyzja: UI nie ma akcji dla
  zamkniętych zleceń),
- numer zgłoszenia z CMS nie jest zapisywany (brak kolumny) — `clientStatusNumber` = `null`
  do czasu, aż będzie potrzebny w UI,
- wariant MySQL (migracja + casty) nie został wykonany na tej maszynie: brak serwera MariaDB
  (`127.0.0.1:3306` zamknięty, brak `pdo_sqlite`), więc testy `mysql` są pomijane. Na wdrożeniu
  trzeba uruchomić `php artisan migrate --force`; kolumny są nullable, więc migracja jest bezpieczna
  dla istniejących danych.
