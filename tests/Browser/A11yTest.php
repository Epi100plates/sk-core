<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Domain\Enums\Role;
use App\Domain\Enums\ServiceStatus;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Accessibility audit with axe-core (task T8.3, acceptance criterion 7).
 *
 * axe is a plain JavaScript file injected into the page — no Node.js, no bundler
 * (decision D1). `axe.run` is asynchronous, so the result is parked in a global
 * and read back once it appears.
 */
final class A11yTest extends DuskTestCase
{
    public function test_login_page_has_no_critical_accessibility_violations(): void
    {
        $this->seedUser('dusk-a11y');

        $this->browse(function (Browser $browser): void {
            $browser->driver->manage()->deleteAllCookies();
            $browser->visit('/login')->waitForText('Zaloguj');

            $this->assertNoCriticalViolations($browser, '/login');
        });
    }

    public function test_queue_page_has_no_critical_accessibility_violations(): void
    {
        $this->seedUser('dusk-a11y');

        $this->browse(function (Browser $browser): void {
            $this->login($browser, 'dusk-a11y', 'DuskHaslo1234');

            $this->assertNoCriticalViolations($browser, 'kolejka');
        });
    }

    public function test_acceptance_form_has_no_critical_accessibility_violations(): void
    {
        $this->seedUser('dusk-a11y');

        $this->browse(function (Browser $browser): void {
            $this->login($browser, 'dusk-a11y', 'DuskHaslo1234')
                ->visit('/services/create')
                ->waitForText('Przyjęcie laptopa');

            $this->assertNoCriticalViolations($browser, 'formularz przyjęcia');
        });
    }

    public function test_service_card_has_no_critical_accessibility_violations(): void
    {
        $this->seedUser('dusk-a11y');

        $phone = $this->uniquePhone();

        $this->browse(function (Browser $browser) use ($phone): void {
            $this->login($browser, 'dusk-a11y', 'DuskHaslo1234')
                ->visit('/services/create')
                ->waitForText('Przyjęcie laptopa')
                ->type('phone', $phone)
                ->type('device_model', 'A11y test laptop')
                ->type('issue', 'Test dostępności karty zlecenia.')
                ->press('Przyjmij zlecenie')
                ->waitForText('Dane zlecenia');
        });

        $service = $this->serviceByPhone($phone);
        $this->assertNotNull($service);

        $this->browse(function (Browser $browser) use ($service): void {
            $browser->visit('/services/'.$service->id)->waitForText('Dane zlecenia');

            $this->assertNoCriticalViolations($browser, 'karta zlecenia');
        });
    }

    public function test_account_panel_has_no_critical_accessibility_violations(): void
    {
        $this->seedUser('dusk-a11y', Role::Admin);

        $this->browse(function (Browser $browser): void {
            $this->login($browser, 'dusk-a11y', 'DuskHaslo1234')
                ->visit('/admin/users')
                ->waitForText('Użytkownicy');

            $this->assertNoCriticalViolations($browser, 'panel kont');
        });
    }

    public function test_release_form_has_no_critical_accessibility_violations(): void
    {
        $this->seedUser('dusk-a11y', Role::Admin);

        $phone = $this->uniquePhone();

        $this->browse(function (Browser $browser) use ($phone): void {
            $this->login($browser, 'dusk-a11y', 'DuskHaslo1234')
                ->visit('/services/create')
                ->waitForText('Przyjęcie laptopa')
                ->type('phone', $phone)
                ->type('device_model', 'A11y release laptop')
                ->type('issue', 'Test dostępności formularza wydania.')
                ->press('Przyjmij zlecenie')
                ->waitForText('Dane zlecenia');
        });

        $this->moveToStatus($phone, ServiceStatus::ReadyForPickup);
        $service = $this->serviceByPhone($phone);
        $this->assertNotNull($service);

        $this->browse(function (Browser $browser) use ($service): void {
            $browser->visit('/services/'.$service->id.'/release')->waitForText('Protokół wydania');

            $this->assertNoCriticalViolations($browser, 'formularz wydania');
        });
    }

    /**
     * Runs axe-core and fails on critical violations (plan section 7.7, F-48).
     *
     * axe is loaded as a static file (`public/js/axe.min.js`) because the strict
     * CSP forbids inline scripts — injecting it as a `<script src>` is exactly how
     * a real browser would fetch it.
     */
    private function assertNoCriticalViolations(Browser $browser, string $view): void
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

        // executeAsyncScript waits for axe's own callback; polling a global from PHP
        // stalls because headless Chrome throttles background timers.
        $violations = $browser->driver->executeAsyncScript(
            'var done = arguments[arguments.length - 1];'
            .'axe.run(document, {resultTypes: ["violations"]})'
            .'.then(function (result) { done(result.violations); })'
            .'.catch(function (error) { done({ error: String(error) }); });'
        );

        if (is_array($violations) && isset($violations['error'])) {
            $this->fail('axe nie uruchomił się na widoku „'.$view.'”: '.$violations['error']);
        }

        /** @var list<array<string, mixed>> $violations */
        $violations = is_array($violations) ? $violations : [];

        $blocking = [];
        $advisory = [];

        foreach ($violations as $violation) {
            $impact = (string) ($violation['impact'] ?? 'unknown');
            $nodes = is_array($violation['nodes'] ?? null) ? $violation['nodes'] : [];
            $targets = [];

            foreach (array_slice($nodes, 0, 2) as $node) {
                $target = $node['target'] ?? [];
                $targets[] = is_array($target) ? implode(' ', array_map('strval', $target)) : (string) $target;
            }

            $entry = $impact.' · '.($violation['id'] ?? '?').' — '.($violation['help'] ?? '')
                .($targets === [] ? '' : ' ['.implode(' ; ', $targets).']');

            if ($impact === 'critical' || $impact === 'serious') {
                $blocking[] = $entry;
            } else {
                $advisory[] = $entry;
            }
        }

        $this->assertSame(
            [],
            $blocking,
            'Błędy dostępności (critical/serious) na widoku „'.$view.'”: '.implode(' | ', $blocking)
        );

        if ($advisory !== []) {
            fwrite(STDERR, "\n[axe] {$view}: ".implode(' | ', $advisory)."\n");
        }
    }
}
