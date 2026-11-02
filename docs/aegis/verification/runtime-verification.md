# Raport weryfikacji runtime (Chromium, 2026-11-02)

| | |
|---|---|
| **Serwer** | `php artisan serve` → `http://127.0.0.1:8123` (PHP 8.4.27) |
| **Magazyn** | `DATA_STORE=json`, dane demo (`DemoSeeder`, 30 zleceń) + 1 zlecenie przyjęte w przeglądarce |
| **Klient** | Chromium przez narzędzie audytu (nie jest zależnością projektu) |
| **Widoki** | 1366×768 (desktop) i 390×844 (telefon) |

Wykonane przejścia i wynik:

| # | Przejście | Wynik |
|---|---|---|
| 1 | Logowanie `admin` / hasło z instalatora | ✅ przekierowanie na kolejkę, ostatnie logowanie zapisane |
| 2 | Kolejka na desktopie | ✅ sidebar, liczniki statusów, filtry, karty z ceną i kosztem |
| 3 | Kolejka na telefonie 390×844 | ✅ dolna nawigacja, karty jednokolumnowe, `scrollWidth == clientWidth` |
| 4 | Przyjęcie laptopa (formularz mobilny) | ✅ domyślny termin **jutro 15:00 czasu lokalnego** (defekt D-45 zamknięty), cena `199,99` zapisana jako `199.99` |
| 5 | Checkbox „wydrukuj potwierdzenie przyjęcia” | ✅ przekierowanie na wydruk A4, znacznik `🖨 potwierdzenie wydane` na karcie |
| 6 | Wydruk potwierdzenia przyjęcia | ✅ numer `SRV-2026-000031`, termin `03.11.2026 15:00`, cena `199,99 zł`, telefon `+48 505 123 456`, plomba `PLO-900-2026` |
| 7 | „Rozpocznij naprawę” → „Oznacz do odbioru” | ✅ komunikat „Status zmieniony na …”, wpisy `status_changed` w historii |
| 8 | Wydanie z telefonu (390×844, UA desktop) | ✅ formularz wypełniony, data wydania = teraz (z sekundami), cena/koszt w formacie `199,99` |
| 9 | Protokół wydania do druku | ✅ `SRV-2026-000031`, cena `199,99 zł`, koszt `80,50 zł`, data `02.11.2026 18:56` |
| 10 | Zapis formularza edycji bez zmian | ✅ „Brak zmian — nic nie zostało zapisane.”, termin bez przesunięcia (D-44) |
| 11 | Formularz edycji po otwarciu | ✅ wszystkie pola wypełnione z magazynu (znaleziony i naprawiony błąd: `device_model` vs `deviceModel`) |
| 12 | Wyszukiwanie `+48505123456` | ✅ znajduje zlecenie zapisane jako `505 123 456` (D-20) |
| 13 | Galeria: upload zdjęcia | ✅ „Dodano zdjęcia: 1.”, miniatura widoczna, plik na dysku prywatnym |
| 14 | Lightbox | ✅ `role="dialog"`, `aria-modal`, licznik `1 / 1`, ESC zamyka, focus wraca na miniaturę |
| 15 | Archiwum wydanych | ✅ grupowanie po dniu, ceny, paginacja |
| 16 | PWA: rejestracja service workera | ✅ `scope=/`, `active`, cache `serwis-1` |
| 17 | Zawartość Cache Storage | ✅ **tylko** `/offline`, CSS, JS, ikony — zero HTML z danymi, zero JSON, zero zdjęć (D-28) |
| 18 | Tryb offline (`context.setOffline(true)`) | ✅ nawigacja na `/services/31` pokazuje stronę „Brak połączenia”, bez danych klienta; po powrocie sieci aplikacja działa |
| 19 | Panel użytkowników (admin) | ✅ lista kont, role, znacznik „aktywne”; po poprawce CSS brak poziomego scrolla strony |
| 20 | Konsola przeglądarki | ✅ 0 błędów i 0 ostrzeżeń (CSP bez `unsafe-inline` nie blokuje żadnego zasobu) |

## Zrzuty ekranu

Pliki leżą w katalogu roboczym narzędzia audytu (poza repozytorium):

- `queue-desktop.png` — kolejka 1366×768,
- `queue-mobile.png` — kolejka 390×844,
- `service-mobile.png` — karta zlecenia z historią (390 px),
- `print-acceptance.png`, `print-release.png` — wydruki A4,
- `lightbox.png` — podgląd zdjęcia,
- `admin-users.png` — panel kont (przed poprawką CSS).

## Poprawki wprowadzone po weryfikacji w przeglądarce

1. `device_model` nie był wypełniany w formularzu edycji (mapowanie pole → właściwość rekordu). Dodano mapę i test regresyjny `it('pre-fills the edit form with the stored values')`.
2. Dokument A4 wymuszał poziomy scroll na telefonie — dodano `@media screen and (max-width: 820px)`.
3. `span.visually-hidden` w tabeli rozszerzał dokument na telefonie — `.table-responsive { position: relative }`.
4. Historia pokazywała surowe wartości statusów (`pending → in_progress`) — tłumaczone na etykiety.
5. Migawka zdarzenia `accepted` zawierała puste pola (`— → —`) — pomijane.
6. Ceny w formularzu wydania pokazywane z przecinkiem (`199,99`).
