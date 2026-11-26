<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Data\ServiceRecord;
use App\Support\ClientStatus\ClientStatusSync;
use Illuminate\Http\RedirectResponse;

/**
 * `POST /services/{service}/client-status` (decision D19/0019).
 *
 * The link is created automatically on acceptance and on every stage change; when
 * the CMS was unreachable, the shop retries from the order card instead of
 * re-creating the order. Authorisation is the normal edit right, so a closed
 * order cannot be re-sent by hand (the command `services:sync-status` can).
 */
final class ClientStatusController extends Controller
{
    public function store(
        ServiceRecord $service,
        ClientStatusSync $clientStatus,
    ): RedirectResponse {
        $this->authorize('update', $service);

        if (! $clientStatus->enabled()) {
            return redirect()
                ->route('services.show', $service)
                ->with('warning', 'Integracja statusu klienta jest wyłączona — uzupełnij CLIENT_STATUS_* w konfiguracji.');
        }

        $result = $clientStatus->sync($service, ClientStatusSync::EVENT_STATUS_CHANGED);

        if ($result['ok']) {
            return redirect()
                ->route('services.show', $service)
                ->with('status', 'Link statusu klienta został utworzony.');
        }

        return redirect()
            ->route('services.show', $service)
            ->with('warning', 'Nie udało się utworzyć linku statusu klienta: '.($result['error'] ?? 'nieznany błąd'));
    }
}
