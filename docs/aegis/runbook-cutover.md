# Runbook: okno przełączenia (cutover) — 30–60 minut

Dotyczy zadania T9.4/T9.5. **Nie ma danych do przeniesienia** — aplikacja startuje pusta (tryb JSON).
Jeśli właściciel dostarczy zrzut starej bazy, dochodzi krok 3a (import, poza godzinami pracy).

## T-24 h

1. Kopia starej aplikacji: ZIP plików + (jeśli jest) zrzut bazy; SHA-256 w `backups/CHECKSUMS.txt`.
2. Próba odtworzenia kopii na staging (runbook backup, sekcja „Odtworzenie”).
3. Sprawdzenie staging: `store:check`, logowanie, przyjęcie, wydanie, wydruk.
4. Potwierdzenie listy kont, które mają powstać (admin + pracownicy).

## T-0 (poza godzinami pracy)

1. Wgraj nową paczkę pod `/serwis` (runbook deploy FTP, kroki 2–3).
2. Utwórz `.env` z `INSTALL_TOKEN` i wygenerowanym `APP_KEY`.
3. Inicjalizacja danych: `/serwis/__install?token=…` → zapisz hasło admina.
4. Zmień hasło admina, utwórz konta pracowników (**trzy role**), usuń `INSTALL_TOKEN`.
5. **3a (opcjonalnie, gdy jest zrzut starej bazy):**

   ```bash
   php artisan legacy:import --dry-run --source=stary_dump.sql --uploads=/ścieżka/uploads --report=raport.csv
   # przejrzyj raport.csv (nieznane statusy, telefony do korekty, duplikaty plomb)
   php artisan legacy:import --source=stary_dump.sql --uploads=/ścieżka/uploads
   php artisan legacy:verify --source=stary_dump.sql   # kod wyjścia 0 = zgodne
   ```

6. Smoke test z listy poniżej.
7. Komunikat do pracowników + numer awaryjny. Stara aplikacja zostaje w kopii, **nie w webroocie**.

## Smoke test (5 zleceń)

```text
[ ] logowanie adminem i kontem pracownika
[ ] przyjęcie z 2 zdjęciami i zaznaczonym „wydrukuj potwierdzenie” → wydruk A4 poprawny
[ ] zmiana statusu 2× i widoczna historia
[ ] wydanie z telefonu (390 px) → status „Wydane”, protokół do druku
[ ] wyszukiwanie po telefonie w innym formacie i po plombie
[ ] zdjęcie i plik services.json niedostępne bez logowania
[ ] `php artisan store:check` wskazuje `json`
[ ] brak błędów 500 w logach przez pierwszą godzinę
```

## T+24 h

- Przegląd `storage/logs/laravel-*.log`: brak 500, brak ostrzeżeń o zdjęciach.
- Kopia zapasowa (`backup:database`) i ściągnięcie jej na dysk lokalny.

## T+7 dni

- Archiwizacja starej wersji (tag `legacy-final`, ZIP w `backups/`), usunięcie plików starej
  aplikacji z `public_html/serwis`, zamknięcie fazy.
