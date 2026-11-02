<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\ServiceRepository;
use App\Domain\Data\ServiceQuery;
use App\Domain\Enums\ServiceStatus;
use App\Domain\FieldLabels;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Archive of released services (F-26) with a date range, a search term and
 * grouping by day. Only signed-in employees can see it (defect D-02: the old
 * `wydane.php` was public).
 */
final class ReleasedController extends Controller
{
    private const PER_PAGE = 25;

    public function __invoke(Request $request, ServiceRepository $services): View
    {
        $timezone = FieldLabels::displayTimezone();
        $term = trim((string) $request->query('q', ''));
        $from = $this->parseDate($request->query('from'), $timezone, endOfDay: false);
        $to = $this->parseDate($request->query('to'), $timezone, endOfDay: true);
        $page = max(1, (int) $request->query('page', '1'));

        $query = new ServiceQuery(
            statuses: [ServiceStatus::Released],
            term: mb_strlen($term) >= 3 ? $term : null,
            releasedFrom: $from,
            releasedTo: $to,
            sort: ServiceQuery::SORT_RELEASED,
            perPage: self::PER_PAGE,
            page: $page,
        );

        $result = $services->paginate($query);

        $grouped = [];

        foreach ($result->items as $service) {
            $releasedAt = $service->releasedAt;
            $key = $releasedAt instanceof CarbonImmutable
                ? $releasedAt->timezone($timezone)->format('Y-m-d')
                : 'brak-daty';

            $grouped[$key][] = $service;
        }

        return view('services.released', [
            'page' => $result,
            'grouped' => $grouped,
            'term' => $term,
            'from' => is_string($request->query('from')) ? (string) $request->query('from') : '',
            'to' => is_string($request->query('to')) ? (string) $request->query('to') : '',
            'timezone' => $timezone,
        ]);
    }

    private function parseDate(mixed $value, string $timezone, bool $endOfDay): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            $date = CarbonImmutable::parse($value, $timezone);

            return $endOfDay ? $date->endOfDay()->utc() : $date->startOfDay()->utc();
        } catch (\Throwable) {
            return null;
        }
    }
}
