# Retencja danych: bezterminowa, bez anonimizacji

| | |
|---|---|
| **Status** | zaakceptowana (właściciel, 2026-11-02) |
| **Źródło** | plan `docs/aegis/plans/2026-11-02-przepisanie-aplikacji-serwis.md` |

## Kontekst

Właściciel prowadzi serwis jednoosobowy i potrzebuje pełnej historii napraw.

## Decyzja

Brak komendy anonimizującej i brak automatycznego usuwania. Obowiązki zastępcze: minimalizacja danych i ochrona dostępu (wszystko za `auth`, zdjęcia przez kontroler).

## Konsekwencje

Kopie zapasowe są krytyczne (`backup:database`). Gdyby polityka się zmieniła, `released_at` pozwala dodać retencję bez migracji.
