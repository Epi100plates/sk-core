<?php

declare(strict_types=1);

namespace App\View\Composers;

use App\Contracts\ServiceRepository;
use App\Domain\Data\ServiceQuery;
use App\Domain\Enums\ServiceStatus;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * The sidebar carries a glanceable copy of the queue: the employee can jump to
 * another laptop from the intake form or an order card without going back to
 * the queue first. The list owns the free height of the rail and scrolls there,
 * so the count is a working set rather than the old five-row teaser; the full
 * list stays one click away.
 */
final class SidebarQueueComposer
{
    private const LIMIT = 30;

    public function __construct(private readonly ServiceRepository $services) {}

    public function compose(View $view): void
    {
        if (! Auth::check()) {
            return;
        }

        $page = $this->services->paginate(
            ServiceQuery::queue(ServiceStatus::activeCases(), self::LIMIT),
        );

        $view->with([
            'sidebarQueue' => $page->items,
            'sidebarQueueTotal' => $page->total,
        ]);
    }
}
