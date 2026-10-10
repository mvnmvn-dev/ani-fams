<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ExportsData;
use App\Http\Controllers\Concerns\RespondsWithFlashOrJson;
use App\Services\ReservationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReservationController extends Controller
{
    use RespondsWithFlashOrJson;
    use ExportsData {
        exportCsv as streamExportCsv;
        exportPdf as streamExportPdf;
    }

    public function index(ReservationService $service)
    {
        $canCreate = $service->canCreateReservations();
        $canApprove = $service->canStaffApprove();
        $canManage = $service->canStaffManage();

        return view('reservations.index', [
            'pageTitle' => 'Facilities Reservation',
            'reservations' => $service->listForCurrentUser(),
            'facilities' => $service->facilities(),
            'manageFacilities' => $canManage ? $service->facilitiesForManage() : [],
            'equipment' => $service->listEquipment(),
            'staffUsers' => $service->staffUsers(),
            'utilization' => $service->utilizationStats(),
            'analytics' => $service->lifecycleAnalytics(),
            'canApprove' => $canApprove,
            'canManage' => $canManage,
            'canCreate' => $canCreate,
        ]);
    }

    public function calendar(Request $request, ReservationService $service)
    {
        $facilityId = (int) $request->query('facility_id', 0);
        $rows = $service->listForCurrentUser();
        if ($facilityId > 0) {
            $rows = array_values(array_filter($rows, static fn ($r) => (int) ($r['facility_id'] ?? 0) === $facilityId));
        }

        return response()->json(['events' => array_values($rows)]);
    }

    public function store(Request $request, ReservationService $service)
    {
        $result = $service->submitRequest($request->all());
        $created = (int) ($result['created'] ?? 1);
        $msg = $created > 1
            ? "Reservation series submitted ({$created} occurrences) for approval."
            : 'Reservation request submitted for approval.';

        return redirect()->route('reservations.index')->with(
            $result['success'] ? 'flash_success' : 'flash_error',
            $result['success'] ? $msg : $result['error']
        );
    }

    public function storeMaintenance(Request $request, ReservationService $service)
    {
        $result = $service->scheduleMaintenanceBlock($request->all());

        return redirect()->route('reservations.index')->with(
            $result['success'] ? 'flash_success' : 'flash_error',
            $result['success'] ? 'Maintenance block scheduled.' : $result['error']
        );
    }

    public function decide(Request $request, string $id, ReservationService $service)
    {
        $decision = (string) $request->input('decision', '');

        try {
            $result = $service->decide((int) $id, $decision);
        } catch (\Throwable $e) {
            report($e);
            $result = ['success' => false, 'error' => 'Unable to update reservation. Please try again.'];
        }

        $success = (bool) ($result['success'] ?? false);
        $message = $success ? 'Reservation '.$decision.'.' : (string) ($result['error'] ?? 'Unable to update reservation.');

        return $this->flashOrJson(
            $request,
            redirect()->route('reservations.index'),
            $success,
            $message,
            [
                'action' => 'reservation.decide',
                'id' => (int) $id,
                'status' => $decision,
                'check_in_code' => $result['check_in_code'] ?? null,
            ]
        );
    }

    public function assignInCharge(Request $request, string $id, ReservationService $service)
    {
        $result = $service->assignInCharge((int) $id, (int) $request->input('in_charge_user_id', 0));

        return $this->flashOrJson(
            $request,
            redirect()->route('reservations.index'),
            (bool) ($result['success'] ?? false),
            $result['success'] ? 'In-charge Staff member assigned.' : ($result['error'] ?? 'Unable to assign Staff.'),
            ['action' => 'reservation.assign_in_charge', 'id' => (int) $id]
        );
    }

    public function exportCsv(ReservationService $service)
    {
        $reservations = $service->listForCurrentUser();
        $headers = ['ID', 'Facility Name', 'Requested By', 'Purpose', 'Start Datetime', 'End Datetime', 'Status', 'Maintenance', 'Approved At'];
        $rows = [];
        foreach ($reservations as $row) {
            $rows[] = [
                $row['id'] ?? '',
                $row['facility_name'] ?? '',
                $row['requester_name'] ?? '',
                $row['purpose'] ?? '',
                $row['start_datetime'] ?? '',
                $row['end_datetime'] ?? '',
                $row['status'] ?? '',
                ! empty($row['is_maintenance']) ? 'yes' : 'no',
                $row['approved_at'] ?? '',
            ];
        }

        return $this->streamExportCsv('reservations', $headers, $rows);
    }

    public function exportPdf(ReservationService $service)
    {
        $reservations = $service->listForCurrentUser();
        $headers = ['ID', 'Facility', 'Requested By', 'Purpose', 'Start', 'End', 'Status'];
        $rows = [];
        foreach ($reservations as $row) {
            $rows[] = [
                $row['id'] ?? '',
                $row['facility_name'] ?? '',
                $row['requester_name'] ?? '',
                $row['purpose'] ?? '',
                $row['start_datetime'] ?? '',
                $row['end_datetime'] ?? '',
                $row['status'] ?? '',
            ];
        }

        return $this->streamExportPdf('reservations', 'Reservations Export', $headers, $rows);
    }

    // ===================== WAITLIST METHODS =====================

    public function joinWaitlist(Request $request, ReservationService $service)
    {
        $result = $service->joinWaitlist($request->all());

        return redirect()->route('reservations.index')->with(
            $result['success'] ? 'flash_success' : 'flash_error',
            $result['success'] ? 'Added to waitlist.' : $result['error']
        );
    }

    public function waitlist(Request $request, ReservationService $service)
    {
        $facilityId = (int) $request->query('facility_id', 0);
        $waitlistEntries = $facilityId > 0 
            ? $service->getWaitlistForFacility($facilityId)
            : $service->getWaitlistForCurrentUser();

        return response()->json(['waitlist' => $waitlistEntries]);
    }

    public function offerWaitlistSlot(Request $request, string $waitlistId, ReservationService $service)
    {
        $reservationId = (int) $request->input('reservation_id', 0);
        $result = $service->offerWaitlistSlot((int) $waitlistId, $reservationId);

        return $this->flashOrJson(
            $request,
            redirect()->route('reservations.index'),
            $result['success'],
            $result['success'] ? 'Waitlist slot offered.' : $result['error'],
            ['action' => 'waitlist.offer', 'id' => (int) $waitlistId]
        );
    }

    public function respondToWaitlistOffer(Request $request, string $waitlistId, ReservationService $service)
    {
        $response = (string) $request->input('response', '');
        $result = $service->respondToWaitlistOffer((int) $waitlistId, $response);

        return redirect()->route('reservations.index')->with(
            $result['success'] ? 'flash_success' : 'flash_error',
            $result['success'] ? 'Waitlist offer response recorded.' : $result['error']
        );
    }

    // ===================== COST TRACKING METHODS =====================

    public function updateCostInfo(Request $request, string $id, ReservationService $service)
    {
        $result = $service->updateCostInfo((int) $id, $request->all());

        return redirect()->route('reservations.index')->with(
            $result['success'] ? 'flash_success' : 'flash_error',
            $result['success'] ? 'Cost information updated.' : $result['error']
        );
    }

    public function costSummary(Request $request, ReservationService $service)
    {
        $startDate = $request->query('start_date', date('Y-m-01'));
        $endDate = $request->query('end_date', date('Y-m-t'));
        $summary = $service->getCostSummary($startDate, $endDate);

        return response()->json(['summary' => $summary]);
    }

    // ===================== NO-SHOW TRACKING METHODS =====================

    public function markNoShow(Request $request, string $id, ReservationService $service)
    {
        $result = $service->markNoShow((int) $id);

        return $this->flashOrJson(
            $request,
            redirect()->route('reservations.index'),
            $result['success'],
            $result['success'] ? 'Reservation marked as no-show.' : $result['error'],
            ['action' => 'reservation.no_show', 'id' => (int) $id]
        );
    }

    public function noShowStats(Request $request, ReservationService $service)
    {
        $userId = (int) $request->query('user_id', Auth::id());
        $stats = $service->getNoShowStats($userId);

        return response()->json(['stats' => $stats]);
    }

    // ===================== CANCELLATION METHODS =====================

    public function cancel(Request $request, string $id, ReservationService $service)
    {
        $reason = trim($request->input('reason', ''));
        if ($reason === '') {
            return redirect()->route('reservations.index')->with('flash_error', 'Cancellation reason is required.');
        }

        $result = $service->cancelReservation((int) $id, $reason);

        return redirect()->route('reservations.index')->with(
            $result['success'] ? 'flash_success' : 'flash_error',
            $result['success'] ? 'Reservation cancelled.' : $result['error']
        );
    }

    // ===================== FEEDBACK METHODS =====================

    public function submitFeedback(Request $request, string $id, ReservationService $service)
    {
        $rating = (int) $request->input('rating', 0);
        $comment = trim($request->input('comment', ''));

        $result = $service->submitFeedback((int) $id, $rating, $comment ?: null);

        return redirect()->route('reservations.index')->with(
            $result['success'] ? 'flash_success' : 'flash_error',
            $result['success'] ? 'Feedback submitted.' : $result['error']
        );
    }

    public function feedbackSummary(Request $request, ReservationService $service)
    {
        $facilityId = (int) $request->query('facility_id', 0);
        $summary = $service->getFeedbackSummary($facilityId);

        return response()->json(['summary' => $summary]);
    }

    // ===================== ICAL EXPORT METHOD =====================

    public function exportIcal(string $id, ReservationService $service)
    {
        $icalContent = $service->generateICal((int) $id);
        if ($icalContent === '') {
            return redirect()->route('reservations.index')->with('flash_error', 'Reservation not found.');
        }

        return response($icalContent)
            ->header('Content-Type', 'text/calendar')
            ->header('Content-Disposition', 'attachment; filename="reservation_'.$id.'.ics"');
    }

    // ===================== DEFENSE READY =====================

    public function checkIn(Request $request, ReservationService $service)
    {
        $code = (string) $request->input('code', $request->input('check_in_code', ''));

        try {
            $result = $service->checkInByCode($code);
        } catch (\Throwable) {
            $result = ['success' => false, 'error' => 'Check-in failed unexpectedly. Use an RSV-… reservation code, not a visitor badge.'];
        }

        return $this->flashOrJson(
            $request,
            redirect()->route('reservations.index'),
            (bool) ($result['success'] ?? false),
            $result['success'] ? 'Reservation checked in.' : ($result['error'] ?? 'Check-in failed.'),
            array_merge(['action' => 'reservation.checkin'], $result)
        );
    }

    public function lifecycle(Request $request, string $id, ReservationService $service)
    {
        $status = (string) $request->input('status', '');
        $remarks = $request->input('remarks');
        $result = $service->advanceStatus((int) $id, $status, $remarks);

        return $this->flashOrJson(
            $request,
            redirect()->route('reservations.index'),
            (bool) ($result['success'] ?? false),
            $result['success'] ? 'Reservation updated to '.$status.'.' : ($result['error'] ?? 'Update failed.'),
            array_merge(['action' => 'reservation.lifecycle', 'id' => (int) $id], $result)
        );
    }

    public function history(string $id, ReservationService $service)
    {
        return response()->json(['logs' => $service->approvalHistory((int) $id)]);
    }

    public function analytics(ReservationService $service)
    {
        return response()->json($service->lifecycleAnalytics());
    }

    public function alternatives(Request $request, ReservationService $service)
    {
        $facilityId = (int) $request->query('facility_id', 0);
        $start = (string) $request->query('start_datetime', '');
        $end = (string) $request->query('end_datetime', '');
        $attendees = (int) $request->query('attendee_count', 0);

        if (! $facilityId || $start === '' || $end === '') {
            return response()->json(['alternatives' => [], 'error' => 'facility_id, start_datetime, and end_datetime are required.'], 422);
        }

        return response()->json([
            'alternatives' => $service->suggestAlternatives($facilityId, $start, $end, $attendees > 0 ? $attendees : null),
        ]);
    }

    public function qrBadge(string $id, ReservationService $service)
    {
        $rows = $service->listForCurrentUser();
        $reservation = null;
        foreach ($rows as $row) {
            if ((int) ($row['id'] ?? 0) === (int) $id) {
                $reservation = $row;
                break;
            }
        }
        if (! $reservation) {
            $reservation = app(\App\Repositories\ReservationRepository::class)->find((int) $id);
        }
        if (! $reservation || empty($reservation['check_in_code'])) {
            return redirect()->route('reservations.index')->with('flash_error', 'Check-in QR is only available after approval.');
        }

        return view('reservations.qr', [
            'pageTitle' => 'Reservation Check-In QR',
            'reservation' => $reservation,
        ]);
    }
}
