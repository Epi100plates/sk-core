# Instrukcja użytkownika — Studio-Komp Serwis

Aplikacja działa w przeglądarce na komputerze i na telefonie. Można ją „zainstalować” na
telefonie (menu przeglądarki → „Dodaj do ekranu głównego”).

## Logowanie

1. Wejdź na `https://epii.pl/serwis`.
2. Podaj login i hasło otrzymane od administratora.
3. Przy pierwszym logowaniu ustaw własne hasło (min. 12 znaków).

Po 5 nieudanych próbach logowanie jest zablokowane na 15 minut.

## Przyjęcie laptopa (3 dotknięcia)

1. Przycisk **Przyjmij** w nagłówku (na telefonie: „Przyjmij” na dolnym pasku) albo
   **Przyjmij laptopa** nad kolejką.
2. Wypełnij: telefon klienta, model, usterkę, termin realizacji (domyślnie jutro 15:00).
   Opcjonalnie: numer seryjny, hasło do urządzenia, numer plomby, uwagi, cena, koszt części.
3. Zaznacz **Express**, jeśli zlecenie ma priorytet, oraz **Wydrukuj potwierdzenie przyjęcia**,
   jeśli chcesz od razu wydrukować potwierdzenie dla klienta.
4. **Przyjmij zlecenie**.

Zdjęcia: w tym samym formularzu (pole „Zdjęcia”) albo później na karcie zlecenia.

> Hasło do laptopa jest zapisywane w postaci zaszyfrowanej i widoczne dla pracowników.
> Uwaga: na karcie w kolejce jest domyślnie ukryte — pokazujesz je przyciskiem **Pokaż**.

## Kolejka

- Na górze lista aktywnych zleceń: najpierw **express**, potem „w trakcie naprawy”,
  „oczekujące”, „czeka na części”, „do wydania”.
- Na końcu listy: **Ostatnio wydane** (bez przycisków akcji).
- Filtry statusu: kafle z liczbami nad listą. Dotknij status (np. **Do wydania**), a potem
  **Zastosuj filtr** — adres z filtrem można wysłać koledze.
- Na karcie: **Rozpocznij naprawę / Oznacz do odbioru / Wznów naprawę** (zależnie od statusu).

## Wyszukiwanie

Pole **Szukaj** w nagłówku (albo „Szukaj” na telefonie). Wystarczą 3 znaki. Szuka po:

- telefonie (także w innym formacie: `505 123 456`, `+48505123456`, `0048…`),
- numerze plomby (`PLO-001-2024`),
- modelu, numerze seryjnym, opisie usterki i numerze zlecenia.

Wyniki obejmują także zlecenia wydane.

## Zmiana danych

Na karcie zlecenia → **Edytuj**. Zapisujesz tylko zmienione pola — jeśli nic nie zmieniłeś,
aplikacja powie „Brak zmian” i **nie ruszy terminu realizacji**.

Wydanie i anulowanie mają osobne przyciski (nie da się ich zrobić przez edycję).

## Wydanie laptopa

1. Na karcie zlecenia (status „Do wydania”) → **Wydaj laptopa**.
2. Sprawdź dane, popraw jeśli trzeba: cena, koszt części, uwagi, plomba, hasło, numer seryjny.
3. Ustaw **datę wydania** (domyślnie teraz; nie można wpisać daty z przyszłości).
4. Zaznacz **Wydrukuj protokół wydania**, jeśli klient ma dostać wydruk.
5. **Potwierdź wydanie** → potwierdź w okienku.

Zlecenie znika z kolejki i pojawia się w **Wydane**.

## Anulowanie

Na karcie zlecenia → sekcja **Anulowanie** → wpisz powód → **Anuluj zlecenie**.
Zlecenie znika z kolejki, zostaje w historii z powodem. Wydanego zlecenia nie można anulować.

## Historia zmian

Na karcie zlecenia, pod danymi: kto, kiedy i co zmienił (z polskimi nazwami pól).
Widać tam również dodanie i usunięcie zdjęć.

## Wydruki

- **Potwierdzenie przyjęcia** — przycisk na karcie zlecenia. Wydruk A4 z numerem zlecenia,
  modelem, usterką, telefonem, terminem i miejscem na podpisy.
- **Protokół wydania** — po wydaniu, z ceną, kosztem i datą wydania.
- Drukuj przez `Ctrl+P` (na telefonie: menu → Drukuj). W okienku druku wyłącz „nagłówki i stopki”,
  jeśli wychodzą na wydruku.

## Telefon / instalacja jako aplikacja

- Na telefonie dolny pasek: Kolejka, Przyjmij, Szukaj, Wydane, Konta (tylko administrator).
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
| Numer telefonu „nie znajduje” | wpisz fragment (3 znaki) albo inny format — wyszukiwanie normalizuje numer |
| Wydruk nie wychodzi | sprawdź, czy przeglądarka nie blokuje okien pop-up; użyj `Ctrl+P` na otwartym wydruku |
