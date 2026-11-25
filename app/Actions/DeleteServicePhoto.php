<?php

declare(strict_types=1);

namespace App\Actions;

use App\Contracts\ServiceEventRepository;
use App\Contracts\ServicePhotoRepository;
use App\Contracts\TransactionManager;
use App\Domain\Data\ServicePhotoRecord;
use App\Domain\Data\UserRecord;
use App\Domain\Enums\ServiceEventType;
use App\Support\Audit\AuditContext;
use App\Support\Photos\PhotoStore;

/**
 * Deleting a photo (F-37). Removes the record, both files and — unlike the old
 * application (defects D-14/D-53) — writes a `photo_deleted` audit entry.
 */
final class DeleteServicePhoto
{
    use RecordsServiceEvents;

    public function __construct(
        private readonly ServicePhotoRepository $photos,
        private readonly ServiceEventRepository $events,
        private readonly TransactionManager $transactions,
        private readonly PhotoStore $store,
    ) {}

    public function handle(ServicePhotoRecord $photo, UserRecord $actor, AuditContext $context): void
    {
        $this->transactions->run(function () use ($photo, $actor, $context): void {
            $this->store->delete($photo);
            $this->photos->delete($photo->id);

            $this->recordEvent(
                $this->events,
                $photo->serviceId,
                ServiceEventType::PhotoDeleted,
                $actor,
                $context,
            );
        });
    }
}
