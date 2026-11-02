<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\ServiceRepository;
use App\Domain\Data\ServiceQuery;
use App\Domain\FieldLabels;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * One search screen for phone, seal number, model, serial number, issue and
 * reference (F-27…F-29, defects D-21/D-03). Searching is done on the server and
 * covers released orders as well.
 */
final class SearchController extends Controller
{
    private const PER_PAGE = 25;

    public function __invoke(Request $request, ServiceRepository $services): View
    {
        $term = trim((string) ($request->query('q') ?? $request->query('phone') ?? $request->query('seal') ?? ''));
        $page = max(1, (int) $request->query('page', '1'));

        $result = mb_strlen($term) >= 3
            ? $services->paginate(ServiceQuery::search($term, self::PER_PAGE, $page))
            : null;

        return view('services.search', [
            'term' => $term,
            'page' => $result,
            'timezone' => FieldLabels::displayTimezone(),
        ]);
    }
}
