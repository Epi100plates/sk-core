<?php

declare(strict_types=1);

namespace Tests\Browser;

use Facebook\WebDriver\Chrome\ChromeDevToolsDriver;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Dark mode in a real browser (2026-11-28).
 *
 * The server can only render the light theme: `<html data-theme>` is written by
 * `public/js/theme.js` before the first paint and the choice is kept in
 * `localStorage["sk-theme"]`. So the switch, its ARIA state and the contrast of
 * the dark palette can only be checked in Chrome.
 *
 * `prefers-color-scheme` is emulated per test (Chrome DevTools command), because
 * the default theme must not depend on how the machine running the suite is
 * configured — a dark Windows theme would otherwise flip what "no saved choice"
 * means.
 *
 * axe-core is the same static file the accessibility suite uses
 * (`public/js/axe.min.js`) — the CSP is `script-src 'self'`, so it is injected
 * as a `<script src>` exactly like a real page would.
 */
final class DarkModeTest extends DuskTestCase
{
    public function test_the_system_preference_decides_when_nothing_is_saved(): void
    {
        $this->seedUser('dusk-theme');

        $this->browse(function (Browser $browser): void {
            $this->login($browser, 'dusk-theme');

            $this->emulateSystemTheme($browser, 'dark');
            $this->forgetThemeChoice($browser);
            $browser->visit('/')->waitForText('Kolejka napraw');
            $browser->waitFor('[data-theme-toggle]');

            $this->assertSame('dark', $this->dataTheme($browser), 'Bez zapisanego wyboru ciemny system musi dać ciemny motyw.');

            // The toggle has to describe the state it is in, not the state of the
            // markup: pressed and "switch to light" while the page is dark.
            $this->assertSame('true', $this->toggleAttribute($browser, 'aria-pressed'), 'aria-pressed nie pokazuje ciemnego motywu po wejściu na stronę.');
            $this->assertSame('Włącz tryb jasny', $this->toggleAttribute($browser, 'aria-label'), 'Etykieta przełącznika nie opisuje akcji powrotu do jasnego motywu.');
            $this->assertSame('Tryb jasny', $this->toggleAttribute($browser, 'title'), 'Podpowiedź przełącznika nie opisuje akcji powrotu do jasnego motywu.');

            $this->emulateSystemTheme($browser, 'light');
            $browser->visit('/')->waitForText('Kolejka napraw');
            $browser->waitFor('[data-theme-toggle]');

            $this->assertSame('light', $this->dataTheme($browser), 'Jasny system musi dać jasny motyw, gdy nic nie zapisano.');
            $this->assertSame('false', $this->toggleAttribute($browser, 'aria-pressed'));
        });
    }

    public function test_the_toggle_switches_to_dark_and_the_choice_survives_a_reload(): void
    {
        $this->seedUser('dusk-theme');

        $this->browse(function (Browser $browser): void {
            $this->login($browser, 'dusk-theme');

            $this->emulateSystemTheme($browser, 'light');
            $this->forgetThemeChoice($browser);
            $browser->visit('/')->waitForText('Kolejka napraw');
            $browser->waitFor('[data-theme-toggle]');

            $this->assertSame('light', $this->dataTheme($browser), 'Punkt wyjścia testu musi być jasny.');

            $browser->click('[data-theme-toggle]');
            $this->waitForTheme($browser, 'dark');

            $this->assertSame('dark', $this->dataTheme($browser), 'Klik nie ustawił html[data-theme="dark"].');
            $this->assertSame('true', $this->toggleAttribute($browser, 'aria-pressed'), 'aria-pressed nie przeszło na true.');
            $this->assertSame('Włącz tryb jasny', $this->toggleAttribute($browser, 'aria-label'), 'Etykieta nie opisuje akcji powrotu do jasnego motywu.');
            $this->assertSame('Tryb jasny', $this->toggleAttribute($browser, 'title'), 'Podpowiedź nie opisuje akcji powrotu do jasnego motywu.');
            $this->assertSame('dark', $this->storedTheme($browser), 'Wybór nie trafił do localStorage["sk-theme"].');
            $this->assertSame(
                '#0c1012',
                $this->themeColorMeta($browser),
                'Pasek przeglądarki nie przeszedł na ciemny kolor marki (#0c1012).'
            );

            // The value of a persisted choice is that the next load starts dark.
            $browser->refresh();
            $browser->waitFor('[data-theme-toggle]');
            $browser->waitForText('Kolejka napraw');

            $this->assertSame('dark', $this->dataTheme($browser), 'Po przeładowaniu motyw wrócił do jasnego.');
            $this->assertSame('true', $this->toggleAttribute($browser, 'aria-pressed'), 'Po przeładowaniu aria-pressed nie pokazuje ciemnego motywu.');
            $this->assertSame('Włącz tryb jasny', $this->toggleAttribute($browser, 'aria-label'), 'Po przeładowaniu etykieta kłamie o stanie przełącznika.');
            $this->assertSame('dark', $this->storedTheme($browser), 'localStorage["sk-theme"] nie przetrwał przeładowania.');
        });
    }

