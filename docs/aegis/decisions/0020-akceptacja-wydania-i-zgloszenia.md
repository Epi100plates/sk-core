# ADR 0020 — Akceptacja wydania i zgłoszenia z formularza

Data: 2026-11-27. Status: przyjęte (zadanie właściciela, lokalne, przed wdrożeniem).

## Kontekst

Właściciel chce, żeby komputer po naprawie nie szedł od razu na stanowisko wydania,
ale najpierw pojawił się u niego jako „oczekujący na akceptację” w zwykłym panelu
edycji, z krótkim przyciskiem akceptacji i możliwością zwrotu do kolejki z uwagami.
Dodatkowo zgłoszenia z formularza strony publicznej (SQLite CMS) mają trafiać do
aplikacji warsztatu, żeby nie trzeba było przepisywać ich ręcznie.

## Decyzje

1. Dwa nowe statusy: `awaiting_approval` („Oczekuje na akceptację”) i `with_remarks`
   („Z uwagami”). `ready_for_pickup` pozostaje stanowiskiem wydania, a samo wydanie
   działa jak dotąd — tylko z tego etapu.
2. Nowa rola `owner` („Szef”) z uprawnieniem `services.approve_release`. Rola
   `admin` zostaje (etykieta „Serwisant admin”, pełny zakres pracy i kont, bez
   akceptacji wydania), więc istniejące konta działają bez migracji danych. Jedno
   konto dostaje rolę Szef komendą `users:owner <login>`.
3. Akceptacja i zwrot są osobnymi akcjami (`ApproveRelease`, `ReturnServiceToQueue`)
   z własnymi zdarzeniami audytu; prośba o wydanie i wznowienie naprawy idą przez
   istniejący przycisk „następny etap”, ale zapisują własne typy zdarzeń.
4. Uprawnienia są egzekwowane w polityce **i** w akcjach (403 dla bezpośredniego POST).
5. Powiadomienia tylko w aplikacji (plakietki, liczniki, znacznik w tytule); brak
   fikcyjnych integracji e-mail/SMS.
6. Zgłoszenia: podpisany HMAC-em endpoint przyjmujący wyłącznie POST, z oknem czasu,
   limitem rozmiaru, throttlingiem i idempotencją po numerze zgłoszenia CMS. Osobna
   skrzynka w obu magazynach danych; nic nie jest usuwane, a przyjęcie do serwisu
   tworzy zwykłe zlecenie i wiąże oba rekordy.

## Konsekwencje

- Wydanie z dowolnego otwartego etapu przestaje być możliwe (wcześniej można było
  ominąć akceptację) — zamierzone, opisane w README.
- Po wdrożeniu na istniejącej instalacji trzeba raz uruchomić `users:owner <login>`;
  bez tego kolejka akceptacji nie ma osoby decyzyjnej (aplikacja działa dalej).
- Nowe kolekcje JSON tworzą się same; MySQL wymaga `migrate --force`
  (`approval_remarks`, tabela `intake_tickets`).
