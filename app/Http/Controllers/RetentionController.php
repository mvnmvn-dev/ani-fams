<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ExportsData;
use App\Http\Controllers\Concerns\RespondsWithFlashOrJson;
use App\Services\RetentionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RetentionController extends Controller
{
    use RespondsWithFlashOrJson;
    use ExportsData {
        exportCsv as streamExportCsv;
        exportPdf as streamExportPdf;
    }

    public function index(RetentionService $service)
    {
        return view('retention.index', [
            'pageTitle' => 'Records Retention & Compliance',
            'policies' => $service->listPolicies(),
            'assignments' => $service->listAssignments(),
            'holds' => $service->listHolds(),
            'availDocs' => $service->listAvailableDocuments(),
            'allDocs' => $service->listAllDocuments(),
            'legalCases' => $service->listLegalCases(),
            'canManage' => Auth::user()->canPermission('retention.manage'),
            'templates' => $service->getPolicyTemplates(),
            'unreadHoldNotifs' => $service->getUnreadHoldNotifications(),
        ]);
    }

    public function storePolicy(Request $request, RetentionService $service)
    {
        $result = $service->createPolicy($request->all());

        return redirect()->route('retention.index')->with(
            $result['success'] ? 'flash_success' : 'flash_error',
            $result['success'] ? 'Retention policy created successfully.' : $result['error']
        );
    }

    public function storeAssignment(Request $request, RetentionService $service)
    {
        $result = $service->assignPolicy($request->all());

        return redirect()->route('retention.index')->with(
            $result['success'] ? 'flash_success' : 'flash_error',
            $result['success'] ? 'Retention policy assigned to document.' : $result['error']
        );
    }

    public function dispose(Request $request, string $id, RetentionService $service)
    {
        $result = $service->executeDisposal((int) $id);
        $success = (bool) ($result['success'] ?? false);
        $message = $success ? 'Disposal action executed.' : (string) ($result['error'] ?? 'Unable to dispose.');

        return $this->flashOrJson(
            $request,
            redirect()->route('retention.index'),
            $success,
            $message,
            array_merge(['action' => 'retention.dispose'], $result)
        );
    }

    public function storeHold(Request $request, RetentionService $service)
    {
        $result = $service->placeHold($request->all());

        return redirect()->route('retention.index')->with(
            $result['success'] ? 'flash_success' : 'flash_error',
            $result['success'] ? 'Legal hold placed successfully.' : $result['error']
        );
    }

    public function releaseHold(Request $request, string $id, RetentionService $service)
    {
        $result = $service->releaseHold((int) $id);
        $success = (bool) ($result['success'] ?? false);
        $message = $success ? 'Legal hold released.' : (string) ($result['error'] ?? 'Unable to release hold.');

        return $this->flashOrJson(
            $request,
            redirect()->route('retention.index'),
            $success,
            $message,
            array_merge(['action' => 'retention.release'], $result)
        );
    }

    public function exportCsv(RetentionService $service)
    {
        $assignments = $service->listAssignments();
        $headers = ['ID', 'Document Title', 'Document Number', 'Owner Name', 'Policy Name', 'Disposal Action', 'Retention Start Date', 'Calculated Disposal Date', 'Status'];
        $rows = [];
        foreach ($assignments as $row) {
            $rows[] = [
                $row['id'] ?? '',
                $row['document_title'] ?? '',
                $row['document_number'] ?? '',
                $row['owner_name'] ?? '',
                $row['policy_name'] ?? '',
                $row['disposal_action'] ?? '',
                $row['retention_start_date'] ?? '',
                $row['calculated_disposal_date'] ?? '',
                $row['status'] ?? '',
            ];
        }

        return $this->streamExportCsv('retention_assignments', $headers, $rows);
    }

    public function exportPdf(RetentionService $service)
    {
        $assignments = $service->listAssignments();
        $headers = ['ID', 'Document', 'Owner', 'Policy', 'Disposal Date', 'Status'];
        $rows = [];
        foreach ($assignments as $row) {
            $rows[] = [
                $row['id'] ?? '',
                $row['document_title'] ?? '',
                $row['owner_name'] ?? '',
                $row['policy_name'] ?? '',
                $row['calculated_disposal_date'] ?? '',
                $row['status'] ?? '',
            ];
        }

        return $this->streamExportPdf('retention_assignments', 'Retention Assignments Export', $headers, $rows);
    }

    // ===================== DISPOSAL CERTIFICATE GENERATION =====================

    public function generateCertificate(Request $request, string $id, RetentionService $service)
    {
        $result = $service->generateDisposalCertificate((int) $id);

        return redirect()->route('retention.index')->with(
            $result['success'] ? 'flash_success' : 'flash_error',
            $result['success'] ? 'Disposal certificate generated.' : $result['error']
        );
    }

    public function disposalsWithoutCertificates(RetentionService $service)
    {
        $disposals = $service->getDisposalsWithoutCertificates();

        return response()->json(['disposals' => $disposals]);
    }

    // ===================== CUSTODIAN NOTIFICATIONS =====================

    public function holdNotifications(RetentionService $service)
    {
        $notifications = $service->getUnreadHoldNotifications();

        return response()->json(['notifications' => $notifications]);
    }

    public function markNotificationRead(Request $request, string $id, RetentionService $service)
    {
        $result = $service->markHoldNotificationRead((int) $id);

        return response()->json($result);
    }

    public function placeHoldWithNotifications(Request $request, RetentionService $service)
    {
        $result = $service->placeHoldWithNotifications($request->all());

        return redirect()->route('retention.index')->with(
            $result['success'] ? 'flash_success' : 'flash_error',
            $result['success'] ? 'Legal hold placed with notifications.' : $result['error']
        );
    }

    public function releaseHoldWithNotifications(Request $request, string $id, RetentionService $service)
    {
        $result = $service->releaseHoldWithNotifications((int) $id);

        return redirect()->route('retention.index')->with(
            $result['success'] ? 'flash_success' : 'flash_error',
            $result['success'] ? 'Legal hold released with notifications.' : $result['error']
        );
    }

    // ===================== POLICY TEMPLATES =====================

    public function policyTemplates(RetentionService $service)
    {
        $templates = $service->getPolicyTemplates();

        return response()->json(['templates' => $templates]);
    }

    public function createFromTemplate(Request $request, RetentionService $service)
    {
        $result = $service->createPolicyFromTemplate(
            (int) $request->input('template_id'),
            $request->input('custom_name')
        );

        return redirect()->route('retention.index')->with(
            $result['success'] ? 'flash_success' : 'flash_error',
            $result['success'] ? 'Policy created from template.' : $result['error']
        );
    }

    // ===================== COMPLIANCE CALENDAR =====================

    public function complianceCalendar(Request $request, RetentionService $service)
    {
        $startDate = $request->query('start_date', date('Y-m-01'));
        $endDate = $request->query('end_date', date('Y-m-t'));
        $events = $service->getComplianceCalendar($startDate, $endDate);

        return response()->json(['events' => $events]);
    }

    public function upcomingDisposals(Request $request, RetentionService $service)
    {
        $daysAhead = (int) $request->query('days_ahead', 30);
        $disposals = $service->getUpcomingDisposals($daysAhead);

        return response()->json(['disposals' => $disposals]);
    }

    public function activeHolds(RetentionService $service)
    {
        $holds = $service->getActiveHolds();

        return response()->json(['holds' => $holds]);
    }
}
