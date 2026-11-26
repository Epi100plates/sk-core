# Instrukcja użytkownika — Studio-Komp Serwis

Aplikacja działa w przeglądarce na komputerze i na telefonie. Można ją „zainstalować” na
telefonie (menu przeglądarki → „Dodaj do ekranu głównego”).

## Logowanie

1. Wejdź na `https://serwis.mati-it.pl`.
2. Podaj login i hasło otrzymane od administratora.
3. Przy pierwszym logowaniu ustaw własne hasło (min. 12 znaków).

Po 5 nieudanych próbach logowanie jest zablokowane na 15 minut.

## Przyjęcie laptopa (3 dotknięcia)

1. Przycisk **Przyjmij** w nagłówku (na telefonie: „Przyjmij” na dolnym pasku) albo
   pozycja **Przyjmij laptopa** w lewym menu. Na ekranie przyjęcia ten sam przycisk
   w nagłówku zmienia się w **Kolejka** — jednym kliknięciem wracasz do listy.
2. Wypełnij: telefon klienta, model, usterkę. Opcjonalnie: numer seryjny, hasło do
   urządzenia, numer plomby, uwagi, cena i koszt części.
3. **Termin realizacji** wybierz jednym dotknięciem: **1 / 2 / 3 / 7 dni**, wpisz
   własną liczbę dni albo wskaż **inną datę**. Pod spodem od razu widać wyliczony
   termin (np. „sob 28.11, 15:00”). Domyślnie ustawione są 2 dni.
4. Zaznacz **Express**, jeśli zlecenie ma priorytet. **Potwierdzenie przyjęcia**
   zaznacz tylko wtedy, gdy klient chce wydruk — większość klientów go nie chce.
5. **Przyjmij zlecenie**.

Zdjęcia: w tym samym formularzu (sekcja „Zdjęcia i wydruk”) albo później na karcie
zlecenia.

> Hasło do laptopa jest zaszyfrowane w bazie i **widoczne od razu** na kafelku i na karcie
> zlecenia. Przycisk **Ukryj** chowa je na czas rozmowy z klientem. W historii zmian widać
> tylko, że hasło jest ustawione — nigdy jego treść.

## Status dla klienta

Po przyjęciu zlecenia aplikacja tworzy bezpieczny link do strony statusu i pokazuje go
na karcie zlecenia w sekcji **Status dla klienta**. Ten sam link dostaje klient SMS-em:

- po przyjęciu sprzętu („przyjęliśmy sprzęt”),
- po zmianie etapu na **Do wydania** („sprzęt gotowy do odbioru”).

Karta zlecenia ma przycisk **Kopiuj** (wklej link do SMS-a lub wiadomości) i **Otwórz**
(podejrzyj, co widzi klient). Link pokazuje tylko etap naprawy i daty — bez telefonu,
opisu usterki i notatek wewnętrznych. Zmiana etapu w aplikacji od razu aktualizuje stronę
klienta. Jeśli linku nie udało się utworzyć (np. chwilowy brak połączenia), na karcie
pojawi się przycisk **Utwórz link statusu** — zlecenie nie przepada.

SMS-y wychodzą z panelu CMS (`sk.mati-it.pl/panel` → Ustawienia → Formularz). Domyślnie
działają w **trybie testowym** (bez realnej wysyłki i kosztów); treści obu wiadomości
można tam edytować.

## Kolejka

- Lista jest podzielona na **etapy**: „W trakcie naprawy”, „Oczekujące”, „Czeka na części”,
  „Do wydania”. Nagłówek etapu ma licznik, a każdy kafelek — kolorowy pasek po lewej
  (grafit = w trakcie, żółty = oczekujące, pomarańczowy = czeka na części, zielony = do wydania).
- Filtr etapów: kafle z liczbami nad listą. Dotknij etap (np. **Do wydania**) — pokażą się tylko
  te zlecenia; dotknij go ponownie (albo **Pokaż wszystkie**) — wróci cała kolejka. Adres
  z filtrem można wysłać koledze.
- Na końcu listy: **Ostatnio wydane** (bez przycisków akcji); całe archiwum pod **Wydane**.
- Na kafelku: **Rozpocznij naprawę / Oznacz do odbioru / Wznów naprawę** (zależnie od statusu)
  oraz **Szczegóły** (pełna karta z historią, zdjęciami i wydrukami).
- **Kliknięcie gdziekolwiek w kafelek otwiera edycję** tego laptopa — można zmienić każde pole,
  łącznie ze statusem i oznaczeniem express. Zaznaczanie tekstu i klikanie przycisków na kafelku
  działa normalnie.
- Filtr etapu, **Pokaż wszystkie**, paginacja i przyciski **Dalej** działają **bez przeładowania
  strony**: lista i liczniki podmieniają się w miejscu, więc nie tracisz miejsca przewijania
  ani wpisanej frazy. Adres w pasku nadal pokazuje wybrany etap i szukaną frazę, a komunikat
  po wykonanej akcji znika sam po kilku sekundach.

