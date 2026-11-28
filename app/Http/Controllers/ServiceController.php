<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\AcceptService;
use App\Actions\AttachServicePhotos;
use App\Actions\ConvertIntakeTicket;
use App\Actions\MarkConfirmationIssued;
use App\Actions\ServiceDetails;
use App\Actions\UpdateService;
use App\Contracts\IntakeTicketRepository;
use App\Contracts\ServiceEventRepository;
use App\Contracts\ServicePhotoRepository;
use App\Contracts\UserRepository;
use App\Domain\Data\IntakeTicketRecord;
use App\Domain\Data\ServiceRecord;
use App\Domain\Data\UserRecord;
use App\Domain\Enums\IntakeStatus;
use App\Domain\ServiceWorkflow;
use App\Http\Requests\ServiceRequest;
use App\Support\Audit\AuditContext;
use App\Support\ClientStatus\ClientStatusSync;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\View\View;

/**
 * One set of screens for accepting, showing and editing a service (F-19, F-20,
 * F-23, F-34…F-36, F-48). The old application had two dashboards and four edit
 * paths with different field sets (defect D-41).
 */
final class ServiceController extends Controller
{
    public function create(Request $request, IntakeTicketRepository $intakes): View
    {
        $this->authorize('create', ServiceRecord::class);

        $intake = null;
        $rawIntake = $request->query('intake');

        if (is_numeric($rawIntake)) {
            $record = $intakes->find((int) $rawIntake);

            // Only a submission that is still open can prefill the form; a
            // converted or rejected one would silently reopen old data.
            if ($record instanceof IntakeTicketRecord && ! $record->status->isFinal()) {
                $this->authorize('view', $record);
                $intake = $record;
            }
        }

        return view('services.create', [
            'service' => null,
            'defaultPlannedAt' => ServiceRequest::defaultPlannedAt(),
            'intake' => $intake,
        ]);
    }

    public function store(
        ServiceRequest $request,
        AcceptService $acceptService,
        AttachServicePhotos $attachPhotos,
        MarkConfirmationIssued $markConfirmation,
        ClientStatusSync $clientStatus,
        IntakeTicketRepository $intakes,
        ConvertIntakeTicket $convertIntake,
    ): RedirectResponse {
        $this->authorize('create', ServiceRecord::class);

        $details = ServiceDetails::fromArray($request->validatedDetails());
        $actor = $this->actor($request);
        $context = AuditContext::fromRequest($request);

        $service = $acceptService->handle($details, $actor, $context);

        $files = $this->uploadedPhotos($request);

        if ($files !== []) {
            $result = $attachPhotos->handle($service, $files, $actor, $context);

            if ($result->hasFailures()) {
                session()->flash('warning', 'Nie udało się dodać części zdjęć: '.implode(', ', $result->failed));
            }
        }

        $printConfirmation = $request->boolean('print_confirmation');

        if ($printConfirmation) {
            $service = $markConfirmation->handle($service, $actor, $context);
        }

        // The customer link is created after the order exists. A failure is only a
        // warning: the acceptance itself is already saved (decision D19/0019).
        if ($clientStatus->enabled() && ! $clientStatus->sync($service, ClientStatusSync::EVENT_ACCEPTED)['ok']) {
            session()->flash('warning', 'Zlecenie '.$service->reference.' zostało zapisane, ale nie udało się utworzyć linku statusu klienta. Link można utworzyć ponownie z karty zlecenia.');
        }

        // The acceptance form was opened from the submissions inbox: link the
        // submission to the order it produced (2026-11-27).
        $rawIntake = $request->input('intake_id');

        if (is_numeric($rawIntake)) {
            $intake = $intakes->find((int) $rawIntake);

            if ($intake instanceof IntakeTicketRecord
                && $intake->status !== IntakeStatus::Converted
                && $intake->status !== IntakeStatus::Rejected) {
                $this->authorize('manage', $intake);
                $convertIntake->handle($intake, $service);
            }
        }

        if ($printConfirmation) {
            return redirect()
                ->route('print.acceptance', $service)
                ->with('status', 'Zlecenie '.$service->reference.' przyjęte.');
        }

        return redirect()
            ->route('services.show', $service)
            ->with('status', 'Zlecenie '.$service->reference.' zostało przyjęte.');
    }

    public function show(
        ServiceRecord $service,
        ServiceEventRepository $events,
        ServicePhotoRepository $photos,
        ServiceWorkflow $workflow,
        UserRepository $users,
        ClientStatusSync $clientStatus,
    ): View {
        $this->authorize('view', $service);

        $actors = [];
        foreach ($users->all() as $user) {
            $actors[$user->id] = $user->name;
        }

        return view('services.show', [
            'service' => $service,
            'photos' => $photos->forService($service->id),
            'events' => $events->forService($service->id, 20),
            'actors' => $actors,
            'canRelease' => $workflow->canRelease($service),
            // Contract with the order card (plan 2026-11-26): the CMS returns the
            // public number, this app does not store it yet, so it stays null.
            'clientStatusUrl' => $service->clientStatusUrl,
            'clientStatusEnabled' => $clientStatus->enabled(),
            'clientStatusNumber' => null,
            'clientStatusSyncedAt' => $service->clientStatusSyncedAt,
        ]);
    }

    public function edit(ServiceRecord $service): View
    {
        $this->authorize('update', $service);

        return view('services.edit', [
            'service' => $service,
            'plannedAtInput' => ServiceRequest::fromRecordToInput($service->plannedAt),
        ]);
    }

    public function update(
        ServiceRequest $request,
        ServiceRecord $service,
        UpdateService $updateService,
        AttachServicePhotos $attachPhotos,
        ClientStatusSync $clientStatus,
    ): RedirectResponse {
        $this->authorize('update', $service);

        $actor = $this->actor($request);
        $context = AuditContext::fromRequest($request);

        $result = $updateService->handle($service, $request->validatedDetails(), $actor, $context);

        $files = $this->uploadedPhotos($request);

        if ($files !== []) {
            $photoResult = $attachPhotos->handle($result['service'], $files, $actor, $context);

            if ($photoResult->hasFailures()) {
                session()->flash('warning', 'Nie udało się dodać części zdjęć: '.implode(', ', $photoResult->failed));
            }
        }

        // The customer page only has to be told when the stage really moved; a
        // plain note/price edit must not cost a request to the CMS.
        if ($result['service']->status !== $service->status
            && $clientStatus->enabled()
            && ! $clientStatus->sync($result['service'], ClientStatusSync::EVENT_STATUS_CHANGED)['ok']) {
            session()->flash('warning', 'Zmiany zostały zapisane, ale nie udało się zaktualizować statusu klienta. Spróbuj ponownie z karty zlecenia.');
        }

        $message = $result['events'] === 0
            ? 'Brak zmian — nic nie zostało zapisane.'
            : 'Zapisano zmiany w zleceniu '.$result['service']->reference.'.';

        return redirect()
            ->route('services.show', $result['service'])
            ->with('status', $message);
    }

    private function actor(Request $request): UserRecord
    {
        $user = $request->user();

        if (! $user instanceof UserRecord) {
            abort(401);
        }

        return $user;
    }

    /**
     * @return list<UploadedFile>
     */
    private function uploadedPhotos(Request $request): array
    {
        $files = $request->file('photos');

        if ($files === null) {
            return [];
        }

        $files = is_array($files) ? $files : [$files];
        $result = [];

        foreach ($files as $file) {
            if ($file instanceof UploadedFile && $file->isValid()) {
                $result[] = $file;
            }
        }

        return $result;
    }
}
