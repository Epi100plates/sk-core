<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

use RuntimeException;

/**
 * Two people edited the same service at the same time. The UI turns this into
 * a 409 with "odśwież i spróbuj ponownie" (plan section 5.7, optimistic lock).
 */
final class VersionConflictException extends RuntimeException
{
    public function __construct(public readonly int $serviceId)
    {
        parent::__construct('Ktoś inny zmienił to zlecenie w międzyczasie. Odśwież stronę i spróbuj ponownie.');
    }
}
