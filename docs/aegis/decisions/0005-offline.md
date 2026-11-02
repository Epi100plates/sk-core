# Offline: tylko powłoka (shell) i blokada zapisów

| | |
|---|---|
| **Status** | zaakceptowana (właściciel, 2026-11-02) |
| **Źródło** | plan `docs/aegis/plans/2026-11-02-przepisanie-aplikacji-serwis.md` |

## Kontekst

Stara aplikacja obiecywała kolejkę zapisów offline, której nigdy nie zaimplementowano. Cache trzymał HTML z telefonami i hasłami klientów.

## Decyzja

Service worker cache’uje wyłącznie CSS/JS/ikony i stronę `/offline`. Wszystkie trasy z danymi klientów są network-only. Offline: baner „brak połączenia”, formularze zablokowane, brak danych klientów w cache. Logout czyści `caches`.

## Konsekwencje

Praca bez zasięgu jest możliwa tylko w trybie odczytu powłoki. Kolejka zapisów offline pozostaje w backlogu (B1).
