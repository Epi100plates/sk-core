# Plan — przebudowa przyjęcia/wydania i status klienta (2026-11-26)

## Cel

Uporządkować codzienną pracę w `serwis.mati-it.pl` (Laravel, `D:\Deepseekapp\serwis`) i połączyć
ją z publiczną stroną statusu z panelu `sk.mati-it.pl/panel`:

1. Kreator przyjęcia: Telefon, model, usterka (wymagane), S/N, hasło, termin (1/2/3/7 dni,
   własna liczba dni, inna data), cena, uwagi, Express, Potwierdzenie.
2. Edycja: te same pola + wszystkie dotychczasowe (status, plomba, koszt, zdjęcia).
3. Protokół wydania: czytelny na 13–14" i telefonie, bez poziomego przewijania.
4. Kolejka: większe filtry etapów, przycisk `Przyjmij` zmienia się w `Kolejka` na ekranie przyjęcia.
5. Status klienta: przy przyjęciu powstaje bezpieczny link, ten sam link jest widoczny w karcie
   zlecenia; zmiana statusu w aplikacji aktualizuje stronę klienta i kolejkuje SMS (gotowość).
6. SMS-y: treści i dostawca konfigurowane wyłącznie w panelu CMS; realna wysyłka nadal w trybie
   testowym do decyzji właściciela.

## Architektura integracji

Aplikacja warsztatu (`serwis.mati-it.pl`, Laravel) jest źródłem prawdy o zleceniu. Publiczna strona
statusu, tokeny i kolejka SMS pozostają w CMS (`sk.mati-it.pl/panel`, SQLite). Łącznikiem jest
podpisane HMAC-em żądanie HTTPS z aplikacji do CMS; aplikacja zapisuje zwrócony adres linku.

```
serwis.mati-it.pl (Laravel)
  POST /api/service-sync.php  -->  sk.mati-it.pl (PHP panelu)
  {reference, phone, device, issue, status, ...}   HMAC-SHA256(secret, timestamp + body)
  <-- {ok, url, number, sms_queued[]}
  zapis: services.client_status_url
  UI: karta zlecenia pokazuje link (kopiuj/otwórz)
```

Mapowanie statusów aplikacji na statusy zgłoszenia CMS:

| aplikacja | CMS `tickets.status` | etap klienta |
|---|---|---|
| pending | new | Przyjęty |
| in_progress | in_progress | W trakcie naprawy |
| awaiting_parts | in_progress | W trakcie naprawy |
| ready_for_pickup | done | Gotowy do wydania (SMS `client_ready`) |
| released | closed | Odebrany |
| cancelled | cancelled (nowy status CMS) | Anulowane |

SMS `client_accepted` jest kolejkowany przy pierwszym utworzeniu zgłoszenia w CMS, `client_ready`
przy pierwszym przejściu na `done`. Treści i włącznik: panel → Ustawienia → Formularz.

Link: `tickets_tracking_token()` pozostaje bezstanowy (w bazie tylko `status_token_version`).
Nowe tokeny są krótsze (MAC 16 B zamiast 32 B, id/wersja w base36); stare 43-znakowe nadal
działają. Bazowy adres linku jest konfigurowalny (`status_base_url`), więc przejście na
`status.mati-it.pl/<token>` to zmiana konfiguracji + vhost, bez zmian w kodzie.

## Bezpieczeństwo

- Sekret tylko w `/srv/sk-serwis/.env` (aplikacja) i `/srv/studio-komp-cms/config.php` (CMS),
  nigdy w repozytorium; brak sekretu = integracja wyłączona, aplikacja działa bez linku.
- Podpis obejmuje znacznik czasu (±300 s) i całe ciało żądania; odrzucane są nieznane pola.
- Zapisywane i wysyłane są tylko dane potrzebne do statusu i SMS-a; strona statusu nie renderuje
  telefonu, usterki ani notatek wewnętrznych.
- Błąd CMS nie blokuje przyjęcia/wydania: zlecenie zostaje zapisane, panel pokazuje ostrzeżenie,
  a link można utworzyć ponownie z karty zlecenia lub komendą `services:sync-status`.

## Podział pracy (agenci)

| Obszar | Właściciel | Pliki |
|---|---|---|
| UI aplikacji (formularze, wydanie, kolejka, karta zlecenia, CSS/JS, wydruk) | `ui-forms` | `resources/views/**`, `public/css/app.css`, `public/css/print.css`, `public/js/app.js` |
| Backend aplikacji (presety terminu, model, migracja, klient HMAC, kontrolery, komenda, testy) | `sync-app` | `app/**`, `config/client-status.php`, `database/migrations/**`, `routes/web.php`, `tests/Feature/ClientStatus*`, `.env.example` |
| CMS (API sync, tokeny krótkie, mapowanie, panel, self-check, dokumentacja) | `cms-sync` | `Piotrek/panel/**`, `Piotrek/docs/**`, `Piotrek/public/tracking/**` (jeśli potrzeba) |
| Integracja, E2E, wdrożenie, git/GitHub/VPS, dokumentacja końcowa | `lead` | reszta |

Umowa widoków: `ServiceController::show()` przekazuje `clientStatusUrl` (string|null),
`clientStatusEnabled` (bool) i `clientStatusNumber` (string|null). Trasa ponowienia:
`POST /services/{service}/client-status` → `services.client-status.store`.

## Kryteria akceptacji

- `php artisan test` zielone (JSON; MySQL, jeśli dostępny), `pint --test`, `phpstan` poziom 6.
- Playwright/curl: brak poziomego przewijania na 390×844, 768×1024, 1280×800, 1366×768, 1440×900.
- Przyjęcie z presetem `2` zapisuje termin +2 dni 15:00; własna data działa; walidacja wymaganych pól.
- Po przyjęciu karta zlecenia pokazuje link; link otwiera stronę statusu z etapem „Przyjęty”.
- Po przejściu na „Do wydania” strona pokazuje „Gotowy do wydania”, a w bazie CMS jest wpis
  `client_ready` (tryb testowy = bez realnej wysyłki).
- CMS API odrzuca zły podpis i nieznany status; powtórzenie tego samego żądania nie dubluje
  zgłoszenia ani SMS-a.
- Wdrożenie: GitHub `sk-core` + VPS `/srv/sk-serwis`; CMS: VPS `/srv/studio-komp` + GitHub
  `sk-serwis`; build Astro tylko lokalnie.
