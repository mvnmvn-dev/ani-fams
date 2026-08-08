<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ExportsData;
use App\Http\Controllers\Concerns\RespondsWithFlashOrJson;
use App\Services\VisitorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VisitorController extends Controller
{
    use RespondsWithFlashOrJson;
    use ExportsData {
        exportCsv as streamExportCsv;
        exportPdf as streamExportPdf;
    }

    public function index(VisitorService $service)
    {
        return view('visitors.index', [
            'pageTitle' => 'Visitor Management',
            'visits' => $service->listVisits(),
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
}
