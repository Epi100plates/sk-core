<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\AdvanceServiceStatus;
use App\Domain\Data\ServiceRecord;
use App\Domain\Data\UserRecord;
use App\Support\Audit\AuditContext;
use App\Support\ClientStatus\ClientStatusSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * `POST /services/{id}/advance` (F-24, F-33).
 *
 * Invalid transitions are turned into a 422 by the exception handler; final
 * services have no button at all (defect D-46).
 */
final class AdvanceStatusController extends Controller
{
    public function __invoke(
        Request $request,
        ServiceRecord $service,
        AdvanceServiceStatus $advance,
        ClientStatusSync $clientStatus,
    ): RedirectResponse|JsonResponse {
        $this->authorize('advance', $service);

        $user = $request->user();

        if (! $user instanceof UserRecord) {
            abort(401);
        }

        $updated = $advance->handle($service, $user, AuditContext::fromRequest($request));

        // Same snapshot as an edit: the customer page follows the stage. The JSON
        // answer stays untouched — the card script only reads the status.
        if ($clientStatus->enabled()
            && ! $clientStatus->sync($updated, ClientStatusSync::EVENT_STATUS_CHANGED)['ok']
            && ! $request->expectsJson()) {
            session()->flash('warning', 'Status został zmieniony, ale nie udało się zaktualizować strony klienta. Spróbuj ponownie z karty zlecenia.');
        }

        if ($request->expectsJson()) {
            return response()->json([
                'id' => $updated->id,
                'status' => $updated->status->value,
                'status_label' => $updated->status->label(),
                'message' => 'Status zmieniony na „'.$updated->status->label().'”.',
            ]);
        }

        return back()->with('status', 'Status zmieniony na „'.$updated->status->label().'”.');
    }
}
