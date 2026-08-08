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
        $canApprove = Auth::user()->canPermission('reservation.approve');
        $canManage = Auth::user()->canPermission('reservation.manage') || $canApprove;

        return view('reservations.index', [
            'pageTitle' => 'Facilities Reservation',
            'reservations' => $service->listForCurrentUser(),
            'facilities' => $service->facilities(),
            'manageFacilities' => $canManage ? $service->facilitiesForManage() : [],
            'equipment' => $service->listEquipment(),
            'utilization' => $service->utilizationStats(),
            'canApprove' => $canApprove,
            'canManage' => $canManage,
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
        $result = $service->decide((int) $id, $decision);
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
            ]
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
}
