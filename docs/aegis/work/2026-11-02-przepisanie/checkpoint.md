# Work record: przepisanie aplikacji Studio-Komp Serwis

| | |
|---|---|
| **Cel** | zrealizować plan `docs/aegis/plans/2026-11-02-przepisanie-aplikacji-serwis.md` (F-01…F-57, D-01…D-58, oba magazyny danych, deployment bez SSH) |
| **Start** | 2026-11-02 |
| **Stan** | slice F0–F7 ukończone i zweryfikowane; F8–F10 częściowo (patrz „Pozostało”) |
| **Branch/HEAD** | `main`, pierwszy commit repozytorium |

## Mapa zadań i dowody

| Faza | Zakres | Stan | Dowód |
|---|---|---|---|
| F0 | szkielet Laravel, `config/persistence.php`, `.env.example`, CI, Pint/PHPStan, ADR-y | ✅ | `composer.json`, `.github/workflows/ci.yml`, `docs/aegis/decisions/*` |
| F1 | użytkownicy (JSON+MySQL), instalator, logowanie, layout, panel kont | ✅ | `AuthTest`, `AdminUsersTest`, `CommandsTest` |
| F2 | migracje, JSON infra (`flock`, `rename`, `meta`, `schema_version`), Eloquent, akcje, `store:json-to-mysql`, regresje | ✅ | `JsonStoreTest`, `CommandsTest`, `UpdateServiceTest`, `StatusAndReleaseTest` |
| F3 | kolejka + sortowanie + filtry, formularz przyjęcia, karta, wyszukiwanie, edycja, wydanie, archiwum | ✅ | `QueueTest`, `SearchTest`, `AcceptServiceTest`, `CancelServiceTest`, weryfikacja Chromium |
| F4 | zdjęcia: `PhotoStore` (GD, miniatury, SHA-256), kontroler z polityką, galeria/lightbox, `photos:verify` | ✅ | `PhotosTest`, `runtime-verification.md` |
| F5 | historia, hasło urządzenia, wydruki A4, anulowanie | ✅ | `PrintTest`, `CancelServiceTest`, `FieldLabelsTest` |
| F6 | manifest, ikony GD, service worker, offline, brak PII w cache | ✅ | `SecurityTest`, `runtime-verification.md` (Cache Storage) |
| F7 | nagłówki/CSP, rate limit, audyt IP/UA, kopie zapasowe, maskowanie logów | ✅ | `SecurityTest`, `AuthTest`, `CommandsTest` |
| F8 | parzystość, e2e/a11y | ⚠️ raport parzystości gotowy; **Dusk: 9 scenariuszy zielonych**; axe jeszcze nie uruchomione | `parity-report.md`, `tests/Browser/*` |
| F9 | deployment/runbooki | ⚠️ runbooki i `build-release.ps1` gotowe; brak stagingu właściciela | `runbook-*.md`, `deploy/htaccess-fallback.txt` |
| F10 | dokumentacja, backlog | ✅ | `README.md`, `docs/user-guide.md`, `docs/aegis/backlog.md` |

## Bramki (ostatni przebieg)

```
vendor/bin/pint --test                    PASS (151 plików)
vendor/bin/phpstan analyse                OK (poziom 6, bez błędów)
php artisan test                          254 testy, 915 asercji (json + mysql)
php artisan dusk                          9 scenariuszy, 33 asercje (Chromium 156)
php artisan store:check                   json: OK / mysql: OK
php artisan icons:check                   OK (5 ikon)
php artisan photos:verify                 OK
php artisan backup:database               ZIP z danymi JSON utworzony
```

## Pozostało (kolejne sesje)

1. **axe-core** (F8.3): wstrzyknąć axe do testów Dusk i wyeliminować błędy krytyczne na 5 widokach (Dusk już działa).
2. **Staging właściciela** (F9.1–T9.5): wgranie paczki, `/__install`, smoke test, próba rollbacku.
3. **Import danych**, gdy pojawi się zrzut (`legacy:import` + `legacy:verify`).
4. **Log redaction** (T7.2): procesor Monologa maskujący telefon/hasło, jeśli w logach pojawią się dane.
5. **Decyzja o Livewire** (ADR 0004) i ewentualne złagodzenie CSP.

## Ryzyka aktywne

- R1/R2: realny schemat i możliwości hostingu nadal nieznane (brak dostępu) — runbooki zakładają oba warianty.
- R17: tryb JSON przy dwóch jednoczesnych zapisach — pokryty testem dwuprocesowym; przy >5000 zleceń `store:check` sugeruje MySQL.
- R12: Dusk działa, ale ChromeDriver trzeba instalować ręcznie (binarka win64); `dusk:chrome-driver` nie działa bez CA w PHP CLI.

## Historia (skrót)

- 2026-11-02: szkielet + domena + magazyny JSON/MySQL (F0–F2), 248 testów zielonych w obu trybach.
- 2026-11-02: ekrany pracy, zdjęcia, wydruki, PWA, bezpieczeństwo (F3–F7), weryfikacja w Chromium 1366×768 i 390×844; 6 poprawek UI z przeglądarki (m.in. puste pole „model” w edycji, poziomy scroll na telefonie).
- 2026-11-02: testy Dusk (9 scenariuszy) i 3 poprawki z nich: odstep nad dolna nawigacja na telefonie, usuniete zbedne okno potwierdzenia przy wydaniu, konfigurowalny limit logowania (LOGIN_ATTEMPTS) dla srodowiska testowego.
