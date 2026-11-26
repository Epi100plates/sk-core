<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\ReleaseDetails;
use App\Actions\ReleaseService;
use App\Contracts\ServicePhotoRepository;
use App\Domain\Data\ServiceRecord;
use App\Domain\Data\UserRecord;
use App\Http\Requests\ReleaseServiceRequest;
use App\Support\Audit\AuditContext;
use App\Support\ClientStatus\ClientStatusSync;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Releasing a laptop (F-39…F-42). One form for phone and desktop, an editable
 * release date and a second confirmation step.
 */
final class ReleaseController extends Controller
{
    public function show(ServiceRecord $service, ServicePhotoRepository $photos): View
    {
        $this->authorize('release', $service);

        return view('services.release', [
            'service' => $service,
            'photos' => $photos->forService($service->id),
            'defaultReleasedAt' => ReleaseServiceRequest::defaultReleasedAt(),
        ]);
    }

    public function store(
        ReleaseServiceRequest $request,
        ServiceRecord $service,
        ReleaseService $release,
        ClientStatusSync $clientStatus,
    ): RedirectResponse {
        $this->authorize('release', $service);

        if ($request->releasedAtIsFromTheFuture()) {
            throw ValidationException::withMessages([
                'released_at' => 'Data wydania nie może być z przyszłości.',
            ]);
        }

        $user = $request->user();

        if (! $user instanceof UserRecord) {
            abort(401);
        }

        $details = ReleaseDetails::fromArray($request->validated());
        $released = $release->handle($service, $details, $user, AuditContext::fromRequest($request));

        if ($clientStatus->enabled() && ! $clientStatus->sync($released, ClientStatusSync::EVENT_RELEASED)['ok']) {
            session()->flash('warning', 'Zlecenie zostało wydane, ale nie udało się zaktualizować strony klienta. Spróbuj ponownie z karty zlecenia.');
        }

        if ($details->printProtocol) {
            return redirect()
                ->route('print.release', $released)
                ->with('status', 'Zlecenie '.$released->reference.' wydane.');
        }

        return redirect()
            ->route('services.show', $released)
            ->with('status', 'Zlecenie '.$released->reference.' wydane klientowi.');
    }
}
