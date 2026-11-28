<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\ApproveRelease;
use App\Actions\ReturnServiceToQueue;
use App\Domain\Data\ServiceRecord;
use App\Domain\Data\UserRecord;
use App\Policies\ServicePolicy;
use App\Support\Audit\AuditContext;
use App\Support\ClientStatus\ClientStatusSync;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The owner's side of the release approval flow (2026-11-27).
 *
 * The technician's "Gotowy do wydania" request goes through the normal
 * `services.advance` endpoint (`in_progress` → `awaiting_approval`); these two
 * actions are the boss answer: accept (→ `ready_for_pickup`, the release counter
 * exactly as before) or return with remarks (→ `with_remarks`).
 *
 * Authorization is enforced by {@see ServicePolicy} and repeated in
 * the actions, so a direct POST from a non-owner account is a 403, not a UI quirk.
 */
final class ReleaseApprovalController extends Controller
{
    public function approve(
        Request $request,
        ServiceRecord $service,
        ApproveRelease $approve,
        ClientStatusSync $clientStatus,
    ): RedirectResponse {
        $this->authorize('approveRelease', $service);

        $actor = $this->actor($request);
        $approved = $approve->handle($service, $actor, AuditContext::fromRequest($request));

        // The customer page follows the stage; a broken CMS never blocks the
        // decision — it only leaves the old snapshot in place with a warning.
        if ($clientStatus->enabled()
            && ! $clientStatus->sync($approved, ClientStatusSync::EVENT_STATUS_CHANGED)['ok']) {
            session()->flash('warning', 'Wydanie zatwierdzone, ale nie udało się zaktualizować strony klienta.');
        }

        return redirect()
            ->route('services.show', $approved)
            ->with('status', 'Wydanie zatwierdzone — zlecenie jest „Do wydania”.');
    }

    public function returnToQueue(
        Request $request,
        ServiceRecord $service,
        ReturnServiceToQueue $return,
        ClientStatusSync $clientStatus,
    ): RedirectResponse {
        $this->authorize('returnToQueue', $service);

        $validated = $request->validate([
            'approval_remarks' => ['required', 'string', 'min:3', 'max:1000'],
        ], [
            'approval_remarks.required' => 'Podaj uwagi do poprawy.',
            'approval_remarks.min' => 'Uwagi muszą mieć co najmniej 3 znaki.',
        ]);

        $actor = $this->actor($request);
        $returned = $return->handle(
            $service,
            (string) $validated['approval_remarks'],
            $actor,
            AuditContext::fromRequest($request),
        );

        if ($clientStatus->enabled()
            && ! $clientStatus->sync($returned, ClientStatusSync::EVENT_STATUS_CHANGED)['ok']) {
            session()->flash('warning', 'Zlecenie zwrócone z uwagami, ale nie udało się zaktualizować strony klienta.');
        }

        return redirect()
            ->route('services.show', $returned)
            ->with('status', 'Zlecenie wróciło do kolejki z uwagami.');
    }

    private function actor(Request $request): UserRecord
    {
        $user = $request->user();

        if (! $user instanceof UserRecord) {
            abort(401);
        }

        return $user;
    }
}
