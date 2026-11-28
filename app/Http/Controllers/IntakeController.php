<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\ContactIntakeTicket;
use App\Actions\RejectIntakeTicket;
use App\Contracts\IntakeTicketRepository;
use App\Contracts\ServiceRepository;
use App\Domain\Data\IntakeQuery;
use App\Domain\Data\IntakeTicketRecord;
use App\Domain\Data\ServiceRecord;
use App\Domain\Data\UserRecord;
use App\Domain\Enums\IntakeStatus;
use App\Domain\FieldLabels;
use App\Http\Requests\RejectIntakeRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The "Zgłoszenia" inbox under "Wydane" (owner request 2026-11-27).
 *
 * A submission is never deleted: it is marked as contacted, rejected with a
 * reason, or converted into a repair order (which opens the normal acceptance
 * form prefilled with the customer's data and links both records afterwards).
 */
final class IntakeController extends Controller
{
    private const PER_PAGE = 30;

    public function index(Request $request, IntakeTicketRepository $tickets): View
    {
        $this->authorize('viewAny', IntakeTicketRecord::class);

        $status = $this->statusFilter($request);
        $rawTerm = $request->query('q');
        $term = is_string($rawTerm) ? trim($rawTerm) : '';
        $term = mb_strlen($term) >= 3 ? $term : '';
        $page = max(1, (int) $request->query('page', '1'));

        $result = $tickets->paginate(new IntakeQuery(
            statuses: $status instanceof IntakeStatus ? [$status] : [],
            term: $term === '' ? null : $term,
            perPage: self::PER_PAGE,
            page: $page,
        ));

        return view('intake.index', [
            'page' => $result,
            'counts' => $tickets->countsByStatus(),
            'activeFilter' => $status,
            'term' => $term,
            'timezone' => FieldLabels::displayTimezone(),
        ]);
    }

    public function show(IntakeTicketRecord $intake): View
    {
        $this->authorize('view', $intake);

        return view('intake.show', ['ticket' => $intake]);
    }

    public function contact(IntakeTicketRecord $intake, ContactIntakeTicket $contact): RedirectResponse
    {
        $this->authorize('manage', $intake);

        $contact->handle($intake);

        return redirect()
            ->route('intake.show', $intake)
            ->with('status', 'Zgłoszenie '.$intake->number.' oznaczone jako „W kontakcie”.');
    }

    public function reject(
        RejectIntakeRequest $request,
        IntakeTicketRecord $intake,
        RejectIntakeTicket $reject,
    ): RedirectResponse {
        $this->authorize('manage', $intake);

        $user = $request->user();

        if (! $user instanceof UserRecord) {
            abort(401);
        }

        $reject->handle($intake, $request->reason(), $user);

        return redirect()
            ->route('intake.show', $intake)
            ->with('status', 'Zgłoszenie '.$intake->number.' zostało odrzucone.');
    }

    /**
     * Opens the normal acceptance form with the customer data filled in. The
     * inbox row is only marked as converted after the order actually exists (see
     * ServiceController::store), so an abandoned form leaves the submission open.
     */
    public function convert(IntakeTicketRecord $intake, ServiceRepository $services): RedirectResponse
    {
        $this->authorize('manage', $intake);
        $this->authorize('create', ServiceRecord::class);

        if ($intake->status === IntakeStatus::Converted) {
            $service = $intake->convertedServiceId !== null ? $services->find($intake->convertedServiceId) : null;

            return $service instanceof ServiceRecord
                ? redirect()->route('services.show', $service)
                : redirect()->route('intake.show', $intake);
        }

        if ($intake->status === IntakeStatus::Rejected) {
            return redirect()
                ->route('intake.show', $intake)
                ->withErrors(['status' => 'Odrzuconego zgłoszenia nie można przyjąć do serwisu.']);
        }

        return redirect()->route('services.create', ['intake' => $intake->id]);
    }

    private function statusFilter(Request $request): ?IntakeStatus
    {
        $raw = $request->query('status');
        $value = is_array($raw) ? ($raw[0] ?? null) : $raw;

        if (! is_string($value)) {
            return null;
        }

        $status = IntakeStatus::tryFrom($value);

        return $status instanceof IntakeStatus ? $status : null;
    }
}
