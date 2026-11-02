<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Domain\Enums\Role;
use App\Domain\Enums\ServiceStatus;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Scenario 7 and 12 of the plan (section 10.2): releasing from a phone-sized
 * window and the three-role permissions (defect D-10 regression).
 */
final class MobileReleaseTest extends DuskTestCase
{
    public function test_release_from_a_phone_sized_window_succeeds(): void
    {
        $this->seedUser('dusk-employee', Role::Employee);

        $phone = '600 700 811';

        $this->browse(function (Browser $browser) use ($phone): void {
            $this->login($browser, 'dusk-employee', 'DuskHaslo1234')
                ->clickLink('+ Przyjmij laptopa')
                ->waitForText('Przyjęcie laptopa')
                ->type('phone', $phone)
                ->type('device_model', 'HP EliteBook 840')
                ->type('issue', 'Do wydania — test telefonu.')
                ->type('price', '150,00')
                ->type('cost', '50,00')
                ->press('Przyjmij zlecenie')
                ->waitForText('Dane zlecenia');
        });

        $this->moveToStatus($phone, ServiceStatus::ReadyForPickup);

        $service = $this->serviceByPhone($phone);
        $this->assertNotNull($service);

        $this->browse(function (Browser $browser) use ($service): void {
            $browser->resize(390, 844)
                ->visit('/services/'.$service->id)
                ->waitForText('Do wydania')
                ->assertSee('Wydaj laptopa')
                ->clickLink('Wydaj laptopa')
                ->waitForText('Protokół wydania')
                ->type('price', '150,00')
                ->type('cost', '50,00');

            // The fixed bottom navigation covers the lower edge of a phone screen,
            // so the submit button is centred before clicking it.
            $browser->script('window.scrollTo(0, document.body.scrollHeight);');

            $browser->press('Potwierdź wydanie')
                ->waitForText('Wydane')
                ->assertSee('Wydane');
        });

        $released = $this->serviceByPhone($phone);
        $this->assertNotNull($released);
        $this->assertSame(ServiceStatus::Released, $released->status);
        $this->assertNotNull($released->releasedAt);
    }

    public function test_employee_does_not_see_the_account_panel(): void
    {
        $this->seedUser('dusk-employee');

        $this->browse(function (Browser $browser): void {
            $this->login($browser, 'dusk-employee', 'DuskHaslo1234')
                ->assertDontSeeLink('Użytkownicy');
        });
    }

    public function test_layout_has_no_horizontal_scroll_on_a_phone(): void
    {
        $this->seedUser('dusk-admin');

        $this->browse(function (Browser $browser): void {
            $this->login($browser)
                ->resize(390, 844)
                ->visit('/')
                ->waitForText('Kolejka napraw');

            $scrollWidth = $browser->script('return document.documentElement.scrollWidth;')[0];
            $clientWidth = $browser->script('return document.documentElement.clientWidth;')[0];

            $this->assertSame((int) $clientWidth, (int) $scrollWidth, 'Strona przewija się w poziomie na telefonie.');
        });
    }
}
