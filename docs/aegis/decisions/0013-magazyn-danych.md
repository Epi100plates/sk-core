# Wymienny magazyn danych: JSON (domyślnie) albo MySQL

| | |
|---|---|
| **Status** | zaakceptowana (właściciel, 2026-11-02) |
| **Źródło** | plan `docs/aegis/plans/2026-11-02-przepisanie-aplikacji-serwis.md` |

## Kontekst

Bazy jeszcze nie ma; aplikacja musi wystartować na hostingu bez MySQL, a później przejść na bazę bez zmian w kodzie.

## Decyzja

Kontrakty repozytoriów (`Contracts/*Repository`) z dwiema implementacjami: `Infrastructure/Json` (pliki, `flock`, `rename()`, `meta.json`, `schema_version`) i `Infrastructure/Eloquent` (MySQL). Wybór przez `DATA_STORE` w `.env` (`config/persistence.php`). Przełączenie: `store:check`, `migrate --force`, `store:json-to-mysql`. Cały krytyczny zestaw testów działa w obu trybach (dataset `stores`).

## Konsekwencje

Dwa razy więcej testów, ale brak rozjazdu zachowania. Akcje i widoki nie znają źródła danych. Limit komfortu JSON to ~5000 zleceń (`store:check` ostrzega). Zdjęcia zawsze na dysku prywatnym, niezależnie od trybu.
