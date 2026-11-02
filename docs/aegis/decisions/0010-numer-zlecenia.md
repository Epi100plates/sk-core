# Numer zlecenia: SRV-RRRR-NNNNNN obok id i legacy_id

| | |
|---|---|
| **Status** | zaakceptowana (właściciel, 2026-11-02) |
| **Źródło** | plan `docs/aegis/plans/2026-11-02-przepisanie-aplikacji-serwis.md` |

## Kontekst

Klient nie miał numeru, którym mógłby się posłużyć; numer wiersza w bazie nie nadaje się na wydruk.

## Decyzja

`reference` = `SRV-2026-000123`, unikalny, drukowany i pokazywany w UI. `legacy_id` zachowuje identyfikator ze starej bazy do korespondencji i migracji zdjęć.

## Konsekwencje

Licznik numerów w trybie JSON żyje w `meta.json` (pod tym samym `flock`), w MySQL wynika z maksimum numeru w danym roku plus unikalny indeks. Kolizja przy dwóch jednoczesnych przyjęciach jest możliwa tylko w MySQL i kończy się błędem zapisu.
