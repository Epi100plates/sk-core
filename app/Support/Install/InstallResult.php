<?php

declare(strict_types=1);

namespace App\Support\Install;

final class InstallResult
{
    public function __construct(
        public readonly string $driver,
        public readonly bool $schemaCreated,
        public readonly ?string $adminLogin,
        public readonly ?string $adminPassword,
        public readonly bool $adminAlreadyExisted,
    ) {}

    public function createdAdmin(): bool
    {
        return $this->adminLogin !== null && $this->adminPassword !== null;
    }
}
