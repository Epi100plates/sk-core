<?php

declare(strict_types=1);

namespace App\Support\Audit;

use Illuminate\Http\Request;

/**
 * Request context stored with every audit entry (T7.3): who changed what, from
 * which IP and browser. Kept as a value object so actions can be invoked from a
 * console command (no request) without faking HTTP state.
 */
final class AuditContext
{
    public function __construct(
        public readonly ?string $ip,
        public readonly ?string $userAgent,
    ) {}

    public static function fromRequest(?Request $request): self
    {
        if (! $request instanceof Request) {
            return new self(null, null);
        }

        $userAgent = $request->userAgent();

        return new self(
            $request->ip(),
            $userAgent === null ? null : mb_substr($userAgent, 0, 255),
        );
    }

    public static function console(): self
    {
        return new self(null, 'console');
    }
}
