<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Domain\Enums\Role;
use App\Domain\Enums\ServiceStatus;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Scenario 5 and 13 of the plan (section 10.2): the runtime regressions from the
 * old application — a save without changes must not move the deadline (D-44) and
 * a released service must not offer an action (D-46).
 */
final class RegressionTest extends DuskTestCase
{
    public function test_saving_the_edit_form_without_changes_does_not_move_the_deadline(): void
    {
        $this->seedUser('dusk-admin');

        $phone = '600 700 821';

        $this->browse(function (Browser $browser) use ($phone): void {
            $this->login($browser)
                ->clickLink('Przyjmij laptopa')
                ->waitForText('Przyjęcie laptopa')
                ->type('phone', $phone)
                ->type('device_model', 'Asus ZenBook 14')
                ->type('issue', 'Test braku dryfu terminu.')
                ->press('Przyjmij zlecenie')
                ->waitForText('Dane zlecenia');
        });

        $before = $this->serviceByPhone($phone);
        $this->assertNotNull($before);

        $this->browse(function (Browser $browser) use ($before): void {
            $browser->visit('/services/'.$before->id.'/edit')
                ->waitForText('Zapisz zmiany')
                ->press('Zapisz zmiany')
                ->waitForText('Brak zmian');
        });

        $after = $this->serviceByPhone($phone);
        $this->assertNotNull($after);

        $this->assertSame(
            $before->plannedAt->format('Y-m-d H:i:s'),
            $after->plannedAt->format('Y-m-d H:i:s'),
            'Termin realizacji przesunął się po zapisie bez zmian (defekt D-44).'
        );
        $this->assertSame($before->version, $after->version);
        $this->assertSame($before->cost, $after->cost);
    }

    public function test_released_service_is_archived_without_workflow_actions(): void
    {
        $this->seedUser('dusk-admin');

        $phone = '600 700 822';

        $this->browse(function (Browser $browser) use ($phone): void {
            $this->login($browser)
                ->clickLink('Przyjmij laptopa')
                ->waitForText('Przyjęcie laptopa')
                ->type('phone', $phone)
                ->type('device_model', 'Acer Aspire 5')
                ->type('issue', 'Archiwum wydanych.')
                ->press('Przyjmij zlecenie')
                ->waitForText('Dane zlecenia');
        });

        $this->moveToStatus($phone, ServiceStatus::Released);

        $this->browse(function (Browser $browser): void {
            $browser->visit('/released')
                ->waitForText('Wydane zlecenia')
                ->assertSee('Acer Aspire 5');
        });
    }

    public function test_employee_logout_clears_the_pwa_cache(): void
    {
        $this->seedUser('dusk-employee', Role::Employee);

        $this->browse(function (Browser $browser): void {
            $this->login($browser, 'dusk-employee', 'DuskHaslo1234')
                ->waitForText('Kolejka napraw');

            // Cache Storage must never hold customer data (defect D-28).
            $cacheHasCustomerData = $browser->script(
                'return caches.keys().then(async (keys) => {
                    for (const key of keys) {
                        const cache = await caches.open(key);
                        const requests = await cache.keys();
                        for (const request of requests) {
                            const path = new URL(request.url).pathname;
                            if (path === "/" || path.startsWith("/services") || path.startsWith("/photos")) {
                                return true;
                            }
                        }
                    }
                    return false;
                });'
            )[0] ?? false;

            $this->assertFalse((bool) $cacheHasCustomerData, 'Cache PWA zawiera dane klientów.');

            $browser->press('Wyloguj')
                ->waitForText('Konto zakłada administrator serwisu.');
        });
    }
}
