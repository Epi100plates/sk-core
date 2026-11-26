<?php

declare(strict_types=1);

namespace App\Domain;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Working-day arithmetic for promised deadlines.
 *
 * A shop promise of "1 dzień" made on Friday is due on Monday, not Saturday:
 * every counted day must be Monday–Friday. Public holidays are intentionally not
 * excluded here; the form names the rule "pon.–pt." so the promise stays honest.
 */
final class BusinessDays
{
    /**
     * Add whole working days to the start date. The time of day is not carried
     * over — callers set the shop's pickup hour (15:00) explicitly.
     */
    public static function add(CarbonInterface $start, int $days): CarbonImmutable
    {
        $date = CarbonImmutable::instance($start)->startOfDay();
        $remaining = max(0, $days);

        while ($remaining > 0) {
            $date = $date->addDay();

            if ($date->isWeekday()) {
                $remaining--;
            }
        }

        return $date;
    }
}
