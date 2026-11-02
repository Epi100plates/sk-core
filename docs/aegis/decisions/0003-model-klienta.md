# Model klienta: tylko numer telefonu

| | |
|---|---|
| **Status** | zaakceptowana (właściciel, 2026-11-02) |
| **Źródło** | plan `docs/aegis/plans/2026-11-02-przepisanie-aplikacji-serwis.md` |

## Kontekst

Właściciel nie prowadzi kartoteki klientów. W starej aplikacji klient był utożsamiany wyłącznie numerem telefonu, bez normalizacji.

## Decyzja

Bez tabeli `customers`, bez imion. Zlecenie przechowuje `phone` (jak wpisany) oraz `phone_normalized` (E.164) do wyszukiwania.

## Konsekwencje

Ten sam klient z dwoma zleceniami to dwa niezależne rekordy. Wydruk i kontakt korzystają z `phone`, porównania z `phone_normalized`.
