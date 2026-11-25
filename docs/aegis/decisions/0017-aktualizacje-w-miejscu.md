# Aktualizacje w miejscu: jedna warstwa asynchroniczna zamiast przeładowań

| | |
|---|---|
| **Status** | zaakceptowana (właściciel, 2026-11-25) |
| **Zastępuje** | punkt 7 decyzji [0016](0016-kolejka-i-wyszukiwanie.md) (osobna trasa `/queue/results`) oraz — w części — mechanizm `data-auto-submit` z punktu 6 decyzji [0015](0015-tresc-i-przeplywy.md) |
| **Źródło** | zlecenie właściciela (2026-11-25): „Sprawdź, co w kodzie można napisać optymalniej, np. tak, aby strona po kliknięciu przycisków się nie przeładowywała za każdym razem, ale działało to asynchronicznie, znajdź też inne elementy strony, które można napisać lepiej i żeby to działało” |

## Kontekst

Po przebudowie kolejki (decyzja 0016) każda czynność na liście kończyła się pełnym
przeładowaniem strony: kliknięcie etapu, paginacja, „Dalej” na kafelku, filtr dat w archiwum,
blokada konta w administracji, dodanie i usunięcie zdjęcia. Skutki były odczuwalne, choć same
żądania są tanie:

- po każdym kliknięciu strona wracała na górę — przy 22 zleceniach to 3 220 px na desktopie
  i 5 158 px na telefonie, czyli kilka ekranów przewijania w miejscu, w którym pracownik
  właśnie stał;
- fokus klawiatury spadał na `<body>`, więc osoba pracująca Tab-em po filtrze zaczynała od
  nagłówka;
- każdy klik to nowy dokument: ponowne wczytanie arkuszy i skryptów (z cache, ale z
  warunkowym odświeżeniem), utrata stanu rozwiniętych `<details>`, migotanie ekranu;
- wyszukiwanie na żywo miało **własną** ścieżkę: osobną trasę `GET /queue/results`, nagłówek
  `X-Queue-Results` jako znacznik i ręczne synchronizowanie ukrytego pola `q` oraz adresu
  „Pokaż wszystkie”. Dwa mechanizmy tej samej listy to dwa miejsca, w których filtr może się
  rozjechać z wynikiem.

## Decyzja

1. **Jedna warstwa asynchroniczna, deklaratywna.** `public/js/app.js` obsługuje wszystkie
   odświeżenia w miejscu; w HTML wystarczą atrybuty:
   - `data-async-region="nazwa"` — kontener, którego treść jest podmieniana;
   - `data-async-form` — formularz (`GET` z pól albo `POST` z `FormData`, z `_method`);
   - `data-async-on-change` — formularz filtrów wysyła się przy zmianie radia/selecta/daty;
   - `data-async-link` — link aktualizujący tę samą listę (paginacja, „Pokaż wszystkie”,
     „Wyczyść zakres”); `data-async-scroll` dodatkowo przewija do odświeżonego regionu.
2. **Serwer renderuje dokładnie tę stronę, którą widzi użytkownik.** Warstwa pobiera **prawdziwy
   adres strony**, parsuje odpowiedź i podmienia każdy region, który występuje w obu
   dokumentach. Nie ma drugiego endpointu, nie ma drugiego renderera i nie ma kopii danych
   w JavaScripcie — źródłem prawdy pozostaje HTML z serwera. Jeśli odpowiedź nie zawiera
   żadnego regionu (formularz logowania, strona błędu), warstwa wykonuje normalną nawigację.
3. **Regiony w aplikacji:** `flash` (layout), `queue-filter` i `queue-results` (kolejka),
   `released-results` i `released-reset` (archiwum), `admin-users` (konta), `service-photos`
   (karta zlecenia). Formularze bez JavaScriptu działają jak dotąd (`method`, `action`,
   `<noscript>`), więc warstwa jest przyspieszeniem, nie warunkiem działania.
4. **Adres, fokus i komunikaty idą za aktualizacją.** Po podmianie regionów adres jest
   aktualizowany przez `history.replaceState` (stan filtra można wysłać koledze i odtworzyć
   odświeżeniem), fokus wraca na to samo pole (radio etapu po nazwie i wartości) albo — gdy
   kliknięty element zniknął z listy — do odświeżonego regionu, a `#async-status`
   (`role="status"`, `aria-live="polite"`) mówi, co się zmieniło. Regiony w trakcie żądania
   mają `aria-busy="true"`, więc czytnik ekranu nie czyta połowy listy.
