<?php

declare(strict_types=1);

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * The queue as the workshop uses it: one live search in the header, password
 * visible until someone hides it, and a tile that opens the edit form.
 * JavaScript behaviour cannot be proven by the HTTP tests, so it runs in Chrome.
 */
final class QueueSearchTest extends DuskTestCase
{
    public function test_live_search_password_and_tile_navigation(): void
    {
        $this->seedUser('dusk-admin');

        $this->browse(function (Browser $browser): void {
            $this->login($browser);

            foreach ([
                ['LiveSearch Dell 7420', '600 700 831', 'Tajne123'],
                ['LiveSearch Lenovo T14', '600 700 832', ''],
            ] as [$model, $phone, $password]) {
                $browser->visit('/services/create')
                    ->waitForText('Przyjęcie laptopa')
                    ->type('phone', $phone)
                    ->type('device_model', $model)
                    ->type('issue', 'Test wyszukiwania na żywo.');

                if ($password !== '') {
                    $browser->type('device_password', $password);
                }

                $browser->press('Przyjmij zlecenie')
                    ->waitForText('Dane zlecenia');
            }

            $browser->visit('/')
                ->waitForText('LiveSearch Dell 7420')
                ->assertSee('LiveSearch Lenovo T14')
                ->assertPresent('.service-card[data-stage="pending"]')
                // Visible by default — no "Pokaż" needed before reading it.
                ->assertSee('Tajne123')
                ->press('Ukryj')
                ->assertDontSee('Tajne123')
                ->press('Pokaż')
                ->assertSee('Tajne123');

            // Typing filters the list without Enter …
            $browser->type('q', 'LiveSearch Dell')
                ->waitUntilMissingText('LiveSearch Lenovo T14')
                ->assertSee('LiveSearch Dell 7420');

            // … and an empty field brings the normal queue back.
            $browser->clear('q')
                ->waitForText('LiveSearch Lenovo T14')
                ->assertSee('LiveSearch Dell 7420');

            // The whole tile opens the edit form for that laptop. The list is
            // narrowed first so the click cannot land on another test's card.
            $browser->type('q', 'LiveSearch Dell')
                ->waitUntilMissingText('LiveSearch Lenovo T14')
                ->click('.service-card')
                ->waitForText('Zapisz zmiany')
                ->assertSee('LiveSearch Dell 7420');
        });
    }
}
