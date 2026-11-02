# Warstwa UI: Blade + kontrolery + vanilla JS (odstępstwo od Livewire 3)

| | |
|---|---|
| **Status** | zaakceptowana (właściciel, 2026-11-02) |
| **Źródło** | plan `docs/aegis/plans/2026-11-02-przepisanie-aplikacji-serwis.md` |

## Kontekst

Plan (D1) wskazywał Laravel 12 + Livewire 3 + Bootstrap 5. W trakcie realizacji okazało się, że Livewire 3 wymaga inline `<script>` oraz `eval` (Alpine), co koliduje z wymaganiem „CSP bez unsafe-inline” (sekcja 5.4 pkt 8) i z warunkiem „bez buildu”. Cała macierz akceptacji (sekcja 7 planu) jest zdefiniowana na poziomie tras HTTP (`GET/POST /services/{id}/release` itd.), nie komponentów.

## Decyzja

UI: Blade + kontrolery + formularze POST/PUT/DELETE + własny, mały JS (`public/js/*.js`). Brak inline `<script>` i inline `style`, dzięki czemu CSP jest bez `unsafe-inline` i bez `unsafe-eval`. Livewire nie jest zależnością projektu.

## Konsekwencje

**Odstępstwo od litery D1 (Livewire), bez zmiany stacku PHP i bez zmiany zakresu funkcji.** Warstwa domenowa (Actions, repozytoria, polityki) nie zna widoków, więc dodanie Livewire w przyszłości nie wymaga przepisywania logiki. **Wymaga potwierdzenia właściciela** — jeśli Livewire jest konieczny, towarzyszy mu złagodzenie CSP (nonce + `unsafe-eval`).
