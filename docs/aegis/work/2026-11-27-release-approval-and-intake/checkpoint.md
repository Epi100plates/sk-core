# 2026-11-27 — Zgłoszenia z formularza + akceptacja wydania

Zakres: zadanie właściciela (`servicepanel`). Gałąź robocza lokalna, bez wdrożenia.

## Zrobione

1. **Formularz przyjęcia** — usunięty podpis „Dni robocze (pon.–pt.); presety kończą się
   o 15:00.” (`components/service-form.blade.php`).
2. **Status dla klienta** — karta z prawej kolumny zastąpiona małym kafelkiem
   (`services/show.blade.php`): zapisany link + „Podgląd”, bez akapitów, bez
   odświeżania/tworzenia linku. Gdy linku nie ma, zostaje jedna cicha linia.
3. **Akceptacja wydania** — nowe statusy, rola `owner` („Szef”), akcje, polityka,
   zdarzenia audytu, plakietki w aplikacji, testy.
4. **Zgłoszenia z formularza** — podpisany endpoint HMAC, skrzynka JSON + MySQL,
   lista/szczegóły/filtry, kontakt/odrzucenie/przyjęcie do serwisu.
5. README, `.env.example`, migracje MySQL, ADR 0020, testy.

## Model statusów

```
pending ──► in_progress ──► awaiting_approval ──► ready_for_pickup ──► released
                 ▲                 │
                 │                 └──► with_remarks ──► in_progress (wznowienie)
                 └──────── awaiting_parts ──► in_progress
```

- `awaiting_approval` = „Oczekuje na akceptację” — serwisant kliknął **Gotowy do wydania**
  (`services.advance`), powstaje zdarzenie `release_requested`.
- `with_remarks` = „Z uwagami” — Szef zwrócił do kolejki; uwagi w polu
  `services.approval_remarks` i w historii (`returned_with_remarks`), widoczne na karcie
  i w panelu edycji.
- `ready_for_pickup` = „Do wydania” — Szef kliknął **Potwierdź**
  (`POST /services/{id}/release-approval`), zdarzenie `release_approved`.
- Wznowienie: `with_remarks` → `in_progress` przez zwykły przycisk **Wznów naprawę**
  (zdarzenie `resumed_repair`).
- Wydanie: tylko z `ready_for_pickup` (tak jak dotąd; akcja release odrzuca inne etapy
  błędem 422, więc nie da się ominąć akceptacji).

## Role

| Wartość | Etykieta | Approve | Uwagi |
|---|---|---|---|
| `owner` | Szef | tak | pełny zakres + konta |
| `admin` | Serwisant admin | nie | pełny zakres + konta; dotychczasowe konto `admin` działa bez zmian |
| `technician` | Technik | nie | pełny zakres pracy |
| `employee` | Pracownik | nie | pełny zakres pracy |

Migracja istniejącej instalacji: **raz** `php artisan users:owner <login>` (bez tego
nie ma osoby mogącej zatwierdzić wydanie). Świeża instalacja tworzy konto `admin`
(Serwisant admin) — właściciel nadaje sobie rolę Szef komendą lub w panelu Użytkownicy.
Brak automatycznej zmiany ról w danych: świadomie, żeby nie odebrać nikomu dostępu.

## Uprawnienia

- `Ability::APPROVE_RELEASE` — tylko `owner`. Polityka `ServicePolicy::approveRelease`
  i `returnToQueue` sprawdzają prawo + etap; akcje `ApproveRelease` /
  `ReturnServiceToQueue` powtarzają kontrolę i rzucają 403. Bezpośredni POST przez
  technika/admina = 403 (test).
- Technik/pracownik nie widzi przycisków akceptacji w panelu edycji ani na karcie.

## Powiadomienia (in-app)

- Plakietka przy „Kolejka”: Szef widzi liczbę `awaiting_approval`, pozostali liczbę
  `with_remarks`.
- Znacznik w tytule strony (`N do akceptacji` / `N z uwagami`).
- Liczniki etapów w filtrze kolejki + grupy na liście.
- **Brak** e-mail/SMS/push — do włączenia trzeba kolejki powiadomień i dostawcy
  (analogicznie do `notifications` w CMS); interfejs jest gotowy na podpięcie.

## Zgłoszenia — kontrakt i bezpieczeństwo

`POST /api/intake-tickets` (poza sesją i CSRF, `throttle:intake`):

```
X-SK-Timestamp: <unix seconds>
X-SK-Signature: base64url(HMAC-SHA256(secret, timestamp."\n".rawBody))
{"number","name","phone","device","problem","source_page","created_at"}
```

- 200 `{"ok":true,"status":"created"|"duplicate"}`, 400 `invalid_json`/`invalid_payload`/
  `payload_too_large`, 401 `invalid_signature`/`stale_timestamp`, 429 limit, 503
  `integration_disabled` przy pustym `INTAKE_SYNC_SECRET`.
- Idempotencja po `number` (CMS-owy numer zgłoszenia); duplikat nie tworzy wiersza.
- Limit ciała (`INTAKE_SYNC_MAX_BODY`), okno czasu (`INTAKE_SYNC_CLOCK_SKEW`), limit
  żądań na IP (`INTAKE_SYNC_RATE_LIMIT`). Brak logowania imienia/telefonu/treści;
  błędy walidacji zwracają tylko nazwy pól. Test `spy` na `Log` potwierdza brak logów.
- Skrzynka: statusy `new/contacted/converted/rejected`, nic nie jest usuwane;
  przyjęcie do serwisu tworzy zlecenie przez zwykły formularz i dopisuje
  `converted_service_id`/`converted_service_reference`.
- UI: „Wydane” → „Zgłoszenia” (badge nowych), lista z filtrami i szukaniem (od 3 znaków),
  szczegóły z akcjami: Kontakt / Odrzuć (powód wymagany) / Przyjmij do serwisu.

## Decyzje i założenia

- `created_at` z CMS jest czasem lokalnym (Europe/Warsaw); aplikacja zapisuje UTC.
- Telefon ze zgłoszenia jest zapisywany bez normalizacji (do formularza trafia taki,
  jaki przyszedł); formularz przyjęcia i tak normalizuje go przy zapisie zlecenia.
- Uwagi Szefa zostają na zleceniu do akceptacji wydania (wtedy są czyszczone).
- Release z innych etapów jest teraz odrzucany (422) — wcześniej można było wydać
  z dowolnego otwartego etapu; to była furtka omijająca akceptację.
- Odrzuconego zgłoszenia nie można przyjąć do serwisu (komunikat, nie cichy błąd).

## Bramki

- `php -l` — 190 plików PHP bez błędów.
- `vendor/bin/pint --test` — PASS (192 pliki).
- `vendor/bin/phpstan analyse --memory-limit=1G` — poziom 6, bez błędów.
- `php artisan test` — 249 passed (1004 asercje), 136 skipped (warianty `mysql`, brak bazy),
  3 porażki przedwczesne/istniejące wcześniej (AcceptServiceTest domyślny termin vs dni
  robocze, QueueTest — tekst z paska bocznego, UpdateServiceTest — nieaktualny napis
  „Edycja zlecenia”); żadna nie dotyczy zmienionych funkcji.
- Nowe testy: `tests/Feature/ReleaseApprovalTest.php` (13 scenariuszy × magazyny),
  `tests/Feature/IntakeTest.php` (17 scenariuszy), zaktualizowane testy statusów i ról.

Deploy: `php artisan migrate --force` (MySQL), `php artisan users:owner <login>`,
`INTAKE_SYNC_SECRET` w `.env`. Nie wdrażano na VPS.