5. **Koniec z osobną trasą wyszukiwania.** `GET /queue/results` i nagłówek `X-Queue-Results`
   zostały usunięte; pole w nagłówku odświeża `queue-results` tą samą warstwą. Zysk: mniej
   kodu (trasa, akcja, znacznik, dwa atrybuty, ~40 linii ręcznej synchronizacji adresu i pól),
   jeden mechanizm. Koszt: przy wpisywaniu frazy pobierana jest cała strona (kilkanaście kB
   więcej niż sam fragment) — przy sieci lokalnej i 250 ms opóźnieniu nieodczuwalne.
6. **Zapisy nigdy nie są przerywane w locie i są serializowane.** Żądanie `GET` może zostać
   anulowane przez następne (wpisywanie frazy), ale `POST` już wysłany nie jest przerywany,
   a drugi zapis w trakcie pierwszego jest ignorowany — inaczej podwójne kliknięcie „Dalej”
   przestawiłoby status dwa razy.
7. **Nawigacja między ekranami zostaje nawigacją.** Kafelki, menu, logowanie, wydruki i karta
   zlecenia ładują nowy dokument. Router po stronie klienta nie kupiłby tu nic poza ryzykiem:
   formularze z błędami walidacji, osobny layout wydruku, wysyłka plików i semantyka historii
   przeglądarki musiałyby być odtwarzane ręcznie.
8. **Komunikat flash znika sam po 6 sekundach** (tylko sukces i ostrzeżenie; błędy zostają).
   Przy aktualizacji w miejscu stary baner wisiałby nad nowym wynikiem i sugerował, że dotyczy
   właśnie wykonanej czynności.
9. **Kafelek etapu jest przełącznikiem** (`data-filter-toggle`). Ten sam klik, który włącza filtr,
   wyłącza go: kafelek, którego wartość jest w adresie, zdejmuje `status` (i `page`) i odświeża oba
   regiony — fraza `q` zostaje. Adres jest tu źródłem prawdy, a nie właściwość `checked`, bo
   przeglądarka zaznacza radio **przed** wysłaniem `click`. Spacja na wybranym radiu nie wysyła
   w Chrome zdarzenia `click`, więc klawiatura ma tę samą czynność na `keydown`; inaczej filtr
   dałoby się włączyć, ale nie wyłączyć bez sięgania po link. „Pokaż wszystkie” zostaje jako
   widoczna droga wyjścia i jedyna bez JavaScriptu.

## Konsekwencje

- **Mniej przeładowań i mniej utraconego kontekstu**: filtr, paginacja, „Dalej”, blokada konta
  i zdjęcia nie przewijają strony na górę, nie gubią fokusu i nie migoczą.
- **Filtr etapu włącza się i wyłącza tym samym kafelkiem** (także Spacją), a wpisana fraza
  zostaje — zdejmowana jest tylko wartość `status` i numer strony.
- **Jeden mechanizm do utrzymania**: nowa lista dodaje region i atrybut, a nie kolejną funkcję
  w `app.js`. Nie ma już drugiego endpointu do trzymania w testach gościa i w polityce nagłówków.
- **Bez nowych zależności i bez kroku budowania**: nadal vanilla JS w jednym pliku, CSP bez
  `unsafe-inline`.
- **Region musi być wyrenderowany na stronie, która go podmienia.** Brak regionu w odpowiedzi
  oznacza „nie ruszaj”, więc usunięcie go z widoku cicho wyłącza aktualizację w miejscu —
  pilnują tego testy kontraktu znaczników (`QueueTest`, `AdminUsersTest`, `PhotosTest`,
  `ReleasedArchiveTest`).
- **Historia przeglądarki jest upraszczana**: aktualizacje w miejscu robią `replaceState`,
  więc „wstecz” wraca do poprzedniego **ekranu**, nie do poprzedniej strony paginacji.
- **Bez JavaScriptu wszystko działa jak dotąd**, tylko z przeładowaniem.

## Alternatywy

- **Livewire / Turbo / htmx / Alpine** — odrzucone: nowa zależność i (dla części z nich) krok
  budowania albo `unsafe-eval`, przy ~150 liniach, które wystarczają do podmiany regionów.
- **Renderowanie listy po stronie klienta (JSON + szablon)** — odrzucone: dwa renderery tej
  samej listy (kafelek, grupy etapów, paginacja) i pusta lista bez JS.
- **Router SPA dla całej aplikacji** — odrzucone: formularze z błędami walidacji, wydruki,
  wysyłka plików i historia przeglądarki to realne zachowania, których nie warto odtwarzać.
- **Zostawić przeładowania i tylko przewijać do miejsca po reloadzie** — odrzucone: nadal
  migocze, gubi fokus i wysyła ponownie cały dokument.
- **Trzymać `/queue/results` „na wszelki wypadek”** — odrzucone: dokładnie ten dualizm był
  powodem, dla którego filtr i ukryte pole `q` mogły się rozjechać.
