# Kolejka i wyszukiwanie — weryfikacja przebudowy

| | |
|---|---|
| **Data** | 2026-11-25 |
| **Zakres** | ekran kolejki, filtr etapów, wyszukiwanie w nagłówku, kafelek zlecenia, formularz edycji, strony archiwum |
| **Decyzja** | [0016 — kolejka i wyszukiwanie](../decisions/0016-kolejka-i-wyszukiwanie.md) |
| **Środowisko** | Laravel 12, PHP 8.4, magazyn JSON (dane deweloperskie, 22 aktywne zlecenia) i MySQL/MariaDB 10.4 (testy), Chromium 158 |

## Co było nie tak

| # | Ekran | Problem |
|---|---|---|
| 1 | kolejka | Wszystkie etapy wyglądały tak samo — status mówiła tylko mała plakietka po prawej; przy 22 zleceniach nie dało się wzrokiem odpowiedzieć „co jest w trakcie, a co czeka na odbiór” |
| 2 | kolejka | Filtr etapów był odwrócony: cztery checkboxy startowały zaznaczone, więc kliknięcie etapu **usuwało** go z listy zamiast pokazać tylko jego |
| 3 | kolejka | Kafelek nie był klikalny w całości; edycja była schowana na karcie zlecenia, do której prowadził tylko link w tytule |
| 4 | kolejka, karta | Hasło do urządzenia było domyślnie zamaskowane (`••••••` + „Pokaż”), choć i tak było w HTML — dodatkowy klik bez zysku |
| 5 | cała aplikacja | Trzy wyszukiwarki (nagłówek → `/search`, osobne pole w archiwum, pozycja „Szukaj” w menu i dolnej nawigacji); żadna nie filtrowała kolejki na żywo, każda wymagała Entera |

## Co jest teraz

| Obszar | Zmiana |
|---|---|
| Czytelność etapów | Lista dzieli się na sekcje: „W trakcie naprawy”, „Oczekujące”, „Czeka na części”, „Do wydania” (i „Wydane”/„Anulowane” w wynikach szukania). Nagłówek sekcji: kropka koloru, nazwa, licznik w ramce, linia do końca szerokości. Kafelek ma 4 px pasek tego samego koloru po lewej. |
| Jeden komunikat o etapie | Nagłówek sekcji **albo** aktywny kafel filtra **albo** plakietka na kafelku — nigdy dwa naraz. Przy jednej sekcji bez filtra status wraca na kafelek. |
| Filtr etapów | Radio zamiast checkboxów: brak wyboru = cała kolejka, kliknięcie = tylko ten etap, wtedy „Pokaż wszystkie” (z zachowaniem wpisanej frazy). Bez JS działa przycisk z `<noscript>`. |
| Wyszukiwanie | Jedno pole w nagłówku. Na kolejce filtruje **podczas pisania** (250 ms, od 3 znaków), bez Entera; puste wraca do kolejki; adres aktualizuje się (`?q=&status=`). Na innych ekranach Enter otwiera kolejkę z frazą. |
| Wyniki | Ta sama lista co kolejka, pogrupowana po etapie, z licznikiem i „Wyczyść szukanie”. Obejmuje zlecenia wydane; archiwum na dole znika w trybie szukania. |
| Kafelek | Kliknięcie gdziekolwiek (poza linkiem, przyciskiem, polem i zaznaczonym tekstem) otwiera **edycję** tego laptopa: wszystkie pola danych, status, express, dodawanie zdjęć. Link „Szczegóły” prowadzi do karty z historią, zdjęciami i wydrukami. Zlecenie wydane/anulowane otwiera kartę (edycja jest zabroniona przez workflow). |
| Hasła | Widoczne od razu na kafelku i karcie zlecenia; „Ukryj” chowa je jednym kliknięciem. W historii nadal `••••`. Wartość występuje w HTML dokładnie raz (bez `data-value`). |
| Archiwum | Został tylko zakres dat; własne pole szukania usunięte (zastępuje je pole w nagłówku). |

