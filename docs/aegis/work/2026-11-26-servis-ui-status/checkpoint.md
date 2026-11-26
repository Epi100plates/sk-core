# Checkpoint — przyjęcie, wydanie i status klienta (2026-11-26)

## Zlecenie

Przebudowa modułu dodawania/edycji laptopa w `serwis.mati-it.pl` w stylu strony firmowej,
responsywna na PC/laptopie/telefonie, przebudowany protokół wydania, większe filtry kolejki,
przycisk „Przyjmij” → „Kolejka”, synchronizacja statusu klienta z bazą i SMS-y konfigurowane
w panelu `sk.mati-it.pl/panel`. Praca rozdzielona na trzy subagentów (`ui-forms`, `sync-app`,
`cms-sync`); integracja, wdrożenie i weryfikacja po stronie leada.

## Co zostało zrobione

| Obszar | Wynik |
|---|---|
| Kreator przyjęcia | 4 sekcje, wymagane telefon/model/usterka, S/N, hasło, termin 1/2/3/7 dni + własna liczba dni + inna data, cena, koszt, plomba, uwagi, Express, Potwierdzenie; podgląd terminu (SSR + JS); pasek akcji w przepływie na desktopie, sticky nad dolną nawigacją na telefonie |
| Edycja | te same sekcje + status, plomba, koszt, zdjęcia; start w trybie „inna data”, żeby zapis bez zmian nie przesuwał terminu |
| Wydanie | podsumowanie (żółty docket) w kolumnie, formularz 2-kolumnowy od 1200 px; na 1366×768 i 1280×800 całość z przyciskiem widoczna bez poziomego scrolla; protokół A4 ~58% wysokości strony |
| Kolejka | chipy etapów 44 px, obrys 1 px, zaznaczenie tłem `--ink` + żółta etykieta; brak pionowych linii tabeli; 44 px touch |
| Przycisk | na `services.create` górny przycisk zmienia się w „Kolejka” (także na telefonie po korekcie leada), powrót jednym kliknięciem |
| Karta zlecenia | sekcja „Status dla klienta”: link, Kopiuj, Otwórz, czas ostatniej synchronizacji, „Odśwież status” (ponawia sync także wtedy, gdy link już istnieje), ręczne „Utwórz link statusu” przy błędzie, informacja o SMS-ach |
| Backend aplikacji | `planned_days` (1–365) → `planned_at` 15:00; `client_status_url`/`client_status_synced_at` w JSON i MySQL (migracja); klient HMAC; sync po accept/update/advance/release/cancel; `services:sync-status`; testy Pest |
| CMS | `POST /api/service-sync.php` (HMAC, timestamp ±300 s, limit 60/min, idempotencja `external_ref`), mapowanie statusów, `client_accepted`/`client_ready`, status `cancelled`, krótkie tokeny (base36 + 16 B MAC), zgodność starych 43-znakowych, `status_base_url`, badge „Z serwisu”, self-check 151 warunków |
| Dokumentacja | ADR 0019, plan + notatki UI/backend, README, instrukcja użytkownika, `docs/status-klienta.md` §11, AGENTS.md (L.7, historia) |

## Weryfikacja lokalna (dokładne wyniki)

```
php artisan test                 234 passed, 123 skipped (893 asercje), exit 0
                                 (skip = warianty mysql bez serwera MariaDB)
php artisan dusk                 17 passed (70 asercji), w tym 6 audytów axe, exit 0
vendor/bin/pint --test           PASS (166 plików)
vendor/bin/phpstan analyse       [OK] No errors (poziom 6)
php artisan view:cache           Blade templates cached successfully
axe-core 4.10 (UI agent)         0 naruszeń: create 1366/390, release 1366/390, show 390, queue 1366
scrollWidth === clientWidth      queue/create/edit/release/show/print: 390, 768, 1280, 1366, 1440, 1920
```

E2E lokalny (prawdziwy CMS + aplikacja, nie atrapy HTTP): przyjęcie z presetem 7 dni → link
w karcie zlecenia → strona statusu „Przyjęty”; przejście na „Do wydania” → „Gotowy do wydania”
+ `client_ready` w kolejce; wydanie → „Odebrany”; anulowanie → etap „Anulowane”. Test
idempotencji, złego podpisu (401) i nieznanego statusu (400) przeszedł.