## Wyszukiwanie

Jedno pole **Szukaj** w nagłówku — na kolejce filtruje listę **od razu podczas pisania**
(wystarczą 3 znaki, Enter nie jest potrzebny). Puste pole przywraca normalną kolejkę.
Na pozostałych ekranach Enter otwiera kolejkę z wpisaną frazą.

Szuka po:

- telefonie (także w innym formacie: `505 123 456`, `+48505123456`, `0048…`),
- numerze plomby (`PLO-001-2024`),
- modelu, numerze seryjnym, opisie usterki i numerze zlecenia.

Wyniki obejmują także zlecenia wydane i są grupowane po etapie. **Wyczyść szukanie** pod
listą usuwa frazę, zostawiając wybrany etap.

## Zmiana danych

Kliknij kafelek zlecenia w kolejce (albo **Edytuj** na karcie zlecenia). Zapisujesz tylko
zmienione pola — jeśli nic nie zmieniłeś, aplikacja powie „Brak zmian” i **nie ruszy terminu
realizacji**. Link **Karta zlecenia** w nagłówku edycji wraca do podglądu.

Wydanie i anulowanie mają osobne przyciski (nie da się ich zrobić przez edycję).

## Wydanie laptopa

1. Na karcie zlecenia (status „Do wydania”) → **Wydaj laptopa**.
2. Sprawdź dane, popraw jeśli trzeba: cena, koszt części, uwagi, plomba, hasło, numer seryjny.
3. Ustaw **datę wydania** (domyślnie teraz; nie można wpisać daty z przyszłości).
4. Zaznacz **Wydrukuj protokół wydania**, jeśli klient ma dostać wydruk.
5. **Potwierdź wydanie** → potwierdź w okienku.

Podsumowanie zlecenia (żółta karta) jest obok formularza, więc na 13–14-calowym laptopie
wszystko widać bez przewijania w poziomie. Zlecenie znika z kolejki i pojawia się w **Wydane**.

## Anulowanie

Na karcie zlecenia → rozwiń **Anuluj zlecenie** pod historią → wpisz powód → **Anuluj zlecenie**.
Zlecenie znika z kolejki, zostaje w historii z powodem. Wydanego zlecenia nie można anulować.

## Historia zmian

Na karcie zlecenia, pod danymi: kto, kiedy i co zmienił (z polskimi nazwami pól).
Widać tam również dodanie i usunięcie zdjęć. Wpisy techniczne (identyfikatory, znaczniki czasu)
są ukryte, żeby historia czytała się jak zdania, a nie jak zrzut bazy.

## Wydruki

- **Potwierdzenie przyjęcia** — przycisk na karcie zlecenia. Wydruk A4 z numerem zlecenia,
  modelem, usterką, telefonem, terminem i miejscem na podpisy.
- **Protokół wydania** — po wydaniu, z ceną, kosztem i datą wydania.
- Drukuj przez `Ctrl+P` (na telefonie: menu → Drukuj). W okienku druku wyłącz „nagłówki i stopki”,
  jeśli wychodzą na wydruku.

## Telefon / instalacja jako aplikacja

- Na telefonie dolny pasek: Kolejka, Przyjmij, Wydane, Konta (tylko administrator).
- Instalacja: menu przeglądarki → **Dodaj do ekranu głównego**.
- Bez internetu aplikacja pokaże stronę „Brak połączenia” — **nie zapisuj wtedy zmian**;
  dane klientów nie są przechowywane w pamięci telefonu (to celowe).

## Role

| Rola | Co może |
|---|---|
| **Administrator** | wszystko + zakładanie i blokowanie kont |
| **Pracownik** | przyjęcie, edycja, zmiana statusu, zdjęcia, wyszukiwanie, wydanie, zmiana ceny i kosztu, anulowanie |
| **Technik** | dziś to samo co pracownik (prawa rozszerzone w kolejnych wersjach) |

## Najczęstsze problemy

| Objaw | Co zrobić |
|---|---|
| „Nieprawidłowy login lub hasło” | sprawdź login (bez wielkich liter), potem odczekaj 15 min po 5 próbach |
| Konto zablokowane | poproś administratora o odblokowanie |
| Brak przycisku „Wydaj” | zlecenie nie jest w statusie „Do wydania” — użyj „Oznacz do odbioru” |
| Brak linku w „Status dla klienta” | kliknij **Utwórz link statusu** na karcie zlecenia; jeśli nadal nie działa, sprawdź połączenie z CMS i poproś administratora o `php artisan services:sync-status` |
| Klient nie dostał SMS-a | w panelu CMS sprawdź Ustawienia → Formularz (tryb testowy, token dostawcy, numer) i kolejkę w Zgłoszeniach |
| Numer telefonu „nie znajduje” | wpisz fragment (3 znaki) albo inny format — wyszukiwanie normalizuje numer |
| Wydruk nie wychodzi | sprawdź, czy przeglądarka nie blokuje okien pop-up; użyj `Ctrl+P` na otwartym wydruku |