## Pomiar

| Wskaźnik | Wartość |
|---|---|
| Wysokość kolejki, 22 zlecenia, 1440 px | 3 220 px (przed rundą 3 154 px — nagłówki sekcji dodały ~66 px) |
| Wysokość kolejki, 390 px | 5 158 px (przed 5 147 px) |
| Wysokość kafelka | 95 px desktop / 161 px telefon |
| Poziome przewijanie przy 390 px | brak (`scrollWidth` 375 px) |
| Kroki do zawężenia etapu | 1 kliknięcie (było 1, ale odwrócone) |
| Kroki do wyszukania | 0 dodatkowych (pisanie), wcześniej: wpisz + Enter + osobny ekran |
| Kroki do edycji z kolejki | 1 kliknięcie w kafelek (wcześniej: karta → „Edytuj”) |
| Wystąpienia hasła w HTML karty | 1 (było 1, ale ukryte za `data-value` + przyciskiem) |

## Weryfikacja automatyczna

```
php artisan test                        292 passed (1039 assertions)   json + mysql
php artisan dusk                        16 passed (49 assertions)      w tym 6 audytów axe
vendor/bin/pint --test                  PASS (157 plików)
vendor/bin/phpstan analyse              [OK] no errors (poziom 6)
```

Nowe/zmienione testy:

- `tests/Feature/QueueTest.php` — filtr jednego etapu, „Pokaż wszystkie”, sekcje etapów, kafelek → edycja, kafelek wydanego zlecenia → karta, fragment `/queue/results` (także z filtrem) i jego nagłówek `X-Queue-Results`.
- `tests/Feature/SearchTest.php` — całe wyszukiwanie przeniesione na kolejkę (`/?q=`), brak drugiego ekranu (`/search` → 404), archiwum bez pola szukania.
- `tests/Feature/SecurityTest.php` — hasło widoczne domyślnie, dokładnie jedno wystąpienie w HTML, `aria-pressed="true"`, obecność na kafelku kolejki.
- `tests/Browser/QueueSearchTest.php` — w Chrome: hasło widoczne → „Ukryj” → „Pokaż”, filtrowanie po wpisaniu frazy bez Entera, powrót po wyczyszczeniu pola, kliknięcie w kafelek otwiera edycję. Test przeszedł dopiero po dodaniu obsługi zdarzenia `change` (WebDriver czyści pole bez `input`) — to realna poprawka, nie obejście testu.

## Zrzuty

- [kolejka — desktop](assets/app-queue-desktop.png)
- [kolejka — telefon](assets/app-queue-mobile.png)
- [wyniki szukania w kolejce](assets/app-queue-search-desktop.png)
- [karta zlecenia z widocznym hasłem](assets/app-show-desktop.png)

## Czego nie sprawdzono

1. **Fizyczny telefon** — układ sprawdzony w Chromium w oknie 390×844, nie na iPhone'ie/Androidzie (klawiatura, autofill, bezpieczne obszary).
2. **Czytnik ekranu** — axe nie zgłasza naruszeń krytycznych, ale kolejność ogłaszania wyników na żywo (`aria-live`) nie była testowana z NVDA/VoiceOver.
3. **Firefox/Safari** — `WeakMap`, `AbortController`, `fetch` i `URLSearchParams` są wspierane, ale nie uruchamiano tych przeglądarek.
4. **Wyszukiwanie na żywo tylko na kolejce** — na karcie zlecenia i w formularzach Enter otwiera kolejkę (celowo: nie można porzucić wypełnianego formularza w połowie pisania).
5. **Liczniki w pasku etapów są globalne** — pokazują stan całego warsztatu, nie liczbę trafień dla wpisanej frazy; liczba trafień jest w podsumowaniu nad listą.
