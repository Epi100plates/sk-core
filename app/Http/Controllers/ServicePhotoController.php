<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\AttachServicePhotos;
use App\Actions\DeleteServicePhoto;
use App\Actions\PhotoAttachResult;
use App\Contracts\ServicePhotoRepository;
use App\Domain\Data\ServicePhotoRecord;
use App\Domain\Data\ServiceRecord;
use App\Domain\Data\UserRecord;
use App\Http\Requests\PhotoUploadRequest;
use App\Support\Audit\AuditContext;
use App\Support\Photos\PhotoStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Serving and deleting photos (F-18, F-37, defects D-52/D-05).
 *
 * Files live outside the web root on the private disk and are streamed through
 * this controller, so a photo can never be fetched without a session, whatever
 * the URL. `Cache-Control: private` keeps customer photos out of shared caches.
 */
final class ServicePhotoController extends Controller
{
    public function show(ServicePhotoRecord $photo, PhotoStore $store): Response
    {
        $this->authorize('view', $photo);

        return $this->binaryResponse($store, $photo, thumb: false);
    }

    public function thumb(ServicePhotoRecord $photo, PhotoStore $store): Response
    {
        $this->authorize('view', $photo);

        return $this->binaryResponse($store, $photo, thumb: true);
    }

    public function store(
        PhotoUploadRequest $request,
        ServiceRecord $service,
        AttachServicePhotos $attach,
    ): RedirectResponse {
        $this->authorize('managePhotos', $service);

        $user = $request->user();

        if (! $user instanceof UserRecord) {
            abort(401);
        }

        $uploaded = $request->uploadedPhotos();

        if ($uploaded === []) {
            return back()->withErrors(['photos' => 'Wybierz co najmniej jedno zdjęcie.']);
        }

        /** @var PhotoAttachResult $result */
        $result = $attach->handle($service, $uploaded, $user, AuditContext::fromRequest($request));

        $redirect = back()->with('status', 'Dodano zdjęcia: '.$result->addedCount().'.');

        if ($result->hasFailures()) {
            $redirect = $redirect->with('warning', 'Odrzucone pliki: '.implode(', ', $result->failed));
        }

        return $redirect;
    }

    public function destroy(
        Request $request,
        ServiceRecord $service,
        ServicePhotoRecord $photo,
        DeleteServicePhoto $delete,
        ServicePhotoRepository $photos,
    ): RedirectResponse {
        if ($photo->serviceId !== $service->id) {
            abort(404);
        }

        $this->authorize('delete', $photo);

        $user = $request->user();

        if (! $user instanceof UserRecord) {
            abort(401);
        }

        $delete->handle($photo, $user, AuditContext::fromRequest($request));

        return back()->with('status', 'Zdjęcie zostało usunięte.');
    }

    private function binaryResponse(PhotoStore $store, ServicePhotoRecord $photo, bool $thumb): Response
    {
        $contents = $store->read($photo, $thumb);

        if ($contents === null) {
            abort(404);
        }

        return response($contents, 200, [
            'Content-Type' => $photo->mime,
            'Content-Length' => (string) strlen($contents),
            'Cache-Control' => 'private, max-age=0, must-revalidate',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
