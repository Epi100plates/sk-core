<?php

declare(strict_types=1);

namespace App\Actions;

use App\Contracts\ServiceEventRepository;
use App\Contracts\ServicePhotoRepository;
use App\Contracts\TransactionManager;
use App\Domain\Data\ServiceRecord;
use App\Domain\Data\UserRecord;
use App\Domain\Enums\ServiceEventType;
use App\Support\Audit\AuditContext;
use App\Support\Photos\PhotoStore;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use Throwable;

/**
 * Attaching photos while accepting, editing or releasing a service — one code
 * path for all three (defect D-16: the old release form had an upload input that
 * the backend never read).
 */
final class AttachServicePhotos
{
    use RecordsServiceEvents;

    public function __construct(
        private readonly ServicePhotoRepository $photos,
        private readonly ServiceEventRepository $events,
        private readonly TransactionManager $transactions,
        private readonly PhotoStore $store,
    ) {}

    /**
     * @param  list<UploadedFile>  $files
     */
    public function handle(
        ServiceRecord $service,
        array $files,
        UserRecord $actor,
        AuditContext $context,
    ): PhotoAttachResult {
        if (count($files) > PhotoStore::MAX_FILES) {
            throw new InvalidArgumentException('Można dodać maksymalnie '.PhotoStore::MAX_FILES.' zdjęć naraz.');
        }

        return $this->transactions->run(function () use ($service, $files, $actor, $context): PhotoAttachResult {
            $stored = [];
            $failed = [];

            foreach ($files as $file) {
                try {
                    $record = $this->store->store($service->id, $file, $actor->id);
                    $saved = $this->photos->save($record);
                } catch (Throwable $exception) {
                    $failed[] = $file->getClientOriginalName().' ('.$exception->getMessage().')';

                    continue;
                }

                $stored[] = $saved;

                $this->recordEvent(
                    $this->events,
                    $service->id,
                    ServiceEventType::PhotoAdded,
                    $actor,
                    $context,
                    [],
                    null,
                    null,
                    'Dodano zdjęcie '.$saved->path,
                );
            }

            return new PhotoAttachResult($stored, $failed);
        });
    }
}
