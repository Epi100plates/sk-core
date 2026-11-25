<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Domain\Enums\Role;
use App\Domain\Enums\ServiceStatus;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Scenario 1 + 2 + 3 of the plan (section 10.2): login, acceptance with the
 * printed confirmation, and the status workflow — on a desktop window.
 */
final class AcceptanceTest extends DuskTestCase
{
    public function test_employee_accepts_a_laptop_and_prints_the_confirmation(): void
    {
        $this->seedUser('dusk-employee', Role::Employee);

        $phone = '600 700 801';

        $this->browse(function (Browser $browser) use ($phone): void {
            $this->login($browser, 'dusk-employee', 'DuskHaslo1234')
                ->assertSee('Kolejka napraw')
                ->clickLink('Przyjmij laptopa')
                ->waitForText('Przyjęcie laptopa')
                ->type('phone', $phone)
                ->type('device_model', 'Dell Latitude 5420')
                ->type('issue', 'Nie ładuje się po podłączeniu zasilacza.')
                ->type('price', '199,99')
                ->type('cost', '80,50')
                ->check('is_express')
                ->check('print_confirmation')
                ->press('Przyjmij zlecenie')
                ->waitForText('Potwierdzenie przyjęcia')
                ->assertSee('Potwierdzenie przyjęcia')
                ->assertSee('Dell Latitude 5420')
                ->assertSee('199,99 zł');

            $service = $this->serviceByPhone($phone);

            $this->assertNotNull($service, 'Zlecenie nie zostało zapisane.');
            $this->assertSame('199.99', $service->price);
            $this->assertSame('80.50', $service->cost);
            $this->assertTrue($service->hasIssuedConfirmation());

            // The confirmation view is protected and shows the customer data.
            $browser->visit('/logout');
        });
    }

    public function test_service_card_shows_the_workflow_and_the_history(): void
    {
        $this->seedUser('dusk-admin');

        $phone = '600 700 802';

        $this->browse(function (Browser $browser) use ($phone): void {
            $this->login($browser)
                ->clickLink('Przyjmij laptopa')
                ->waitForText('Przyjęcie laptopa')
                ->type('phone', $phone)
                ->type('device_model', 'Lenovo ThinkPad T490')
                ->type('issue', 'Bateria do wymiany.')
                ->press('Przyjmij zlecenie')
                ->waitForText('Dane zlecenia')
                ->assertSee('Lenovo ThinkPad T490')
                ->assertSee('Oczekujące')
                ->assertSee('Historia zmian')
                ->press('Rozpocznij naprawę')
                ->waitForText('W trakcie naprawy')
                ->assertSee('Historia zmian');

            $service = $this->serviceByPhone($phone);
            $this->assertNotNull($service);
            $this->assertSame(ServiceStatus::InProgress, $service->status);
        });
    }

    public function test_queue_hides_released_services_actions(): void
    {
        $this->seedUser('dusk-admin');

        $phone = '600 700 803';

        $this->browse(function (Browser $browser) use ($phone): void {
            $this->login($browser)
                ->clickLink('Przyjmij laptopa')
                ->waitForText('Przyjęcie laptopa')
                ->type('phone', $phone)
                ->type('device_model', 'Wydany laptop testowy')
                ->type('issue', 'Gotowy do odbioru.')
                ->press('Przyjmij zlecenie')
                ->waitForText('Dane zlecenia');
        });

        $this->moveToStatus($phone, ServiceStatus::Released);
        $released = $this->serviceByPhone($phone);
        $this->assertNotNull($released);

        $this->browse(function (Browser $browser) use ($released): void {
            $browser->visit('/')
                ->waitForText('Ostatnio wydane')
                ->assertSee('Wydany laptop testowy');

            // A released service has no workflow action on its own card any more.
            $browser->visit('/services/'.$released->id)
                ->waitForText('Wydane')
                ->assertDontSee('Rozpocznij naprawę')
                ->assertDontSee('Oznacz do odbioru');
        });
    }
}
