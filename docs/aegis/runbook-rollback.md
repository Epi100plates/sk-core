# Runbook: rollback (powrót do starej wersji, < 15 min)

Warunek uruchomienia: błąd blokujący pracę warsztatu dłużej niż 30 minut albo utrata danych.

## Wariant 1 — powrót do poprzedniej paczki nowej aplikacji (najczęstszy)

1. Zatrzymaj aplikację (tryb serwisowy hostingu lub `public_html/serwis` → zmiana nazwy katalogu).
2. Wgraj poprzedni ZIP (trzymany przez 7 dni po cutoverze).
3. Przywróć dane: `storage/app` z ostatniej kopii (JSON + zdjęcia) albo import `database.sql`.
4. Zdejmij tryb serwisowy, zaloguj się, sprawdź 3 zlecenia i jedno wydanie.
5. Zapisz w `docs/aegis/incidents/` co się stało (data, objaw, przyczyna, decyzja).

## Wariant 2 — powrót do starej aplikacji PHP

1. Zatrzymaj nową aplikację.
2. Wgraj `backups/2026-11-02-app.zip` do `public_html/serwis`.
3. Jeśli baza była używana: import `backups/db.sql` (phpMyAdmin).
4. Stara aplikacja ma znane defekty (D-01…D-58) — rollback oznacza powrót do nich;
   traktuj go jako działanie awaryjne, nie docelowe.

## Dane zapisane w nowej aplikacji po cutoverze

Nowe zlecenia nie wrócą do starej aplikacji automatycznie. Przed rollbackiem zabezpiecz dane:

- tryb JSON: skopiuj **cały** `storage/app/data/` oraz `storage/app/private/photos/` — to komplet danych,
- tryb MySQL: eksport bazy z panelu/phpMyAdmin + katalog zdjęć.

Po rollbacku ewentualne nowe zlecenia trzeba wprowadzić ręcznie do starej aplikacji.

Dlatego okno cutoveru jest poza godzinami pracy, a rollback testowany jest na staging.

## Ćwiczenie rollbacku (raz na kwartał)

1. Na staging: wgraj aktualną paczkę, utwórz 2 zlecenia, zrób kopię.
2. Wgraj poprzednią paczkę, przywróć kopię.
3. Zmierz czas (cel < 15 min) i zapisz wynik w tym pliku:

| Data | Wersja przed | Wersja po | Czas | Uwagi |
|---|---|---|---|---|
| — | — | — | — | ćwiczenie jeszcze nie wykonane (brak staging u właściciela) |
