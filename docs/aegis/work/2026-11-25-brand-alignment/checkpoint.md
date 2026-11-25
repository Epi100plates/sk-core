# Checkpoint — wyrównanie wizualne ze stroną firmową (2026-11-25)

## Zlecenie

> „w katalogu pojawił się folder piotrekv3, chciałbym aby aplikacja serwisu przypominała
> wizualnie tę stronę internetową, w przyszłości będą one połączone, możesz rozdzielić pracę
> na kilku agentów”

## Co zostało zrobione

| Krok | Wynik |
|---|---|
| Rozpoznanie marki | tokeny, paleta, fonty i złożenie strony odczytane z `Piotrekv3/` (`src/styles/global.css`, `dist/brand/`) |
| System wizualny | `public/css/app.css` przepisany na tokeny marki (grafit, żółty, włoskowate linie, promień 1 px, brak cieni) |
| Fonty i logo | `public/fonts` (6 plików woff2, lokalnie), `public/brand` (logo na ciemne tło, znak marki) |
| Szkielet aplikacji | grafitowy nagłówek z logo i wyszukiwarką, sidebar z grupami, grafitowa dolna nawigacja z ikonami SVG |
| Ekrany | kolejka (kafle statusów = filtr), karta zlecenia, wydanie z żółtą kartą zlecenia, logowanie, formularze, wyszukiwanie, archiwum, konta, błędy, offline, instalacja |
| Wydruki A4 | `print.css` + oba protokoły w tokenach marki (pasek grafitowy, żółty chip z numerem) |
| PWA | ikony ze znaku marki, motyw `#2d343a`, fonty w precache service workera |
| Dokumentacja | ADR 0014, `docs/aegis/verification/visual-alignment.md`, aktualizacja README i instrukcji użytkownika |

## Podział pracy (agenci)

- **print-brand** — `public/css/print.css` + `resources/views/print/*`: samodzielny arkusz wydruku
  z tymi samymi tokenami i fontami; brak kolizji z resztą (własny plik CSS, dwa widoki).
- **icons-manifest** — `GenerateIcons`, `ManifestController`, `public/icons/*`, test manifestu:
  czysto plikowe zadanie bez wspólnego stanu z warstwą widoków.
- **lead (ten agent)** — system wizualny, szkielet, wszystkie widoki aplikacji i weryfikacja
  w przeglądarce. Świadomie nie oddano CSS aplikacji innym agentom: jeden arkusz = jedno
  źródło prawdy, a kolejność „najpierw tokeny, potem widoki” wymagała jednego właściciela.

Konflikt zdań rozwiązany na korzyść jednego właściciela pliku: agent wydruków zgłosił, że
`PrintTest` oczekuje `potwierdzenie wydane` małymi literami — poprawka w widoku (CSS i tak
pokazuje wersaliki), więc test pozostał bez zmian.

## Weryfikacja

```
php artisan test          257 passed (936 assertions)     json + mysql
php artisan dusk          15 passed (41 assertions)       w tym 6 audytów axe
vendor/bin/pint --test    PASS (157 plików)
phpstan (poziom 6)        [OK] no errors
```

- axe-core (WCAG 2.1 A/AA): 0 naruszeń na 12 widokach (11 tras + logowanie w osobnym kontekście).
- brak poziomego przewijania na 390×844 na 10 trasach (w tym podgląd wydruku A4).
- kontrasty par tokenów: 5,53:1 … 14,49:1 (tabela w raporcie wizualnym).

Zrzuty: `docs/aegis/verification/assets/app-*.png`, `print-acceptance.png`, `ref-*.png`.

## Rzeczy, które wyszły po drodze

1. **Cache CSS** — `?v=` w adresach sprawiał, że przeglądarka trzymała stary arkusz; każde
   podejście wymagało podbicia `ASSET_VERSION` (w tej zmianie 7 → 9).
2. **`text-transform: uppercase` a testy** — nagłówki „eyebrow” są w DOM normalną wielkością liter,
   a Dusk `waitForText` widzi wersaliki. Dwa oczekiwania w `RegressionTest` przestawiono na
   nagłówki stron („Wydane zlecenia”, „Zapisz zmiany”) zamiast na tekst ozdobny.
3. **Przycisk akceptacji** — `+ Przyjmij laptopa` stracił plusa; testy Dusk klikają teraz
   `Przyjmij laptopa`.
4. **Chrome 158** — zainstalowany ChromeDriver obsługiwał tylko 156; pobrano sterownik 158
   (Chrome for Testing) do `vendor/laravel/dusk/bin/chromedriver-win.exe`.
5. **Nagłówek dnia w archiwum** — pokazywał `02.11.2026 01:00` (północ UTC w Warszawie);
   dodano `FieldLabels::formatDay()` z testem.

## Pozostaje

- UAT na fizycznym telefonie i wydruk próbny na papierze (właściciel).
- Decyzja, czy scalić tokeny w jeden plik `tokens.css` współdzielony ze stroną (strona buduje się
  w Node, aplikacja nie — dziś kopiowanie jest ręczne).
- Ewentualna optymalizacja logo do SVG/WebP (~190 KB dwóch PNG).
