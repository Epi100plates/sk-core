# Treść i przepływy ekranów: jeden serwis, jeden tekst na fakt

| | |
|---|---|
| **Status** | zaakceptowana (właściciel, 2026-11-25) |
| **Źródło** | zlecenie właściciela (2026-11-25): „w niektórych miejscach tekst się dubluje, część informacji jest niepotrzebna, nie zawsze wszystko poprawnie się formatuje… przebuduj aplikację, aby była bardziej user friendly, będzie ona tylko do użytku w jednym serwisie” + prośba o sprawdzenie zabezpieczeń |

## Kontekst

Aplikacja była pisana jak system dla wielu serwisów i wielu ról: każdy ekran tłumaczył się przed użytkownikiem („Kliknij status, aby filtrować”, „Zapisywane szyfrowane, widoczne dla pracowników”, „Czas lokalny (Europe/Warsaw)”), powtarzał tę samą informację w nagłówku, tytule i treści, a historia zmian pokazywała surowe pola audytu (`Wydał: 1`, `Potwierdzenie przyjęcia wydane: — → —`). Przechodząc przez wszystkie 32 trasy znaleziono 46 miejsc, w których tekst się dublował, był zbędny albo źle sformatowany. Równolegle audyt bezpieczeństwa wykazał, że hasło do laptopa trafiało w postaci jawnej do historii zlecenia.

Aplikacja obsługuje **jeden serwis** (Studio-Komp), a nie sieć placówek — to zwalnia z tłumaczenia kontekstu, którego użytkownik nie potrzebuje.

## Decyzja

1. **Jedna nazwa na jedną rzecz.** Nawigacja, tytuł strony i przycisk używają tego samego słowa: *Kolejka*, *Przyjmij laptopa*, *Szukaj zlecenia*, *Wydane zlecenia*, *Użytkownicy*. Pasek nad tytułem (`eyebrow`) nie powtarza tytułu — niesie inną informację (data przyjęcia, liczba kont). Numer zlecenia pojawia się na ekranie raz (metadane albo karta wydruku, nie oba).
2. **Kolejka jest listą, nie plakatem.** Karta zlecenia to gęsty wiersz: model + numer, telefon · termin · cena, jedna linia usterki, status i akcja po prawej. 22 zlecenia zajmują ~3 ekrany zamiast ~7, wiersz ma ~110 px. Puste pola (`Hasło —`, `Uwagi —`) nie są renderowane.
3. **Historia czyta się jak zdania.** Ukryte są wpisy, które nie niosą informacji dla pracownika: identyfikatory, znaczniki czasu, pola aktora (`released_by`, `accepted_by`), powód anulowania i status (mają własny wiersz lub opis). Data i autor są w nagłówku wpisu, zmiana statusu pojawia się raz, pary `— → —` są odfiltrowane. Numery telefonu w historii są formatowane jak na karcie (`+48 500 102 877`).
4. **Hasło do urządzenia nigdy nie trafia do historii.** `ServiceDiff` zapisuje znacznik `••••` zamiast wartości, a `FieldLabels::format` maskuje także wpisy sprzed zmiany. Samo hasło zostaje zaszyfrowane w rekordzie i pokazywane na żądanie (**Pokaż**).
5. **Polska odmiana jest liczona, nie wpisywana na sztywno.** `FieldLabels::plural()` obsługuje `1 zlecenie / 3 zlecenia / 22 zlecenia / 0 zleceń`; używają go kolejka, wyszukiwanie, archiwum, historia i licznik kont.
6. **Filtr statusu działa po jednym kliknięciu.** Kafelki statusów wysyłają formularz przy zmianie (`data-auto-submit`); `<noscript>` zostawia przycisk „Zastosuj filtr”, więc bez JS filtr nadal działa.
7. **Akcje destrukcyjne są schowane.** Anulowanie zlecenia to rozwijany panel (`<details>`), a nie stała karta konkurująca z historią.
8. **Formatowanie daty bez zbędnych sekund.** Pole terminu pokazuje `26.11.2026 15:00`; sekundy pojawiają się tylko wtedy, gdy w rekordzie są niezerowe (inaczej zapis „przesuwałby” termin — defekt D-44).
9. **Jeden właściciel limitu prób logowania.** Limit jest wyłącznie w middleware `throttle:login` (5 prób / 15 min). Zdjęty został drugi licznik w kontrolerze, który podwajał zużycie limitu i skracał realne okno do ~3 prób.
10. **Błąd to 404, nie stack trace.** Trasy wiążą rekord przez `abort(404)`, więc nieistniejące ID nie wywraca aplikacji; `.env.example` ma `APP_DEBUG=false`, a nagłówki bezpieczeństwa są dokładane także do odpowiedzi błędów (`$exceptions->respond`), nie tylko do stron 200.

## Konsekwencje

- **Mniej tekstu, więcej informacji**: usunięte zostały cztery akapity pomocy, trzy powtórzenia numeru zlecenia, 22 wiersze `—` w kolejce i 17 wierszy `— → —` w jednym wpisie historii.
- **Historia jest krótsza niż rekord audytu**: szczegóły techniczne nadal są w `service_events` (eksport, `legacy:verify`), ale nie w interfejsie. Kto potrzebuje pełnego śladu, czyta dane, nie ekran.
- **`ASSET_VERSION` nadal trzeba podbijać** — przebudowa zmieniła CSS i JS.
- **Sekundy w terminie** są zaokrąglane do minuty tylko wtedy, gdy były zerowe; zapis bez zmian nadal zwraca ten sam instant (test regresyjny D-44).
- **Trzy role zostają** (decyzja 0009): „Technik” i „Pracownik” mają dziś identyczne prawa — interfejs mówi to wprost zamiast udawać różnicę. Konsolidacja ról to osobna decyzja, bo dotyka danych i polityk.
- **Pozostaje do zrobienia**: UAT na fizycznym telefonie, wydruk próbny na papierze, `SESSION_SECURE_COOKIE=true` po włączeniu HTTPS.

## Alternatywy

- **Zostawić „wszystko dla wszystkich” i tylko skrócić teksty** — odrzucone: gęstość kolejki i duplikaty w historii wynikały ze struktury ekranów, nie z długości zdań.
- **Ukryć historię za przyciskiem „pokaż wszystko”** — odrzucone: historia jest codziennym narzędziem (kto zmienił cenę), więc ma być widoczna, tylko czytelna.
- **Osobny widok „audyt” z surowymi polami** — odrzucone na teraz: nikt tego nie potrzebuje w serwisie, a dane są dostępne w magazynie i w eksporcie.
- **Modal/JS do anulowania zamiast `<details>`** — odrzucone: natywny element działa bez JS, jest dostępny z klawiatury i nie wymaga nowego kodu.