    public function test_the_second_click_returns_to_light(): void
    {
        $this->seedUser('dusk-theme');

        $this->browse(function (Browser $browser): void {
            $this->login($browser, 'dusk-theme');

            $this->emulateSystemTheme($browser, 'light');
            $this->forgetThemeChoice($browser);
            $browser->visit('/')->waitForText('Kolejka napraw');
            $browser->waitFor('[data-theme-toggle]');

            $browser->click('[data-theme-toggle]');
            $this->waitForTheme($browser, 'dark');

            $browser->click('[data-theme-toggle]');
            $this->waitForTheme($browser, 'light');

            $this->assertSame('light', $this->dataTheme($browser), 'Drugi klik nie wrócił do jasnego motywu.');
            $this->assertSame('false', $this->toggleAttribute($browser, 'aria-pressed'), 'aria-pressed nie wróciło na false.');
            $this->assertSame('Włącz tryb ciemny', $this->toggleAttribute($browser, 'aria-label'), 'Etykieta nie opisuje akcji przejścia w ciemny.');
            $this->assertSame('Tryb ciemny', $this->toggleAttribute($browser, 'title'), 'Podpowiedź nie opisuje akcji przejścia w ciemny.');
            $this->assertSame('light', $this->storedTheme($browser), 'Jasny motyw musi być zapisany jako "light", inaczej wybór ginie.');
            $this->assertSame(
                '#2d343a',
                $this->themeColorMeta($browser),
                'Pasek przeglądarki nie wrócił do jasnego koloru marki (#2d343a).'
            );
        });
    }

    public function test_a_saved_dark_choice_is_applied_before_the_content_is_painted(): void
    {
        $this->seedUser('dusk-theme');

        $this->browse(function (Browser $browser): void {
            $this->login($browser, 'dusk-theme');

            $this->emulateSystemTheme($browser, 'light');
            $browser->script('return window.localStorage.setItem("sk-theme", "dark");');
            $this->traceFirstThemeAttribute($browser);

            $browser->visit('/')->waitForText('Kolejka napraw');
            $browser->waitFor('[data-theme-toggle]');

            // The trace is installed before any page script runs, so it sees the
            // very first `data-theme` write together with the state of the DOM at
            // that moment.
            $trace = $browser->script('return window.__themeTrace;')[0] ?? null;

            $this->assertIsArray($trace, 'Nie udało się odczytać śladu ustawiania data-theme.');

            $this->assertSame('dark', $trace['theme'] ?? null, 'Zapisany ciemny motyw nie został zastosowany.');
            $this->assertFalse(
                (bool) ($trace['bodyParsed'] ?? true),
                'data-theme pojawiło się dopiero po sparsowaniu treści — użytkownik zobaczy jasne migotanie.'
            );

            $this->assertSame('dark', $this->dataTheme($browser));
            $this->assertSame('true', $this->toggleAttribute($browser, 'aria-pressed'), 'Po wejściu aria-pressed nie pokazuje ciemnego motywu.');
        });
    }

    public function test_dark_mode_has_no_contrast_violations(): void
    {
        $this->seedUser('dusk-theme');

        $this->browse(function (Browser $browser): void {
            $this->login($browser, 'dusk-theme');

            $this->emulateSystemTheme($browser, 'light');
            $this->forgetThemeChoice($browser);
            $browser->visit('/')->waitForText('Kolejka napraw');
            $browser->waitFor('[data-theme-toggle]');

            $browser->click('[data-theme-toggle]');
            $this->waitForTheme($browser, 'dark');

            // The queue is `/` (the route name is `services.queue`; there is no
            // `/services/queue` path), the intake form is `/services/create`.
            $views = [
                'kolejka' => '/',
                'formularz przyjęcia' => '/services/create',
            ];

            $problems = [];

            foreach ($views as $label => $path) {
                $browser->visit($path);
                $browser->waitFor('[data-theme-toggle]');

                // The audit is worthless if the page quietly fell back to light.
                $this->assertSame('dark', $this->dataTheme($browser), 'Motyw nie przetrwał wejścia na widok „'.$label.'".');

                $violations = $this->darkModeViolations($browser);

                if ($violations !== []) {
                    $problems[$label] = $this->describe($violations);
                }
            }

            $report = [];

            foreach ($problems as $label => $description) {
                $report[] = $label.' → '.$description;
            }

            $this->assertSame(
                [],
                $problems,
                'Tryb ciemny: kontrast lub błędy critical/serious — '.implode(' | ', $report)
            );
        });
    }

