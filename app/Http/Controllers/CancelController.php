<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CancelService;
use App\Domain\Data\ServiceRecord;
use App\Domain\Data\UserRecord;
use App\Http\Requests\CancelServiceRequest;
use App\Support\Audit\AuditContext;
use Illuminate\Http\RedirectResponse;

/**
 * Cancelling an order (F-56). Available from the service card; the reason is
 * mandatory and lands in the audit trail.
 */
final class CancelController extends Controller
{
    public function __invoke(
        CancelServiceRequest $request,
        ServiceRecord $service,
        CancelService $cancel,
    ): RedirectResponse {
        $this->authorize('cancel', $service);

        $user = $request->user();

        if (! $user instanceof UserRecord) {
            abort(401);
        }

        $cancelled = $cancel->handle($service, $request->reason(), $user, AuditContext::fromRequest($request));

        return redirect()
            ->route('services.show', $cancelled)
            ->with('status', 'Zlecenie '.$cancelled->reference.' zostało anulowane.');
    }
}
