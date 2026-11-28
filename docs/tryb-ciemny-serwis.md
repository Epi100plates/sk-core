# Tryb ciemny w aplikacji warsztatu

Tryb ciemny wzorowany na wizytówce serwisu: prawie czarny grafit, mikro-siatka i
żółty akcent sygnałowy. Paleta jest **ta sama** co na stronie marketingowej
(studio-komp.pl, sekcja „Tryb ciemny” w `AGENTS.md` tamtego projektu), więc oba
produkty wyglądają jak jedna firma.

## Jak to działa

| Element | Plik |
|---|---|
| Inicjalizacja motywu przed pierwszym malowaniem | `public/js/theme.js` |
| Przełącznik w pasku akcji (ikona księżyca/słońca) | `resources/views/layouts/app.blade.php` |
| Paleta i reguły | `public/css/app.css`: `:root` (jasny) i `:root[data-theme='dark']` |
| Wydruk zawsze jasny | `@media print` na końcu `app.css` (ekrany wydruku mają własny `print.css`) |

- Motyw to atrybut `data-theme="light|dark"` na `<html>` (dodatkowo
  `data-bs-theme` dla komponentów Bootstrapa 5.3).
- Wybór użytkownika ląduje w `localStorage` pod kluczem `sk-theme`; bez zapisu
  obowiązuje preferencja systemowa, a jej zmiana jest obserwowana na żywo.
- CSP aplikacji to `script-src 'self'`, więc inicjalizacja jest **osobnym
  plikiem** — w projekcie nie ma i nie powinno być skryptów inline.
- `<meta name="color-scheme" content="light dark">` + `color-scheme` w CSS
  (osobno dla każdego motywu) pozwalają przeglądarce przemalować kontrolki
  natywne (data-picker, pasek przewijania, autofill).
- Pasek adresu w przeglądarce zmienia kolor razem z motywem
  (`<meta name="theme-color">` z `data-theme-color-light` / `data-theme-color-dark`).

## Paleta

Wartości ciemne (nadpisują wyłącznie tokeny — układ i komponenty zostają):

```
--paper #151a1e        karty            --line #242c31        linie
--surface #101417      tło strony       --line-strong #364048 mocniejsze linie
--surface-2 #1b2227    podpowierzchnie  --muted #a7aea9        tekst wtórny
--graphite #1a2126     chroma (rail)    --ink #f3f5f2          tekst
--paper-hover #1b2227  hover karty      --link-hover #ffffff   hover linku
--error #ff9a91        błąd             --ok #6ccf95           sukces
--control-line #5f6a73 obramowania kontrolek (3,35:1 na karcie)
```

Tokeny o **stałym znaczeniu** (nie zmieniają się między motywami, bo opisują
rolę koloru, nie powierzchnię): `--on-yellow` (tekst na żółtym), `--on-light`
(tekst na jasnej płytce, np. przycisk usuwania zdjęcia), `--on-error` (tekst na
czerwonym pasku), `--grid-line` / `--grid-size` (mikro-siatka), `--on-dark`,
`--on-dark-muted`, `--dark-line` (ciemna chroma w obu motywach).

Zasady przy dodawaniu nowych komponentów:

1. Kolory wyłącznie z tokenów — żadnych nowych wartości hex w komponentach.
2. Tekst na żółtym wypełnieniu: `var(--on-yellow)`, **nie** `var(--ink)`
   (`--ink` w ciemnym motywie jest jasny).
3. Ciemny panel: `var(--graphite)`; karta: `var(--paper)`; tło strony:
   `var(--surface)`. Nie używać `var(--ink)` jako tła.
4. Wypełnienia odwrócone (wybrany kafelek terminu, aktywna strona paginacji)
   w ciemnym motywie zostają jasne — to najczytelniejszy sygnał „wybrane”;
   akcenty na tym wypełnieniu muszą wtedy użyć `--on-yellow`.
5. Obramowania pól i przycisków: `var(--control-line)`.

## Testy

```powershell
php artisan test --filter=Theme      # kontrakt HTML/meta/CSP (tests/Feature/ThemeTest.php)
php artisan dusk --filter=DarkMode   # przełącznik, zapis, aria, axe color-contrast (tests/Browser/DarkModeTest.php)
```

Dusk wymaga uruchomionego serwera (`php artisan serve` na 127.0.0.1:8000) i
ChromeDrivera. Zrzuty i pomiary obu motywów robi też skrypt pomocniczy
`storage/qa/theme-qa.mjs` (poza repozytorium): kontrast axe, przepełnienie
layoutu, błędy konsoli, widoki desktop i telefon.

## Wdrożenie

`public/js/theme.js` jest nowym plikiem, a `app.css` zmienia się w całości —
po wdrożeniu trzeba **podbić `ASSET_VERSION` w `.env` na VPS** (nginx cache'uje
`/css` i `/js` na 7 dni, a service worker trzyma powłokę pod nazwą
`serwis-<wersja>`). Skrypt `deploy/deploy-vps.ps1` celowo nie rusza `.env`,
więc to krok ręczny:

```bash
ssh root@olga322.mikrus.xyz -p 10322 "sed -i 's/^ASSET_VERSION=.*/ASSET_VERSION=<nowa>/' /srv/sk-serwis/.env"
```

## Znane ograniczenia

- Motyw jest ustawiany po stronie przeglądarki; przy wyłączonym JS zostaje
  jasny (aplikacja i tak wymaga JS do kolejki i formularzy).
- Mikro-siatka jest dekoracją tła — nie pojawia się na wydrukach.
- Przełącznika nie ma na ekranie logowania (strona samodzielna, bez paska
  akcji); logowanie podąża za zapisanym wyborem lub systemem.
- Na telefonie rząd filtrów etapów w kolejce wystaje ok. 35 px poza szerokość
  ekranu — **defekt sprzed trybu ciemnego** (identyczny w jasnym motywie),
  do osobnej poprawki.
