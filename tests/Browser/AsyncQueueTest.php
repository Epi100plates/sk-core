<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Actions\AcceptService;
use App\Actions\ServiceDetails;
use App\Contracts\ServiceRepository;
use App\Domain\Data\UserRecord;
use App\Domain\Enums\ServiceStatus;
use App\Support\Audit\AuditContext;
use Carbon\CarbonImmutable;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * The queue updates in place (ADR 0017): a stage chip, the reset link and the
 * "Dalej" button re-render `[data-async-region]` blocks instead of reloading the
 * page. HTTP tests cannot prove that — they only see the markup — so the proof
 * is a JavaScript global that a navigation would wipe, plus the address bar
 * keeping the chosen stage.
 */
final class AsyncQueueTest extends DuskTestCase
{
    public function test_the_queue_filters_and_advances_without_reloading(): void
    {
        $actor = $this->seedUser('dusk-admin');
        $suffix = (string) random_int(1000, 9999);
        $pending = 'Async Pending '.$suffix;
        $progress = 'Async Progress '.$suffix;
        $paged = 'Async Page '.$suffix;

        $this->acceptService($actor, $pending);
        $this->acceptService($actor, $progress, ServiceStatus::InProgress);

        // 51 matches give the list a second page; handed-over ones keep the
        // working queue (and the other browser tests) unchanged.
        for ($i = 1; $i <= 51; $i++) {
            $this->acceptService($actor, $paged.' '.$i, ServiceStatus::Released, $actor);
        }

        $this->browse(function (Browser $browser) use ($pending, $progress, $paged): void {
            $this->login($browser);

            $browser->visit('/')
                ->waitForText($pending)
                ->assertSee($progress)
                // Wiped by a reload, so it proves the document survived.
                ->script('window.__serwisProbe = "alive";');

            // A stage chip filters the list in place and the address bar follows.
            // The sidebar keeps a shortcut to every open job, so the assertion
            // is scoped to the list the chip actually filters.
            $browser->click('.stat[data-stage="in_progress"]')
                ->waitUntilMissing('.queue-results .queue-group[data-stage="pending"]')
                ->assertSee($progress)
                ->assertQueryStringHas('status', 'in_progress')
                ->assertScript('window.__serwisProbe === "alive"');

            // The same chip pressed again turns the filter off: a chip that can
            // only be switched on is a trap.
            $browser->click('.stat[data-stage="in_progress"]')
                ->waitFor('.queue-results .service-card[data-stage="pending"]')
                ->assertQueryStringMissing('status')
                ->assertScript('window.__serwisProbe === "alive"');

            // "Pokaż wszystkie" brings the whole queue back the same way.
            $browser->click('.stat[data-stage="in_progress"]')
                ->waitUntilMissing('.queue-results .queue-group[data-stage="pending"]')
                ->clickLink('Pokaż wszystkie')
                ->waitFor('.queue-results .service-card[data-stage="pending"]')
                ->assertQueryStringMissing('status')
                ->assertScript('window.__serwisProbe === "alive"');

            // Pagination swaps only the list: page two holds the 51st result.
            $browser->type('q', $paged)
                ->waitUntilMissing('.queue-results .service-card[data-stage="pending"]')
                ->assertSee($paged.' 1')
                ->click('a[rel="next"]')
                ->waitForText($paged.' 51')
                ->assertQueryStringHas('page', '2')
                ->assertScript('window.__serwisProbe === "alive"')
                ->assertScript('document.querySelectorAll(".service-card").length === 1');

            // Advancing a tile moves it to the next section without a reload; the
            // server confirmation arrives in the flash region.
            $browser->type('q', $pending)
                ->waitFor('.queue-results .service-card[data-stage="pending"]')
                ->click('.queue-group[data-stage="pending"] button[type="submit"]')
                ->waitUntilMissing('.queue-group[data-stage="pending"]')
                ->assertSee('Status zmieniony')
                ->assertQueryStringHas('q', $pending)
                ->assertScript('window.__serwisProbe === "alive"');
        });
    }

    private function acceptService(
        UserRecord $actor,
        string $model,
        ServiceStatus $status = ServiceStatus::Pending,
        ?UserRecord $releasedBy = null,
    ): void {
        $service = app(AcceptService::class)->handle(new ServiceDetails(
            phone: '700 '.random_int(100, 999).' '.random_int(100, 999),
            deviceModel: $model,
            serialNumber: null,
            issue: 'Test aktualizacji w miejscu.',
            devicePassword: null,
            notes: null,
            isExpress: false,
            plannedAt: CarbonImmutable::now('UTC')->addDays(2),
            price: '199.00',
            cost: '50.00',
            sealNumber: null,
        ), $actor, AuditContext::console());

        if ($status === ServiceStatus::Pending) {
            return;
        }

        app(ServiceRepository::class)->save($service->with([
            'status' => $status->value,
            'released_at' => $status === ServiceStatus::Released ? CarbonImmutable::now('UTC')->format('Y-m-d H:i:s') : null,
            'released_by' => $status === ServiceStatus::Released ? $releasedBy?->id : null,
        ]));
    }
}
