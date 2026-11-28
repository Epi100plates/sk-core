<?php

declare(strict_types=1);

namespace App\View\Composers;

use App\Contracts\IntakeTicketRepository;
use App\Contracts\ServiceRepository;
use App\Contracts\UserRepository;
use App\Domain\Data\ServiceQuery;
use App\Domain\Data\UserRecord;
use App\Domain\Enums\Ability;
use App\Domain\Enums\IntakeStatus;
use App\Domain\Enums\Role;
use App\Domain\Enums\ServiceStatus;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * The sidebar carries a glanceable copy of the queue: the employee can jump to
 * another laptop from the intake form or an order card without going back to
 * the queue first. The list owns the free height of the rail and scrolls there,
 * so the count is a working set rather than the old five-row teaser; the full
 * list stays one click away.
 *
 * The same composer feeds the in-app notifications of the release approval flow
 * (owner request 2026-11-27): the owner sees how many laptops wait for the
 * acceptance, the technician how many came back with remarks, and the public
 * site's submissions carry a "new" badge under "Wydane".
 */
final class SidebarQueueComposer
{
    private const LIMIT = 30;

    public function __construct(
        private readonly ServiceRepository $services,
        private readonly IntakeTicketRepository $intakes,
        private readonly UserRepository $users,
    ) {}

    public function compose(View $view): void
    {
        if (! Auth::check()) {
            return;
        }

        $page = $this->services->paginate(
            ServiceQuery::queue(ServiceStatus::activeCases(), self::LIMIT),
        );

        $user = Auth::user();
        $counts = $this->services->countsByStatus();
        $awaitingApproval = $counts[ServiceStatus::AwaitingApproval->value] ?? 0;
        $withRemarks = $counts[ServiceStatus::WithRemarks->value] ?? 0;
        $canApprove = $user instanceof UserRecord && $user->can(Ability::APPROVE_RELEASE);

        $titleMarker = null;

        if ($canApprove && $awaitingApproval > 0) {
            $titleMarker = $awaitingApproval.' do akceptacji';
        } elseif (! $canApprove && $withRemarks > 0) {
            $titleMarker = $withRemarks.' z uwagami';
        }

        $intakeCounts = $this->intakes->countsByStatus();

        // Safety net for an installation upgraded without the one-time
        // `users:owner` step: an account manager sees that nobody can accept a
        // release instead of wondering why the flow is stuck.
        $missingOwner = $user instanceof UserRecord
            && $user->can(Ability::MANAGE_USERS)
            && ! $this->hasOwner();

        $view->with([
            'sidebarQueue' => $page->items,
            'sidebarQueueTotal' => $page->total,
            'sidebarAwaitingApproval' => $awaitingApproval,
            'sidebarWithRemarks' => $withRemarks,
            'sidebarCanApprove' => $canApprove,
            'sidebarIntakeNew' => $intakeCounts[IntakeStatus::New->value] ?? 0,
            'sidebarTitleMarker' => $titleMarker,
            'sidebarMissingOwner' => $missingOwner,
        ]);
    }

    private function hasOwner(): bool
    {
        foreach ($this->users->all() as $account) {
            if ($account->isActive && $account->role === Role::Owner) {
                return true;
            }
        }

        return false;
    }
}