Self-checki CMS (lokalnie i na VPS): `75/75` (`self-check-status.php`) i `151/151`
(`self-check-service-sync.php`).

## Wdrożenie (2026-11-26)

| Element | Stan |
|---|---|
| Aplikacja `sk-core` | commity `27970b6`, `d3c88e1` (odświeżanie linku), wypchnięte na GitHub; VPS `/srv/sk-serwis` przez `deploy/deploy-vps.ps1` (źródła + kopia, bez composera i builda na VPS) |
| CMS `sk-serwis` | commit `70b5c12`, wypchnięty na VPS (`/srv/studio-komp`) i GitHub (`_exports/studio-komp-www`) |
| nginx-sk | `location = /api/service-sync.php` (tylko POST, 16k, `access_log off`); `nginx -t` OK, `systemctl reload nginx-sk` |
| Sekrety | `service_sync_secret` w `/srv/studio-komp-cms/config.php` = `CLIENT_STATUS_SECRET` w `/srv/sk-serwis/.env`; wartość poza repozytorium |
| `.env` aplikacji | `CLIENT_STATUS_ENABLED=true`, `CLIENT_STATUS_URL=http://127.0.0.1:8088`, timeout 5 s, `ASSET_VERSION=33` |
| Backfill | `php artisan services:sync-status` na VPS: 12 linków, 3 pominięte (wydane/anulowane), 0 błędów |
| Baza CMS | 12 zgłoszeń `SRV-2026-000001…000012` z `external_ref`; `client_accepted`/test ×12, `client_ready`/test ×3 (tryb testowy, bez realnej wysyłki) |
| Strona statusu | publiczny `GET /status/<token>/?format=json` i HTML: 200, `Cache-Control: no-store`, `X-Robots-Tag: noindex`, brak danych osobowych |
| App na żywo | `https://serwis.mati-it.pl/` 200; `app.css?v=32`, `app.js?v=32` |

Przykładowy link ma postać `https://sk.mati-it.pl/status/<krótki-token>/` (token jest sekretem,
dlatego w dokumentacji nie podajemy prawdziwych adresów klientów).

## Ograniczenia i następne kroki

1. **Realna wysyłka SMS** — wszystko działa w trybie testowym; potrzebne konto u dostawcy,
   token, nadawca i wyłączenie trybu testowego w panelu (Ustawienia → Formularz).
2. **`status.mati-it.pl`** — `status_base_url` jest gotowe, ale domena nie istnieje; potrzebny
   DNS + vhost + wpis w Cloudflare Tunnel. Do tego czasu linki są na `sk.mati-it.pl/status/...`.
3. **MySQL** — lokalnie brak MariaDB, więc wariant `mysql` sprawdza CI; migracja dodaje dwie
   nullable kolumny, bezpieczna dla istniejących danych.
4. **Numer zgłoszenia CMS** nie jest zapisywany w aplikacji (`clientStatusNumber = null`);
   link jest wystarczający, numer można dodać później.
5. **Ręczne ponowienie** linku działa dla zleceń otwartych; zamknięte wysyła komenda
   `services:sync-status --all`.
6. **Pasek mobilny** ma teraz zarówno „Kolejka” w górze, jak i dolną nawigację; jeśli to
   redundantne, można ukryć górny przycisk poniżej 576 px (jedna reguła CSS).

## Propozycje UX (szczegóły w `ui-notes.md`)

1. Jeden przycisk „Przyjmij i wydrukuj” obok „Przyjmij zlecenie”.
2. Skaner kodów w polach S/N i plomba (fokus po odczycie).
3. Szybka zmiana etapu z kafelka kolejki (drugi, cichy przycisk „Do wydania”).
4. Filtr historii zmian (statusy / pieniądze / zdjęcia).
5. Pamięć ostatniego presetu terminu w `localStorage`.
6. Podgląd „jak widzi to klient” w modalu zamiast nowej karty.
