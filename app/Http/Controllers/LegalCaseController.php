<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ExportsData;
use App\Http\Controllers\Concerns\RespondsWithFlashOrJson;
use App\Services\LegalCaseService;
use App\Services\ArnelAiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LegalCaseController extends Controller
{
    use RespondsWithFlashOrJson;
    use ExportsData {
        exportCsv as streamExportCsv;
        exportPdf as streamExportPdf;
    }

    public function index(LegalCaseService $service, ArnelAiService $arnel)
    {
        $counsels = DB::table('employees')->select('id', 'full_name', 'position')->orderBy('full_name')->get()->map(fn ($r) => (array) $r)->all();
        $cases = $service->listCases();

        return view('legal.index', [
            'pageTitle' => 'Legal Management',
            'cases' => $cases,
            'aiInsights' => $arnel->legalCaseInsights($cases),
            'counsels' => $counsels,
            'vendors' => $service->allVendors(),
            'budgetRollup' => $service->budgetRollup(),
            'canManage' => Auth::user()->canPermission('legal.manage'),
            'openRecordId' => request()->query('open'),
        ]);
    }

    public function store(Request $request, LegalCaseService $service)
    {
        $result = $service->createCase($request->all());
        return redirect()->route('legal.index')->with(
            $result['success'] ? 'flash_success' : 'flash_error',
            $result['success'] ? 'Legal case created successfully.' : $result['error']
        );
    }

    public function show(Request $request, string $id, LegalCaseService $service)
    {
        $case = $service->caseDetails((int) $id);
        if (! $case) {
            return redirect()->route('legal.index')->with('flash_error', 'Legal case not found.');
        }

        $allDocs = DB::table('documents')->where('status', 'active')->select('id', 'title', 'document_number')->orderBy('title')->get()->map(fn ($r) => (array) $r)->all();

        $data = [
            'pageTitle' => $case['title'] ?? 'Legal Case',
            'case' => $case,
            'allDocs' => $allDocs,
            'vendors' => $service->allVendors(),
            'canManage' => Auth::user()->canPermission('legal.manage'),
            'asModal' => true,
        ];

        if ($request->boolean('modal') || $request->ajax()) {
            return response()->view('legal.modal', $data);
        }

        return redirect()->route('legal.index', ['open' => $id]);
    }

    public function addParty(Request $request, string $id, LegalCaseService $service)
    {
        $data = $request->all();
        $data['case_id'] = (int) $id;
        $result = $service->addParty($data);
        return redirect()->route('legal.index', ['open' => $id])->with(
            $result['success'] ? 'flash_success' : 'flash_error',
            $result['success'] ? 'Party added successfully.' : $result['error']
        );
    }

    public function addMilestone(Request $request, string $id, LegalCaseService $service)
    {
        $data = $request->all();
        $data['case_id'] = (int) $id;
        $result = $service->addMilestone($data);
        return redirect()->route('legal.index', ['open' => $id])->with(
            $result['success'] ? 'flash_success' : 'flash_error',
            $result['success'] ? 'Milestone added successfully.' : $result['error']
        );
    }

    public function toggleMilestone(Request $request, string $caseId, string $milestoneId, LegalCaseService $service)
    {
        $result = $service->toggleMilestone((int) $milestoneId, $request->boolean('completed'));
        $success = (bool) ($result['success'] ?? false);
        $message = $success ? 'Milestone status updated.' : (string) ($result['error'] ?? 'Unable to update milestone.');

        return $this->flashOrJson(
            $request,
            redirect()->route('legal.index', ['open' => $caseId]),
            $success,
            $message,
            array_merge(['action' => 'legal.milestone.toggle', 'case_id' => (int) $caseId], $result)
        );
    }

    public function addDeadline(Request $request, string $id, LegalCaseService $service)
    {
        $data = $request->all();
        $data['case_id'] = (int) $id;
        $result = $service->addDeadline($data);
        return redirect()->route('legal.index', ['open' => $id])->with(
            $result['success'] ? 'flash_success' : 'flash_error',
            $result['success'] ? 'Deadline added successfully.' : $result['error']
        );
    }

    public function toggleDeadline(Request $request, string $caseId, string $deadlineId, LegalCaseService $service)
    {
        $result = $service->toggleDeadline((int) $deadlineId, $request->boolean('completed'));
        $success = (bool) ($result['success'] ?? false);
        $message = $success ? 'Deadline status updated.' : (string) ($result['error'] ?? 'Unable to update deadline.');

        return $this->flashOrJson(
            $request,
            redirect()->route('legal.index', ['open' => $caseId]),
            $success,
            $message,
            array_merge(['action' => 'legal.deadline.toggle', 'case_id' => (int) $caseId], $result)
        );
    }

    public function linkDocument(Request $request, string $id, LegalCaseService $service)
    {
        $result = $service->linkDocument((int) $id, (int) $request->input('document_id'));
        return redirect()->route('legal.index', ['open' => $id])->with(
            $result['success'] ? 'flash_success' : 'flash_error',
            $result['success'] ? 'Document linked successfully.' : $result['error']
        );
    }

    public function addCost(Request $request, string $id, LegalCaseService $service)
    {
        $data = $request->all();
        $data['case_id'] = (int) $id;
        $result = $service->addCost($data);
        return redirect()->route('legal.index', ['open' => $id])->with(
            $result['success'] ? 'flash_success' : 'flash_error',
            $result['success'] ? 'Expense logged successfully.' : $result['error']
        );
    }

    public function destroy(string $id, LegalCaseService $service)
    {
        $result = $service->softDelete((int) $id);

        return redirect()->route('legal.index')->with(
            $result['success'] ? 'flash_success' : 'flash_error',
            $result['success'] ? 'Legal case moved to trash.' : ($result['error'] ?? 'Unable to delete.')
        );
    }

    public function storeVendor(Request $request, LegalCaseService $service)
    {
        $result = $service->addVendor($request->all());

        return redirect()->route('legal.index')->with(
            $result['success'] ? 'flash_success' : 'flash_error',
            $result['success'] ? 'Vendor / counsel contact saved.' : $result['error']
        );
    }

    public function linkVendor(Request $request, string $id, LegalCaseService $service)
    {
        $result = $service->linkVendorToCase(
            (int) $id,
            (int) $request->input('vendor_id'),
            $request->input('role_note')
        );

        return redirect()->route('legal.index', ['open' => $id])->with(
            $result['success'] ? 'flash_success' : 'flash_error',
            $result['success'] ? 'Vendor linked to case.' : $result['error']
        );
    }

    public function updateBudget(Request $request, string $id, LegalCaseService $service)
    {
        $result = $service->updateBudget((int) $id, $request->input('budget_amount'));

        return redirect()->route('legal.index', ['open' => $id])->with(
            $result['success'] ? 'flash_success' : 'flash_error',
            $result['success'] ? 'Budget updated.' : $result['error']
        );
    }

    public function recordOutcome(Request $request, string $id, LegalCaseService $service)
    {
        $data = $request->all();
        $data['case_id'] = (int) $id;
        $data['is_appealed'] = $request->boolean('is_appealed');
        $result = $service->recordOutcome($data);

        return redirect()->route('legal.index', ['open' => $id])->with(
            $result['success'] ? 'flash_success' : 'flash_error',
            $result['success'] ? 'Outcome recorded.' : ($result['error'] ?? 'Unable to record outcome.')
        );
    }

    public function flagPartyConflict(Request $request, string $id, LegalCaseService $service)
    {
        $data = $request->all();
        $data['case_id'] = (int) $id;
        $result = $service->flagPartyConflict($data);

        return redirect()->route('legal.index', ['open' => $id])->with(
            $result['success'] ? 'flash_success' : 'flash_error',
            $result['success'] ? 'Party conflict flagged.' : ($result['error'] ?? 'Unable to flag conflict.')
        );
    }

    public function flagCounselConflict(Request $request, string $id, LegalCaseService $service)
    {
        $data = $request->all();
        $data['case_id'] = (int) $id;
        $result = $service->flagCounselConflict($data);

        return redirect()->route('legal.index', ['open' => $id])->with(
            $result['success'] ? 'flash_success' : 'flash_error',
            $result['success'] ? 'Counsel conflict flagged.' : ($result['error'] ?? 'Unable to flag conflict.')
        );
    }

    public function resolvePartyConflict(Request $request, string $caseId, string $conflictId, LegalCaseService $service)
    {
        $result = $service->resolveConflict((int) $conflictId, $request->all());

        return redirect()->route('legal.index', ['open' => $caseId])->with(
            $result['success'] ? 'flash_success' : 'flash_error',
            $result['success'] ? 'Conflict resolution updated.' : ($result['error'] ?? 'Unable to update.')
        );
    }

    public function verifyCounselConflict(Request $request, string $caseId, string $conflictId, LegalCaseService $service)
    {
        $result = $service->verifyCounselConflict(
            (int) $conflictId,
            $request->boolean('verified'),
            $request->input('mitigation_plan')
        );

        return redirect()->route('legal.index', ['open' => $caseId])->with(
            $result['success'] ? 'flash_success' : 'flash_error',
            $result['success'] ? 'Counsel conflict updated.' : ($result['error'] ?? 'Unable to update.')
        );
    }

    public function runConflictScan(Request $request, string $id, LegalCaseService $service)
    {
        $result = $service->runConflictScan((int) $id);
        $success = (bool) ($result['success'] ?? false);
        $flagged = (int) ($result['flagged'] ?? 0);
        $matchCount = count($result['matches'] ?? []);
        $message = $success
            ? ($matchCount === 0
                ? 'Conflict scan complete — no potential issues found.'
                : "Conflict scan complete — {$matchCount} match(es), {$flagged} new flag(s).")
            : ($result['error'] ?? 'Conflict scan failed.');

        return $this->flashOrJson(
            $request,
            redirect()->route('legal.index', ['open' => $id]),
            $success,
            $message,
            array_merge(['action' => 'legal.conflict.scan', 'case_id' => (int) $id], $result)
        );
    }

    public function exportCsv(LegalCaseService $service)
    {
        $cases = $service->listCases();
        $headers = ['ID', 'Case Number', 'Title', 'Type', 'Status', 'Filed Date', 'Court/Agency', 'Opposing Party', 'Assigned Counsel', 'External Counsel', 'Risk Level', 'Description', 'Created At'];
        $rows = [];
        foreach ($cases as $row) {
            $rows[] = [
                $row['id'] ?? '',
                $row['case_number'] ?? '',
                $row['title'] ?? '',
                $row['case_type'] ?? '',
                $row['status'] ?? '',
                $row['filed_date'] ?? '',
                $row['court_or_agency'] ?? '',
                $row['opposing_party'] ?? '',
                $row['counsel_name'] ?? '',
                $row['external_counsel_name'] ?? '',
                $row['risk_level'] ?? '',
                $row['description'] ?? '',
                $row['created_at'] ?? '',
            ];
        }

        return $this->streamExportCsv('legal_cases', $headers, $rows);
    }

    public function exportPdf(LegalCaseService $service)
    {
        $cases = $service->listCases();
        $headers = ['ID', 'Case Number', 'Title', 'Type', 'Status', 'Counsel', 'Risk'];
        $rows = [];
        foreach ($cases as $row) {
            $rows[] = [
                $row['id'] ?? '',
                $row['case_number'] ?? '',
                $row['title'] ?? '',
                $row['case_type'] ?? '',
                $row['status'] ?? '',
                $row['counsel_name'] ?? '',
                $row['risk_level'] ?? '',
            ];
        }

        return $this->streamExportPdf('legal_cases', 'Legal Cases Export', $headers, $rows);
    }
}
