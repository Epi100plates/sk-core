<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\MarkConfirmationIssued;
use App\Contracts\ServiceEventRepository;
use App\Domain\Data\ServiceRecord;
use App\Domain\Data\UserRecord;
use App\Domain\FieldLabels;
use App\Support\Audit\AuditContext;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * A4 printouts (feature F-55): the acceptance confirmation and the release
 * protocol. Both require a session — a printed document carries the customer's
 * phone number and device password, so it must never be publicly reachable.
 */
final class PrintController extends Controller
{
    public function acceptance(
        Request $request,
        ServiceRecord $service,
        MarkConfirmationIssued $markConfirmation,
    ): View {
        $this->authorize('print', $service);

        $user = $request->user();

        if ($user instanceof UserRecord && ! $service->hasIssuedConfirmation()) {
            $service = $markConfirmation->handle($service, $user, AuditContext::fromRequest($request));
        }

        return view('print.acceptance', [
            'service' => $service,
            'timezone' => FieldLabels::displayTimezone(),
            'printedAt' => now()->utc(),
        ]);
    }

    public function release(ServiceRecord $service, ServiceEventRepository $events): View
    {
        $this->authorize('print', $service);

        return view('print.release', [
            'service' => $service,
            'timezone' => FieldLabels::displayTimezone(),
            'printedAt' => now()->utc(),
            'events' => $events->forService($service->id, 5),
        ]);
    }
}
