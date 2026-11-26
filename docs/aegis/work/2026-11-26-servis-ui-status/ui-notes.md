# UI: przyjęcie, edycja, wydanie, kolejka, karta zlecenia — notatka z przebudowy

| | |
|---|---|
| **Data** | 2026-11-26 |
| **Zakres** | `services.create`, `services.edit`, `services.release`, `services.queue`, `services.show`, wydruk protokołu wydania |
| **Plan** | [plan.md](plan.md) |
| **Właściciel** | `ui-forms` (widoki + `public/css`, `public/js`) |

## Decyzje

### 1. Formularz przyjęcia czyta się jak dokument, nie jak siatka pól

Cztery sekcje z nagłówkiem mono i włoskowatą linią (`Klient i sprzęt`, `Usterka i termin`,
`Rozliczenie`, `Zdjęcia i wydruk`; w edycji dochodzi `Status zlecenia`). Wszystkie sekcje są
otwarte — nic, co potrzebne przy kliencie, nie chowa się za kliknięciem. Nagłówek sekcji używa
istniejącego języka marki (`.queue-group__head`: mono, 11 px, uppercase, linia do prawej), więc
formularz nie wprowadza nowego stylu, tylko porządkuje obecny.

### 2. Termin: jeden serwerowy `planned_days`, trzy sposoby wyboru

Radio `planned_mode` (1 / 2 / 3 / 7 / własna liczba dni / inna data) decyduje tylko, **które**
pole jedzie do serwera:

| tryb | co leci do serwera | co jest wyłączone |
|---|---|---|
| preset 1/2/3/7 | `planned_days` = 1/2/3/7 | `planned_at` (`disabled`), więc nie blokuje submitu |
| własna liczba dni | `planned_days` = 1–365 z pola liczbowego | `planned_at` |
| inna data | `planned_at` (wymagane) | `planned_days` (`disabled`) |

`planned_days` jest **zawsze w DOM** jako ukryte pole z wartością (domyślnie 2), więc bez JS
działa domyślny preset 2 dni: serwer dostaje sam `planned_days` i liczy +2 dni na 15:00.
`planned_mode` nie jest znany serwerowi (wypada w `validated()`). Podgląd „Termin: sob 28.11,
15:00" jest renderowany także po stronie serwera (dla presetu z `now()->addDays()->setTime(15,0)`,
dla „innej daty" z `planned_at`), a `public/js/app.js` (`setupDeadline`) przelicza go na żywo.

Edycja startuje w trybie „inna data": zapisana godzina jest faktem i nie może się przesunąć,
gdy ktoś zmienia tylko telefon (regresja D-44).

### 3. Pasek akcji: w przepływie na desktopie, przyklejony tylko na telefonie

`position: sticky` na każdej szerokości zakrywał podgląd terminu i pole „Uwagi" już przy
scrollu 0 (zmierzone: pasek y=701–767, podgląd y=725 na 1366×768). Pasek jest więc zwykłym
ostatnim wierszem karty od 992 px, a poniżej 992 px przykleja się **nad** dolną nawigacją
(`bottom: calc(--bottom-nav-height + 1px + safe-area)`) z zapasem `padding-bottom` na
`.app-main`, żeby ostatnie pole dało się przewinąć nad pasek.

### 4. Wydanie: podsumowanie w kolumnie, formularz w dwóch kolumnach

Docket (żółta karta zlecenia) przestał być pasem na całą szerokość — w kolumnie 20 rem
etykiety stoją nad wartościami, usterka jest przycięta do trzech linii. Formularz obok: cztery
krótkie sekcje w dwóch kolumnach od 1200 px. Efekt: na 1366×768 i 1280×800 cały protokół
z przyciskiem „Potwierdź wydanie" jest widoczny, bez poziomego przewijania i bez ściskania pól
(cena 153 px na 1366).

### 5. Chipy etapów w kolejce to przyciski, nie komórki tabeli

Wysokość 44 px, obrys 1 px z promieniem 1 px, odstęp 0,35 rem, licznik 17 px, etykieta 13 px;
zaznaczony chip = tło `--ink` i żółta etykieta (jak dotąd), a obrys zmienia kolor przez
`:has(:checked)`. Wiersz zawija się, nie przewija. Markup bez zmian (`.stat__value` z liczbą
zostaje, bo pilnuje go `QueueTest`).

### 6. Karta „Status dla klienta"

Link, „Kopiuj" (`data-copy-value` + `data-copy-target`), „Otwórz" w nowej karcie i zdanie, że
ten sam link idzie SMS-em przy przyjęciu i po zmianie na „Do wydania". Gdy linku nie ma, a
integracja włączona: formularz `POST services.client-status.store` z `@csrf` i
`data-guard-submit` + „Utwórz link statusu". Gdy wyłączona: krótka informacja. Widok czyta
`$clientStatusUrl ?? null`, `$clientStatusEnabled ?? false`, `$clientStatusNumber ?? null`
i sprawdza `Route::has(...)`, więc działa i przed, i po wdrożeniu backendu.

