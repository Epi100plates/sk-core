<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\AcceptService;
use App\Actions\AttachServicePhotos;
use App\Actions\MarkConfirmationIssued;
use App\Actions\ServiceDetails;
use App\Actions\UpdateService;
use App\Contracts\ServiceEventRepository;
use App\Contracts\ServicePhotoRepository;
use App\Contracts\UserRepository;
use App\Domain\Data\ServiceRecord;
use App\Domain\Data\UserRecord;
use App\Domain\ServiceWorkflow;
use App\Http\Requests\ServiceRequest;
use App\Support\Audit\AuditContext;
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
    public function create(): View
    {
        $this->authorize('create', ServiceRecord::class);

        return view('services.create', [
            'service' => null,
            'defaultPlannedAt' => ServiceRequest::defaultPlannedAt(),
        ]);
    }

    public function store(
        ServiceRequest $request,
        AcceptService $acceptService,
        AttachServicePhotos $attachPhotos,
        MarkConfirmationIssued $markConfirmation,
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

        if ($request->boolean('print_confirmation')) {
            $markConfirmation->handle($service, $actor, $context);

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
