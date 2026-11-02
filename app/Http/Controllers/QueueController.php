<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\ServiceRepository;
use App\Domain\Data\Page;
use App\Domain\Data\ServiceQuery;
use App\Domain\Enums\ServiceStatus;
use App\Domain\FieldLabels;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The queue (F-21, F-22, F-32): every active service, plus the last ten released
 * ones at the bottom of the first page. Released orders can never jump above an
 * active repair — that was defect D-46, where an express service that was
 * already handed over sat at the top with a dead "Next" button.
 */
final class QueueController extends Controller
{
    private const PER_PAGE = 50;

    public function __invoke(Request $request, ServiceRepository $services): View
    {
        $statuses = $this->statusFilter($request);

        if ($statuses === []) {
            $statuses = ServiceStatus::activeCases();
        }

        $page = max(1, (int) $request->query('page', '1'));

        $result = $services->paginate(ServiceQuery::queue($statuses, self::PER_PAGE, $page));
        $released = $page === 1 ? $services->recentlyReleased(10) : [];

        return view('services.queue', [
            'page' => $result,
            'released' => $released,
            'counts' => $services->countsByStatus(),
            'activeFilter' => array_map(static fn (ServiceStatus $status): string => $status->value, $statuses),
            'timezone' => FieldLabels::displayTimezone(),
        ]);
    }

    /**
     * @return list<ServiceStatus>
     */
    private function statusFilter(Request $request): array
    {
        $raw = $request->query('status');
        $raw = is_array($raw) ? $raw : ($raw === null ? [] : [$raw]);

        $statuses = [];

        foreach ($raw as $value) {
            if (! is_string($value)) {
                continue;
            }

            $status = ServiceStatus::tryFrom($value);

            if ($status instanceof ServiceStatus && $status->isActive()) {
                $statuses[] = $status;
            }
        }

        return $statuses;
    }
}
