# Kolejka według etapów, jeden kafelek = edycja, jedna wyszukiwarka

| | |
|---|---|
| **Status** | zaakceptowana (właściciel, 2026-11-25) |
| **Zastępuje** | punkt 1 (nazwa „Szukaj zlecenia”), punkt 4 w części o pokazywaniu hasła na żądanie i punkt 6 (filtr na checkboxach) decyzji [0015](0015-tresc-i-przeplywy.md) |
| **Źródło** | zlecenie właściciela (2026-11-25): „Kolejka jest bardzo mało czytelna, na pierwszy rzut oka nie widać które laptopy są w jakiej fazie pracy… każdy z kafelków ma być klikalny i otwierać edycję… hasła mają być domyślnie widoczne… sortowanie ma działać na odwrót… wyszukiwarka ma być jedna, tylko ta u góry” |

## Kontekst

Po przebudowie treści kolejka była gęstą listą bez podziału na etapy: status każdego laptopa
mówiła tylko mała plakietka po prawej, a kolor lewej krawędzi oznaczał wyłącznie express.
Pracownik skanujący 22 zlecenia musiał czytać każdą plakietkę, żeby odpowiedzieć na pytanie
„co jest w trakcie, a co czeka na odbiór”.

Filtr etapów był zbudowany odwrotnie niż podpowiada intuicja: cztery checkboxy startowały **zaznaczone**
(wszystkie etapy), więc kliknięcie „Do wydania” usuwało ten etap z listy zamiast go pokazać.
Do tego dochodziły trzy powierzchnie wyszukiwania (pole w nagłówku, osobny ekran `/search`
i pole w archiwum), z których żadna nie filtrowała kolejki na żywo — trzeba było wpisać frazę
i nacisnąć Enter, a potem wrócić do kolejki.

Hasło do urządzenia było domyślnie zamaskowane (`••••••` + przycisk „Pokaż”), choć i tak
znajdowało się w kodzie strony — maskowanie nie chroniło przed niczym, tylko dodawało klik.

## Decyzja

1. **Kolejka dzieli się na sekcje etapów.** Lista jest grupowana po statusie w kolejności
   sortowania (w trakcie → oczekujące → czeka na części → do wydania → wydane/anulowane).
   Nagłówek sekcji to nazwa etapu, licznik i kolorowa kropka; kafelek ma 4 px pasek tego
   samego koloru po lewej. Kolor nigdy nie jest jedynym nośnikiem informacji — nazwa etapu
   stoi w nagłówku, a przy jednej sekcji (np. po filtrze) status wraca na kafelek jako plakietka.
2. **Status jest wypowiedziany raz.** Nagłówek sekcji, aktywny kafel filtra albo plakietka
   na kafelku — dokładnie jedno z tych miejsc, zależnie od kontekstu. Nie ma sytuacji, w której
   ten sam etap jest napisany dwa razy obok siebie.
3. **Filtr etapu to radio, nie checkbox.** Brak wyboru = cała kolejka robocza; kliknięcie
   etapu = tylko ten etap; wtedy pojawia się „Pokaż wszystkie”. Radio wysyła formularz przy
   zmianie, a `<noscript>` zostawia przycisk, więc filtr działa też bez JavaScriptu.
4. **Jedna wyszukiwarka — pole w nagłówku.** Osobny ekran `/search`, pozycja „Szukaj” w menu
   i dolnej nawigacji oraz pole w archiwum zostały usunięte. Na kolejce pole filtruje listę
   **podczas pisania** (250 ms opóźnienia, od 3 znaków), bez Entera; puste pole przywraca
   normalną kolejkę. Na pozostałych ekranach Enter otwiera kolejkę z tą frazą. Wyniki obejmują
   wydane zlecenia i są grupowane tak samo jak kolejka.
5. **Cały kafelek otwiera edycję.** Kliknięcie gdziekolwiek (poza linkiem, przyciskiem, polem
   formularza i zaznaczonym tekstem) prowadzi do formularza edycji tego laptopa, w którym można
   zmienić każde pole danych, status, express i dodać zdjęcia. Pełna karta zlecenia (historia,
   zdjęcia, wydruki) jest pod linkiem „Szczegóły”. Kafelek zlecenia wydanego/anulowanego prowadzi
   do karty, bo edycja zakończonego zlecenia jest zabroniona przez workflow.
6. **Hasło do urządzenia jest widoczne domyślnie.** Na kafelku i na karcie zlecenia widać
   wartość; przycisk „Ukryj” chowa ją na czas rozmowy z klientem. W historii zmian nadal jest
   tylko znacznik `••••` (decyzja 0015), a wartość występuje w HTML dokładnie raz — bez
   atrybutu `data-value`, który wcześniej ją duplikował.
7. **Lista jest renderowana po stronie serwera także dla wyszukiwania na żywo.** Nowa trasa
   `GET /queue/results` zwraca ten sam partial co strona kolejki (bez nagłówka i menu), więc
   kafelek, grupowanie i filtry nie mają dwóch implementacji. Trasa jest w grupie `auth`
   i `RequirePasswordChange`, jak cała aplikacja.

## Konsekwencje

- **Mniej czytania, więcej sygnałów**: etap widać po nagłówku sekcji i kolorze, zanim przeczyta
  się model; 22 zlecenia to teraz 3 sekcje zamiast 22 plakietek do porównania.
- **Mniej kliknięć**: filtr etapu to jedno kliknięcie (był też wcześniej), wyszukiwanie nie
  wymaga Entera ani osobnego ekranu, edycja jest pod ręką na kafelku.
- **Jedna powierzchnia wyszukiwania**: fraza nie ginie między ekranami; adres z `?q=` i
  `?status=` można wysłać koledze, a historia przeglądarki odtwarza wynik.
- **JavaScript jest przyspieszeniem, nie warunkiem**: bez JS filtr działa przez `<noscript>`,
  a wyszukiwanie przez Enter na formularzu; z JS lista odświeża się sama.
- **Hasło jest łatwiej dostępne na ekranie** — to świadoma decyzja jednego serwisu; ochrona
  nadal polega na logowaniu, sesji i braku dostępu do magazynu, a nie na kropkach w UI.
- **Nowa trasa do utrzymania**: `services.queue.results` musi być w testach gościa i w
  polityce nagłówków; service worker traktuje `/queue` jak dane (sieć, bez cache).

## Alternatywy

- **Zostawić jedną listę i tylko pogrubić plakietki** — odrzucone: problemem był brak podziału,
  nie wielkość etykiety.
- **Grupować po stronie JavaScriptu** — odrzucone: dwa renderery tej samej listy i pusta lista
  bez JS; partial serwerowy jest jednym źródłem prawdy.
- **Modal edycji zamiast strony** — odrzucone: formularz ma 13 pól i zdjęcia, a strona działa
  bez JS, z historią wstecz i zakładkami.
- **Trzymać /search jako „pełne wyniki”** — odrzucone: ta sama fraza, dwa ekrany, dwa miejsca,
  w których trzeba poprawiać filtr.
- **Pokazywać hasło dopiero po kliknięciu** — odrzucone przez właściciela: pracownik i tak
  potrzebuje go przy każdym uruchomieniu laptopa.