### 7. Protokół A4

Sekcje w `.print-section` (nie dzielą się między strony), rozliczenie jako cztery etykietowane
liczby w dwóch kolumnach zamiast czterech wierszy tabeli (mniej o ~⅓ wysokości), nagłówek 18 pt,
margines podpisów 13 mm. Cały dokument zajmuje ok. 58% wysokości A4 — jedna strona z zapasem.

### 8. Dostępność

Etykiety powiązane `for`/`id`, `aria-invalid` + `aria-describedby` przy każdym błędzie pola,
podsumowanie błędów na górze formularza (`role="alert"`, `tabindex="-1"`, fokus po nieudanym
submicie w `app.js`), widoczny focus (2 px `--ink`, na chipach `+2px` offset), przełączniki jako
natywne checkboxy w bloku z paskiem sygnału (bez `role="switch"` bez `aria-checked`), zero emoji
jako ikon, `prefers-reduced-motion` nadal wyłącza przejścia.

## Co sprawdzono

- `php artisan view:cache` / `view:clear` — kompilacja bez błędów.
- Chromium (Playwright MCP) na lokalnej kopii danych (`storage/app/data-ui`, port 8011),
  zalogowany `admin`:
  - brak poziomego przewijania (`scrollWidth === clientWidth`) na `create`, `edit`, `release`,
    `queue`, `show`, `print/release` w 390×844, 768×1024, 1280×800, 1366×768, 1440×900,
    1920×1080;
  - preset 7 → zapisane `03.12.2026 15:00` (26.11 + 7 dni, 15:00); preset 2 → podgląd
    „sob 28.11, 15:00"; własna liczba dni 12 → „wt 08.12, 15:00"; „inna data" 01.12 09:30 →
    podgląd z pola, `planned_days` wyłączone, `planned_at` wymagane;
  - na telefonie pasek akcji nad dolną nawigacją, ostatnie pole („Potwierdzenie przyjęcia")
    przewija się nad pasek;
  - karta statusu: stan „wyłączona", stan „brak linku + Utwórz link statusu" (formularz POST
    z CSRF), stan „link aktywny" (Kopiuj/Otwórz); kopiowanie ustawia etykietę „Skopiowano"
    i komunikat w `#async-status`;
  - axe-core 4.10 (WCAG A/AA): 0 naruszeń na `create` (1366 i 390), `release` (1366 i 390),
    `show` (390), `queue` (1366).

## Znane ograniczenia

1. `ASSET_VERSION` w `.env`/`config/app.php` trzeba podbić po wdrożeniu (poza zakresem tego
   zadania — pliki `config/` i `.env` należą do integracji). Bez tego service worker poda stary
   `app.css`/`app.js`; przy weryfikacji czyściłem cache SW, bo inaczej przeglądarka pokazywała
   poprzednią wersję stylów.
2. Edycja nie ma jeszcze presetu „2 dni" jako domyślnego (celowo: nie przesuwamy zapisanego
   terminu). Jeśli właściciel chce skracać termin presetem, wystarczy klik w chip.
3. `planned_at` jest wyłączone w trybie presetów, więc data w polu nie odzwierciedla wyliczonego
   terminu — widoczny jest podgląd „Termin: …". Serwer i tak liczy z `planned_days`.

## Propozycje dalszych usprawnień UX

1. **Jeden przycisk „Przyjmij i wydrukuj"** obok „Przyjmij zlecenie" — dziś wydruk potwierdzenia
   to checkbox, który trzeba znaleźć na dole formularza; w praktyce decyzja „drukować / nie"
   zapada przy kliencie.
2. **Skaner kodów (S/N, plomba)** — pole S/N i plomby mogłyby przyjmować znak z czytnika i same
   przenosić fokus do następnego pola; dziś pracownik przepisuje numer z naklejki.
3. **Szybka zmiana etapu z kafelka kolejki** („Do wydania" jednym klikiem, bez wchodzenia
   w kartę) — kafelek ma już akcję, ale tylko jedną; drugi, cichy przycisk skróciłby najczęstszą
   czynność.
4. **Historia zmian jako filtr** (tylko statusy / tylko pieniądze / tylko zdjęcia) — przy
   zleceniach z 20+ wpisami przewijanie timeline'u jest wolniejsze niż przeglądarka zdarzeń.
5. **Pamięć ostatniego terminu** — jeśli 80% przyjęć idzie na preset 2 dni, chip mógłby
   pamiętać ostatni wybór w `localStorage` i podświetlać go jako domyślny (bez zmiany kontraktu
   serwera).
6. **Status klienta: podgląd „jak to widzi klient"** w modalu zamiast otwierania nowej karty —
   pracownik mógłby od razu przeczytać, co zobaczy klient, i nie tracić kontekstu zlecenia.
