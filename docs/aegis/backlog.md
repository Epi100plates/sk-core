# Backlog poza v1

Pozycje z planu (B1–B14) z aktualnym stanem realizacji. Nic z tej listy nie blokuje startu.

| ID | Pozycja | Stan w tej iteracji | Orientacyjny koszt |
|---|---|---|---|
| B1 | Kolejka zapisów offline (IndexedDB + Background Sync) z rozwiązywaniem konfliktów | odłożone — offline to tylko powłoka (ADR 0005) | 5–8 dni |
| B2 | Tabela `devices` (ten sam laptop w wielu naprawach, gwarancja) | odłożone | 2–3 dni |
| B3 | PDF do e-maila (dziś druk przez przeglądarkę) | odłożone; wydruki A4 gotowe (F-55) | 1–2 dni |
| B4 | Powiadomienia SMS/e-mail „do odbioru” | odłożone | 2 dni + bramka |
| B5 | Panel statystyk (przyjęcia, wydania, czas naprawy, przychód) | odłożone; dane w magazynie wystarczają | 2–3 dni |
| B6 | Publiczne sprawdzanie statusu przez klienta (telefon + plomba) | świadomie poza v1 (decyzja D4); trasy nie istnieją | 1–2 dni |
| B7 | Ewidencja części i kosztów z magazynem | odłożone | 5–8 dni |
| B8 | Etykiety QR/kreskowe (skan przy przyjęciu i wydaniu) | odłożone | 2–3 dni |
| B9 | Wielu techników z przypisaniem zleceń | odłożone; role przygotowane (D9) | 5–10 dni |
| B10 | Automatyczny backup do chmury (S3/Backblaze) | odłożone; `backup:database` gotowe | 1 dzień |
| B11 | Podpis klienta na ekranie przy wydaniu | odłożone | 2–3 dni |
| B12 | Import danych ze starej aplikacji — uruchomienie, gdy pojawi się zrzut | **kod gotowy i przetestowany na fiksturze** (`legacy:import`, `legacy:verify`) | 0,5 dnia |
| B13 | Eksport zestawienia wydań (CSV/PDF) do rozliczeń | odłożone | 1–2 dni |
| B14 | Przypisanie zlecenia do technika (kolumna + filtr „moje zlecenia”) | odłożone | 1–2 dni |
| B15 | Testy e2e Dusk + axe-core w repo (ADR 0011) | scenariusze opisane, brak plików | 2–3 dni |
| B16 | Livewire 3 zamiast Blade (jeśli właściciel tego wymaga — ADR 0004) | wymaga decyzji i złagodzenia CSP | 4–6 dni |
