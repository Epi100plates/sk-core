<?php

declare(strict_types=1);

namespace App\Support\Logging;

use Illuminate\Log\Logger as IlluminateLogger;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

/**
 * Attaches {@see RedactSensitiveData} to a logging channel through the `tap`
 * option in `config/logging.php`, so both the `single` and `daily` channels mask
 * customer data before anything is written to disk.
 *
 * Laravel hands the tap its own `Illuminate\Log\Logger` wrapper, which keeps the
 * Monolog logger inside — the processor has to go on that inner instance.
 */
final class RedactLogRecords
{
    public function __invoke(LoggerInterface $logger): void
    {
        if ($logger instanceof IlluminateLogger) {
            $logger = $logger->getLogger();
        }

        if ($logger instanceof Logger) {
            $logger->pushProcessor(new RedactSensitiveData);
        }
    }
}
