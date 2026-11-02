# Drukowanie potwierdzenia przyjęcia i protokołu wydania

| | |
|---|---|
| **Status** | zaakceptowana (właściciel, 2026-11-02) |
| **Źródło** | plan `docs/aegis/plans/2026-11-02-przepisanie-aplikacji-serwis.md` |

## Kontekst

Stara aplikacja nie miała żadnego wydruku; klient nie dostawał potwierdzenia.

## Decyzja

Dwa widoki A4 (`@page size: A4; margin: 10mm`): potwierdzenie przyjęcia i protokół wydania. Oba za `auth`, z `window.print()` po otwarciu. Checkbox „wydrukuj potwierdzenie przyjęcia” i „wydrukuj protokół wydania”.

## Konsekwencje

Wydruk zawiera telefon i hasło urządzenia — dlatego nigdy nie jest publiczny. Marginesy i nagłówki przeglądarki to ryzyko R15 (szkolenie „Ctrl+P”).
