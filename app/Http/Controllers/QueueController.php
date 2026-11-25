<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\ServiceRepository;
use App\Domain\Data\Page;
use App\Domain\Data\ServiceQuery;
use App\Domain\Data\ServiceRecord;
use App\Domain\Enums\ServiceStatus;
use App\Domain\FieldLabels;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * The queue (F-21, F-22, F-32): every active service, plus the last ten released
 * ones at the bottom of the first page. Released orders can never jump above an
 * active repair — that was defect D-46, where an express service that was
 * already handed over sat at the top with a dead "Next" button.
 *
 * The list is also the one and only search result screen: the header field
 * filters it live through `results()`, which renders the same cards without the
 * page shell.
 */
final class QueueController extends Controller
{
    private const PER_PAGE = 50;

    public function __invoke(Request $request, ServiceRepository $services): View
    {
        return view('services.queue', $this->data($request, $services));
    }

    /** The list alone, for the live search in the header (F-27). */
    public function results(Request $request, ServiceRepository $services): Response
    {
        // The marker lets the script tell this fragment from a login or error
        // page: an expired session must not paste the login form into the queue.
        return response()
            ->view('services._queue-results', $this->data($request, $services))
            ->header('X-Queue-Results', '1');
    }

    /**
     * @return array<string, mixed>
     */
    private function data(Request $request, ServiceRepository $services): array
    {
        $status = $this->statusFilter($request);
        $rawTerm = $request->query('q');
        $term = is_string($rawTerm) ? trim($rawTerm) : '';
        $term = mb_strlen($term) >= 3 ? $term : '';
        $page = max(1, (int) $request->query('page', '1'));

        // Without a term the queue is the working list: open cases only. A
        // search must also find handed-over laptops, so it lifts the status
        // restriction — unless the employee narrowed the stage themselves.
        $statuses = $status instanceof ServiceStatus
            ? [$status]
            : ($term === '' ? ServiceStatus::activeCases() : []);

        $result = $services->paginate(new ServiceQuery(
            statuses: $statuses,
            term: $term === '' ? null : $term,
            sort: ServiceQuery::SORT_QUEUE,
            perPage: self::PER_PAGE,
            page: $page,
        ));

        return [
            'page' => $result,
            'groups' => $this->groups($result),
            'released' => $term === '' && ! $status instanceof ServiceStatus && $page === 1
                ? $services->recentlyReleased(10)
                : [],
            'counts' => $services->countsByStatus(),
            'activeFilter' => $status,
            'term' => $term,
            'query' => array_filter(
                ['q' => $term, 'status' => $status?->value],
                static fn (?string $value): bool => $value !== null && $value !== '',
            ),
            'timezone' => FieldLabels::displayTimezone(),
        ];
    }

    /**
     * One section per stage, in the order the query sorted them (in progress
     * first, final states last). The screen shows which laptop is where at a
     * glance instead of making the employee read every badge.
     *
     * @param  Page<ServiceRecord>  $page
     * @return list<array{status: ServiceStatus, items: list<ServiceRecord>}>
     */
    private function groups(Page $page): array
    {
        /** @var array<string, array{status: ServiceStatus, items: list<ServiceRecord>}> $groups */
        $groups = [];

        foreach ($page->items as $service) {
            $key = $service->status->value;

            if (! isset($groups[$key])) {
                $groups[$key] = ['status' => $service->status, 'items' => []];
            }

            $groups[$key]['items'][] = $service;
        }

        return array_values($groups);
    }

    /**
     * One stage at a time (F-21). The old strip was a set of checkboxes that
     * started all-checked, so a click removed a stage instead of picking one;
     * the radio now means "show only this stage", and no choice means all.
     */
    private function statusFilter(Request $request): ?ServiceStatus
    {
        $raw = $request->query('status');
        $value = is_array($raw) ? ($raw[0] ?? null) : $raw;

        if (! is_string($value)) {
            return null;
        }

        $status = ServiceStatus::tryFrom($value);

        return $status instanceof ServiceStatus && $status->isActive() ? $status : null;
    }
}
