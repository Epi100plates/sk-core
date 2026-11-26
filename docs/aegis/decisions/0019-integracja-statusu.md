# 0019 — Status klienta: aplikacja warsztatu synchronizuje zlecenie do CMS

- Status: przyjęte (2026-11-26)
- Kontekst: aplikacja `serwis.mati-it.pl` (Laravel) obsługuje przyjęcie i naprawę;
  publiczna strona statusu, tokeny i kolejka SMS żyją w panelu CMS (`sk.mati-it.pl/panel`,
  SQLite). Do tej pory oba światy nie były połączone: klient dostawał link tylko dla
  zgłoszeń z formularza na stronie.
- Decyzja: aplikacja wysyła podpisane HMAC-em żądanie HTTPS do CMS po każdym przyjęciu
  i zmianie statusu; CMS prowadzi jedno zgłoszenie na zlecenie (`external_ref` = numer
  `SRV-…`), zwraca gotowy link do strony statusu i kolejkuje SMS-y (`client_accepted`,
  `client_ready`). Aplikacja zapisuje zwrócony adres w `services.client_status_url`.
- Konsekwencje:
  - źródłem prawdy o naprawie pozostaje aplikacja; CMS jest warstwą prezentacji i wysyłki,
  - brak sekretu / brak sieci nie blokuje przyjęcia ani wydania — link można utworzyć
    ponownie z karty zlecenia albo komendą `php artisan services:sync-status`,
  - CMS nie renderuje telefonu, opisu usterki ani notatek wewnętrznych; dostaje je tylko
    po to, by mieć poprawny rekord i numer do SMS-a,
  - token pozostaje bezstanowy (w bazie tylko `status_token_version`); nowe linki są
    krótsze (MAC 16 B, base36), stare 43-znakowe nadal działają,
  - adres linku jest konfigurowalny (`status_base_url`), więc przeniesienie na
    `status.mati-it.pl/<token>` nie wymaga zmian w kodzie.
- Odrzucone alternatywy:
  - wspólna baza SQLite dla obu aplikacji — aplikacja działa też na hostingu współdzielonym
    i w trybie JSON, a panel CMS jest jedynym właścicielem bazy,
  - strona statusu w Laravelu — dublowałaby tokeny, widok i kolejkę SMS, które już istnieją
    w CMS (i w `sk-klient`),
  - ręczne przepisywanie numerów — zbyt podatne na błędy i nie daje linku w momencie przyjęcia.
