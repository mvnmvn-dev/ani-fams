<?php

namespace App\Services;

use App\Repositories\ReservationRepository;

use App\Support\AuditLogger;
use Illuminate\Support\Facades\Auth;
class ReservationService
{
    public function __construct(private ReservationRepository $repo) {}

    /** Employees only ever see their own requests; staff/admin see everyone's. */
    public function listForCurrentUser(): array
    {
        if (Auth::user()?->canPermission('reservation.approve')) {
            return $this->repo->all();
        }
        return $this->repo->allForUser(Auth::id());
    }

    public function facilities(): array
    {
        return $this->repo->allFacilities();
    }

    public function pendingCountForCurrentUser(): int
    {
        return Auth::user()?->canPermission('reservation.approve')
            ? $this->repo->countPending()
            : $this->repo->countPendingForUser(Auth::id());
    }

    /**
     * @return array{success: bool, error?: string, id?: int}
     */
    public function submitRequest(array $input): array
    {
        $facilityId = (int) ($input['facility_id'] ?? 0);
        $purpose    = trim($input['purpose'] ?? '');
        $start      = $input['start_datetime'] ?? '';
        $end        = $input['end_datetime'] ?? '';

        if (!$facilityId || $purpose === '' || !$start || !$end) {
            return ['success' => false, 'error' => 'Please fill in all fields.'];
        }

        if (strtotime($end) <= strtotime($start)) {
            return ['success' => false, 'error' => 'End time must be after the start time.'];
        }

        if (strtotime($start) < strtotime('-1 minute')) {
            return ['success' => false, 'error' => 'You cannot reserve a time slot in the past.'];
        }

        if ($this->repo->hasConflict($facilityId, $start, $end)) {
            return ['success' => false, 'error' => 'That facility is already booked for the selected time range.'];
        }

        $id = $this->repo->create([
            'facility_id'    => $facilityId,
            'requested_by'   => Auth::id(),
            'purpose'        => $purpose,
            'start_datetime' => $start,
            'end_datetime'   => $end,
        ]);

        AuditLogger::log('reservations', 'create', 'reservation', $id, null, [
            'facility_id'    => $facilityId,
            'purpose'        => $purpose,
            'start_datetime' => $start,
            'end_datetime'   => $end,
        ]);

        return ['success' => true, 'id' => $id];
    }

    /**
     * @return array{success: bool, error?: string}
     */
    public function decide(int $reservationId, string $decision): array
    {
        if (!Auth::user()?->canPermission('reservation.approve')) {
            return ['success' => false, 'error' => 'You do not have permission to approve reservations.'];
        }

        if (!in_array($decision, ['approved', 'rejected'], true)) {
            return ['success' => false, 'error' => 'Invalid decision.'];
        }

        $reservation = $this->repo->find($reservationId);
        if (!$reservation) {
            return ['success' => false, 'error' => 'Reservation not found.'];
        }

        if ($reservation['status'] !== 'pending') {
            return ['success' => false, 'error' => 'This reservation has already been decided.'];
        }

        $this->repo->updateStatus($reservationId, $decision, Auth::id());

        AuditLogger::log(
            'reservations',
            $decision === 'approved' ? 'approve' : 'reject',
            'reservation',
            $reservationId,
            ['status' => 'pending'],
            ['status' => $decision]
        );

        return ['success' => true];
    }
}
