# Jeden pasek zamiast nagłówka: szukanie, akcje i filtr etapów w jednym miejscu

| | |
|---|---|
| **Status** | zaakceptowana (właściciel, 2026-11-25) |
| **Zastępuje** | punkt 4 decyzji [0014](0014-warstwa-wizualna.md) (grafitowy pasek górny z logo) oraz umiejscowienie jednego pola szukania z punktu 4 decyzji [0016](0016-kolejka-i-wyszukiwanie.md) — samo zachowanie szukania zostaje bez zmian |
| **Źródło** | zlecenie właściciela (2026-11-25): „Ta wersja nie musi mieć górnej belki z logiem itp, zaoszczędźmy miejsce dodając wszystkie ikonki jak wyszukiwanie, wylogowywanie itp na tę belkę którą jest na ss wcześniej odpowiednio ją zmniejszając, nie musi ona być na całą szerokość, upchaj to wszystko po prostu bardziej” |

## Kontekst

Trzy bloki nad listą robiły jedną rzecz. Grafitowy nagłówek (78 px na desktopie, 98 px na
telefonie po zawinięciu) niósł logo, pole szukania i wyjście. Pod nim blok tytułu strony (67 px /
59 px) mówił „Kolejka napraw” i pokazywał datę. Pod nim karta filtra etapów (142 px / 127 px)
pokazywała cztery liczby — każda we własnej kolumnie szerokości ~280 px, żeby zmieścić dwie cyfry.

Efekt: na 1440 px pierwszy kafelek zaczynał się 374 px od góry, a na telefonie 390×844 — 441 px,
czyli ponad połowa pierwszego ekranu zanim pojawiało się pierwsze zlecenie. Sam pasek statystyk
zużywał pełną szerokość na cztery liczby, a najcenniejszy pas u góry ekranu przeznaczał na logo
i nazwę firmy, które pracownik widzi codziennie i nie musi ich czytać.

## Decyzja

1. **Bez grafitowego nagłówka.** `.app-header` zniknął z layoutu i z arkusza razem z regułami
   `--header-height`. Nad treścią nie stoi nic, co istnieje tylko po to, żeby pokazać logo.
2. **Jeden pasek, jedna karta.** `.app-bar` na górze kolumny treści: pierwszy wiersz to tytuł
   ekranu (gdy widok go podaje), pole szukania, „Przyjmij”, nazwa użytkownika i „Wyloguj”; drugi
   wiersz to filtr etapów kolejki. Layout udostępnia drugi wiersz przez `@section('bar')`, więc
   liczby i sterowanie są w jednym pudełku, a nie w dwóch kartach jedna pod drugą.
3. **Logo wraca do sidebara** jako znak SK (28 px) nad nawigacją — marka zostaje, ale nie zajmuje
   pasa nad treścią. Poniżej 1024 px (sidebar ukryty) nie ma jej wcale: na telefonie tożsamość
   nosi ikona PWA na pulpicie, a nie pasek w aplikacji.
4. **Tytuł ekranu wchodzi do paska.** Na kolejce `Kolejka napraw` i data stanu warsztatu stoją
   obok szukania, więc strona zaczyna się od sterowania, a nie od bloku tytułu. Pozostałe ekrany
   zachowują swój `page-head` (mają podtytuł i własne akcje) — wspólny jest pasek, nie tytuł.
5. **Filtr etapów to chipsy w jednym wierszu**, nie cztery kolumny: liczba 15 px + etykieta 12 px
   + kropka koloru etapu, separatory hairline, aktywny chip odwrócony (grafit z żółtą etykietą)
   jak dotąd. „Pokaż wszystkie” jest linkiem w tym samym wierszu zamiast stopki karty.
6. **Pasek nie jest przyklejony.** Sticky pasek z filtrem zabierałby 95 px każdego ekranu
   przewijania — czyli dokładnie to, co ta zmiana odzyskuje. Świadoma cena: szukanie jest na górze
   strony, nie w każdej chwili.
7. **Telefon: tytuł i wyjście w jednym wierszu, szukanie pełną szerokością pod nim.** Wciśnięte
   obok tytułu pole miało ~100 px, czyli dwanaście znaków — za mało, żeby zobaczyć wpisywaną frazę.
8. **Szukanie stoi poza regionem asynchronicznym.** Filtr etapów nadal jest
   `data-async-region="queue-filter"`; pole `q` jest wcześniej w DOM i poza tym regionem, więc
   podmiana liczb po kliknięciu chipa nie zabiera fokusu z pola, w którym ktoś właśnie pisze
   (ukryte `q` w regionie synchronizuje ten sam kod co przedtem).

## Konsekwencje

- **Pierwszy kafelek bliżej góry:** 374 → 156 px na 1440 px (218 px mniej) i 441 → 252 px na
  390×844 (189 px mniej). Wysokość dokumentu na telefonie spadła z 1285 do 1096 px.
- **Pasek jest gęsty, nie rozciągnięty:** 95 px na ≥1200 px (dwa wiersze), 99 px na 1024 i 768
  (jeden wiersz + chipsy), 195 px na 360 px (tytuł, szukanie, chipsy w dwóch rzędach).
  Na 1600 px karta paska ma 1283 px — tyle, ile potrzebuje treść, nie więcej.
- **Cztery liczby w jednym wierszu** zamiast czterech kolumn: 619 px na cały filtr etapów
  (na 1440 px), więc obok zostaje miejsce na „Pokaż wszystkie”.
- **Nagłówek nie jest już „chrome”, więc nie ma gdzie schować stałych elementów:** kolejny ekran,
  który będzie chciał własne akcje w pasku, użyje `@section('bar')` albo dostanie je w `page-head`.
- **Testy pilnują układu, nie wyglądu:** `QueueTest` sprawdza, że pasek istnieje, że stary
  `app-header` nie wrócił, że tytuł i `queue-filter` są w pasku i że inne ekrany mają pasek bez
  drugiego wiersza.
- **`ASSET_VERSION` musi iść w górę przy każdej zmianie CSS** — w tej rundzie doszedł do 28.

## Alternatywy

- **Zostawić nagłówek i tylko go zmniejszyć** — odrzucone: 78 px nagłówka + 67 px tytułu + 142 px
  karty filtra to nadal trzy bloki nad listą; zmniejszenie jednego z nich nie odzyskuje pasa.
- **Sticky pasek z filtrem** — odrzucone: przy 22 zleceniach pasek wisiałby nad listą przez całe
  przewijanie, zabierając 95 px z każdego ekranu.
- **Szukanie w sidebarze** — odrzucone: na telefonie sidebar nie istnieje, a na desktopie wypada
  poza wzrok osoby pracującej na liście.
- **Menu hamburger z akcjami** — odrzucone: ukrywa nawigację, którą dolna nawigacja już pokazuje,
  i dokłada krok przed każdą czynnością.
- **Filtr etapów w modalu albo panelu bocznym** — odrzucone: dodatkowy krok przed filtrowaniem
  i brak widocznych liczb, które są najważniejszą informacją o stanie warsztatu.
- **Pasek na całą szerokość z pustymi marginesami** — odrzucone: karta kończy się tam, gdzie
  kończy się treść; resztę szerokości zostawiamy liście.