    /**
     * Contrast problems and critical/serious findings of the current dark page.
     *
     * @return list<array<string, mixed>>
     */
    private function darkModeViolations(Browser $browser): array
    {
        return array_values(array_filter(
            $this->axeViolations($browser),
            static fn (array $violation): bool => ($violation['id'] ?? '') === 'color-contrast'
                || in_array((string) ($violation['impact'] ?? ''), ['critical', 'serious'], true)
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function axeViolations(Browser $browser): array
    {
        $browser->script(
            "(function () {
                if (typeof window.axe !== 'undefined') { return; }
                var script = document.createElement('script');
                script.src = '/js/axe.min.js';
                document.head.appendChild(script);
            })();"
        );

        $browser->waitUntil('return typeof window.axe !== "undefined";', 20);

        // executeAsyncScript waits for axe's own callback; polling a global from
        // PHP stalls because headless Chrome throttles background timers.
        $result = $browser->driver->executeAsyncScript(
            'var done = arguments[arguments.length - 1];'
            .'axe.run(document, {resultTypes: ["violations"]})'
            .'.then(function (result) { done(result.violations); })'
            .'.catch(function (error) { done({ error: String(error) }); });'
        );

        if (is_array($result) && isset($result['error'])) {
            $this->fail('axe nie uruchomił się: '.$result['error']);
        }

        /** @var list<array<string, mixed>> $result */
        $result = is_array($result) ? array_values($result) : [];

        return $result;
    }

    /**
     * @param  list<array<string, mixed>>  $violations
     */
    private function describe(array $violations): string
    {
        return implode(' | ', array_map(static function (array $violation): string {
            $nodes = is_array($violation['nodes'] ?? null) ? $violation['nodes'] : [];
            $targets = [];

            foreach (array_slice($nodes, 0, 2) as $node) {
                $target = $node['target'] ?? [];
                $targets[] = is_array($target) ? implode(' ', array_map('strval', $target)) : (string) $target;
            }

            return ($violation['impact'] ?? '?').' · '.($violation['id'] ?? '?').' — '.($violation['help'] ?? '')
                .($targets === [] ? '' : ' ['.implode(' ; ', $targets).']');
        }, $violations));
    }

    /** Makes the suite independent of the host's light/dark setting. */
    private function emulateSystemTheme(Browser $browser, string $theme): void
    {
        (new ChromeDevToolsDriver($browser->driver))->execute('Emulation.setEmulatedMedia', [
            'features' => [
                ['name' => 'prefers-color-scheme', 'value' => $theme],
            ],
        ]);
    }

    /**
     * Installs, before any page script runs, an observer that records the first
     * `data-theme` write together with whether the body already existed at that
     * moment. A synchronous <head> script writes the attribute while the body is
     * still unparsed; anything later is a visible flash of the light theme.
     */
    private function traceFirstThemeAttribute(Browser $browser): void
    {
        (new ChromeDevToolsDriver($browser->driver))->execute('Page.addScriptToEvaluateOnNewDocument', [
            'source' => <<<'JS'
                window.__themeTrace = { theme: null, bodyParsed: null };
                new MutationObserver(function (records, observer) {
                    var theme = document.documentElement && document.documentElement.getAttribute('data-theme');
                    if (theme && window.__themeTrace.theme === null) {
                        window.__themeTrace.theme = theme;
                        window.__themeTrace.bodyParsed = document.body !== null;
                        observer.disconnect();
                    }
                }).observe(document, { attributes: true, subtree: true, attributeFilter: ['data-theme'] });
                JS,
        ]);
    }

    private function forgetThemeChoice(Browser $browser): void
    {
        $browser->script('return window.localStorage.removeItem("sk-theme");');
    }

    private function waitForTheme(Browser $browser, string $theme): void
    {
        $browser->waitUntil(
            'return document.documentElement.getAttribute("data-theme") === "'.$theme.'";',
            10,
            'Motyw nie zmienił się na '.$theme.'.'
        );
    }

    private function dataTheme(Browser $browser): ?string
    {
        $value = $browser->script('return document.documentElement.getAttribute("data-theme");')[0] ?? null;

        return is_string($value) ? $value : null;
    }

    private function storedTheme(Browser $browser): ?string
    {
        $value = $browser->script('return window.localStorage.getItem("sk-theme");')[0] ?? null;

        return is_string($value) ? $value : null;
    }

    private function toggleAttribute(Browser $browser, string $attribute): ?string
    {
        $value = $browser->script(
            'var toggle = document.querySelector("[data-theme-toggle]");'
            .'return toggle ? toggle.getAttribute("'.$attribute.'") : null;'
        )[0] ?? null;

        return is_string($value) ? $value : null;
    }

    private function themeColorMeta(Browser $browser): ?string
    {
        $value = $browser->script(
            'var meta = document.querySelector(\'meta[name="theme-color"]\');'
            .'return meta ? meta.getAttribute("content") : null;'
        )[0] ?? null;

        return is_string($value) ? $value : null;
    }
}
