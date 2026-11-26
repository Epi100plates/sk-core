<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\ServiceRepository;
use App\Support\ClientStatus\ClientStatusSync;
use Illuminate\Console\Command;

/**
 * `php artisan services:sync-status` — sends orders to the CMS when the link was
 * never created (CMS was down, secret added later), decision D19/0019.
 *
 * Only references are printed: phone numbers and issue texts never reach the
 * console log, just like they never reach the application log.
 */
final class SyncClientStatus extends Command
{
    protected $signature = 'services:sync-status
        {--all : Wysyła także zlecenia, które mają już link}
        {--service= : ID pojedynczego zlecenia}';

    protected $description = 'Tworzy brakujące linki statusu klienta w CMS';

    public function handle(ServiceRepository $services, ClientStatusSync $clientStatus): int
    {
        if (! $clientStatus->enabled()) {
            $this->warn('Integracja statusu klienta jest wyłączona (CLIENT_STATUS_ENABLED, CLIENT_STATUS_URL, CLIENT_STATUS_SECRET).');

            return self::SUCCESS;
        }

        $single = $this->option('service');

        if (is_string($single) && $single !== '') {
            return $this->syncOne($services, $clientStatus, (int) $single);
        }

        $every = (bool) $this->option('all');
        $sent = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($services->all() as $service) {
            if (! $every && (! $service->status->isActive() || $service->clientStatusUrl !== null)) {
                $skipped++;

                continue;
            }

            $result = $clientStatus->sync($service, ClientStatusSync::EVENT_STATUS_CHANGED);

            if (! $result['ok']) {
                $failed++;
                $this->warn($service->reference.': '.($result['error'] ?? 'nieznany błąd'));

                continue;
            }

            $sent++;
            $this->line($service->reference.': '.($result['url'] ?? ''));
        }

        $this->line(sprintf('Wysłano: %d, pominięto: %d, błędy: %d', $sent, $skipped, $failed));

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function syncOne(ServiceRepository $services, ClientStatusSync $clientStatus, int $id): int
    {
        $service = $services->find($id);

        if ($service === null) {
            $this->error('Nie znaleziono zlecenia #'.$id.'.');

            return self::FAILURE;
        }

        $result = $clientStatus->sync($service, ClientStatusSync::EVENT_STATUS_CHANGED);

        if (! $result['ok']) {
            $this->warn($service->reference.': '.($result['error'] ?? 'nieznany błąd'));

            return self::FAILURE;
        }

        $this->line($service->reference.': '.($result['url'] ?? ''));

        return self::SUCCESS;
    }
}
