# Flaga „potwierdzenie przyjęcia wydane”

| | |
|---|---|
| **Status** | zaakceptowana (właściciel, 2026-11-02) |
| **Źródło** | plan `docs/aegis/plans/2026-11-02-przepisanie-aplikacji-serwis.md` |

## Kontekst

Pole `potwierdzenie_odbioru` było zbierane dwa razy i nigdzie nie używane.

## Decyzja

Pole ma nowe znaczenie: `confirmation_issued_at`. Ustawiane przy pierwszym wydruku potwierdzenia (checkbox przy przyjęciu albo „drukuj ponownie” na karcie). Karta pokazuje znacznik „potwierdzenie wydane”. Ponowny wydruk nie zmienia daty.

## Konsekwencje

Import starych danych: `potwierdzenie_odbioru = 1` → `confirmation_issued_at = accepted_at` z ostrzeżeniem w raporcie, bo stare znaczenie było niejasne.
