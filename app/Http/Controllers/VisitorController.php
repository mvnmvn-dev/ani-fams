<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ExportsData;
use App\Http\Controllers\Concerns\RespondsWithFlashOrJson;
use App\Services\VisitorService;
use App\Services\ArnelAiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VisitorController extends Controller
{
    use RespondsWithFlashOrJson;
    use ExportsData {
        exportCsv as streamExportCsv;
        exportPdf as streamExportPdf;
    }

    public function index(VisitorService $service, ArnelAiService $arnel)
    {
        $visits = $service->listVisits();
        return view('visitors.index', [
            'pageTitle' => 'Visitor Management',
            'visits' => $visits,
            'aiInsights' => $arnel->visitorInsights($visits),
            'visitors' => $service->visitors(),
            'employees' => $service->employees(),
            'areas' => $service->areas(),
            'canRegister' => Auth::user()->canPermission('visitor.register'),
            'canCheckIn' => Auth::user()->canPermission('visitor.checkin'),
            'canManage' => Auth::user()->canPermission('visitor.manage'),
        ]);
    }

    public function store(Request $request, VisitorService $service)
    {
        $photo = $request->file('photo');
        $result = $service->preRegister($request->all(), $photo);

        return redirect()->route('visitors.index')->with(
            $result['success'] ? 'flash_success' : 'flash_error',
            $result['success'] ? 'Visitor pre-registered successfully.' : $result['error']
        );
    }

    public function checkIn(Request $request, string $id, VisitorService $service)
    {
        $result = $service->checkIn((int) $id);
        $success = (bool) ($result['success'] ?? false);
        $message = $success ? 'Visitor checked in successfully.' : (string) ($result['error'] ?? 'Unable to check in.');

        return $this->flashOrJson(
            $request,
            redirect()->route('visitors.index'),
            $success,
            $message,
            array_merge(['action' => 'visitor.checkin'], $result)
        );
    }

    public function checkOut(Request $request, string $id, VisitorService $service)
    {
        $result = $service->checkOut((int) $id);
        $success = (bool) ($result['success'] ?? false);
        $message = $success ? 'Visitor checked out successfully.' : (string) ($result['error'] ?? 'Unable to check out.');

        return $this->flashOrJson(
            $request,
            redirect()->route('visitors.index'),
            $success,
            $message,
            array_merge(['action' => 'visitor.checkout'], $result)
        );
    }

    public function toggleWatchlist(Request $request, string $id, VisitorService $service)
    {
        $result = $service->toggleWatchlist((int) $id, $request->boolean('watchlist'));
        $success = (bool) ($result['success'] ?? false);
        $message = $success ? 'Visitor watchlist status updated.' : (string) ($result['error'] ?? 'Unable to update watchlist.');

        return $this->flashOrJson(
            $request,
            redirect()->route('visitors.index'),
            $success,
            $message,
            array_merge(['action' => 'visitor.watchlist'], $result)
        );
    }

    public function badge(string $id, VisitorService $service)
    {
        $data = $service->badgeData((int) $id);
        if (! $data) {
            return redirect()->route('visitors.index')->with('flash_error', 'Visit badge not found.');
        }

        return view('visitors.badge', [
            'pageTitle' => 'Visitor Badge',
            'visit' => $data,
        ]);
    }

    public function exportCsv(VisitorService $service)
    {
        $visits = $service->listVisits();
        $headers = ['ID', 'Visitor Name', 'Company', 'Host Name', 'Purpose', 'Scheduled At', 'Check In Time', 'Check Out Time', 'Status', 'Allowed Areas', 'Watchlisted'];
        $rows = [];
        foreach ($visits as $row) {
            $rows[] = [
                $row['id'] ?? '',
                $row['visitor_name'] ?? '',
                $row['visitor_company'] ?? '',
                $row['host_name'] ?? '',
                $row['purpose'] ?? '',
                $row['scheduled_at'] ?? '',
                $row['check_in_time'] ?? '',
                $row['check_out_time'] ?? '',
                $row['status'] ?? '',
                $row['allowed_areas'] ?? '',
                ! empty($row['is_watchlisted']) ? 'Yes' : 'No',
            ];
        }

        return $this->streamExportCsv('visits', $headers, $rows);
    }

    public function exportPdf(VisitorService $service)
    {
        $visits = $service->listVisits();
        $headers = ['ID', 'Visitor', 'Host', 'Purpose', 'Scheduled', 'Status', 'Watchlisted'];
        $rows = [];
        foreach ($visits as $row) {
            $rows[] = [
                $row['id'] ?? '',
                $row['visitor_name'] ?? '',
                $row['host_name'] ?? '',
                $row['purpose'] ?? '',
                $row['scheduled_at'] ?? '',
                $row['status'] ?? '',
                ! empty($row['is_watchlisted']) ? 'Yes' : 'No',
            ];
        }

        return $this->streamExportPdf('visits', 'Visits Export', $headers, $rows);
    }

    // ===================== WATCHLIST ENHANCEMENTS =====================

    public function updateWatchlistDetails(Request $request, string $id, VisitorService $service)
    {
        $result = $service->updateWatchlistWithDetails(
            (int) $id,
            $request->boolean('is_watchlisted'),
            $request->input('watchlist_reason'),
            $request->input('watchlist_expiry_date')
        );

        return $this->flashOrJson(
            $request,
            redirect()->route('visitors.index'),
            $result['success'],
            $result['success'] ? 'Watchlist details updated.' : $result['error'],
            ['action' => 'visitor.watchlist_details', 'id' => (int) $id]
        );
    }

    // ===================== VEHICLE LOGGING =====================

    public function logVehicle(Request $request, VisitorService $service)
    {
        $result = $service->logVehicle($request->all());

        return redirect()->route('visitors.index')->with(
            $result['success'] ? 'flash_success' : 'flash_error',
            $result['success'] ? 'Vehicle logged successfully.' : $result['error']
        );
    }

    public function getVehicles(Request $request, VisitorService $service)
    {
        $visitorId = (int) $request->query('visitor_id', 0);
        $visitId = (int) $request->query('visit_id', 0);

        if ($visitorId > 0) {
            $vehicles = $service->getVehiclesForVisitor($visitorId);
        } elseif ($visitId > 0) {
            $vehicles = $service->getVehiclesForVisit($visitId);
        } else {
            $vehicles = [];
        }

        return response()->json(['vehicles' => $vehicles]);
    }

    // ===================== NDA SIGNATURE =====================

    public function setNdaRequired(Request $request, string $id, VisitorService $service)
    {
        $result = $service->setNdaRequired((int) $id, $request->boolean('nda_required'));

        return $this->flashOrJson(
            $request,
            redirect()->route('visitors.index'),
            $result['success'],
            $result['success'] ? 'NDA requirement updated.' : $result['error'],
            ['action' => 'visitor.nda_require', 'id' => (int) $id]
        );
    }

    public function signNda(Request $request, string $id, VisitorService $service)
    {
        $result = $service->signNda(
            (int) $id,
            $request->input('signature_data'),
            $request->ip()
        );

        return redirect()->route('visitors.index')->with(
            $result['success'] ? 'flash_success' : 'flash_error',
            $result['success'] ? 'NDA signed successfully.' : $result['error']
        );
    }

    // ===================== EMERGENCY ROSTER =====================

    public function emergencyRoster(VisitorService $service)
    {
        $roster = $service->getEmergencyRoster();

        return response()->json(['roster' => $roster]);
    }

    public function exportEmergencyRoster(VisitorService $service)
    {
        $roster = $service->getEmergencyRoster();
        $headers = ['Badge Code', 'Visitor Name', 'Company', 'Host Name', 'Host Phone', 'Check-in Time', 'Purpose', 'Allowed Areas'];
        $rows = [];
        foreach ($roster as $row) {
            $rows[] = [
                $row['badge_code'] ?? '',
                $row['visitor_name'] ?? '',
                $row['visitor_company'] ?? '',
                $row['host_name'] ?? '',
                $row['host_phone'] ?? '',
                $row['check_in_time'] ?? '',
                $row['purpose'] ?? '',
                $row['allowed_areas'] ?? '',
            ];
        }

        return $this->streamExportPdf('emergency_roster', 'Emergency Roster - ' . date('Y-m-d H:i'), $headers, $rows);
    }
}
