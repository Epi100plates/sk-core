<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Domain\Data\Page;
use App\Domain\Data\ServiceEventRecord;

interface ServiceEventRepository
{
    public function append(ServiceEventRecord $event): ServiceEventRecord;

    /**
     * Newest first.
     *
     * @return Page<ServiceEventRecord>
     */
    public function forService(int $serviceId, int $perPage = 20, int $page = 1): Page;

    /**
     * @return list<ServiceEventRecord>
     */
    public function all(): array;

    public function count(): int;
}
