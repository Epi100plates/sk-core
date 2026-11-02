# Ikony PWA generowane z GD

| | |
|---|---|
| **Status** | zaakceptowana (właściciel, 2026-11-02) |
| **Źródło** | plan `docs/aegis/plans/2026-11-02-przepisanie-aplikacji-serwis.md` |

## Kontekst

Stara aplikacja miała jedną ikonę 192 px; brak 512 i maskowalnych, przez co PWA nie była instalowalna.

## Decyzja

`php artisan icons:generate` rysuje ikony 96/192/512 + maskowalne w GD; `icons:check` weryfikuje wymiary (w CI).

## Konsekwencje

Brak zewnętrznych narzędzi i Pythona. Zmiana logo to zmiana jednej komendy.
