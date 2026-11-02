<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Domain\Data\ServicePhotoRecord;

interface ServicePhotoRepository
{
    public function find(int $id): ?ServicePhotoRecord;

    public function save(ServicePhotoRecord $photo): ServicePhotoRecord;

    /**
     * @return list<ServicePhotoRecord>
     */
    public function forService(int $serviceId): array;

    public function delete(int $id): void;

    /**
     * @return list<ServicePhotoRecord>
     */
    public function all(): array;

    public function count(): int;
}
