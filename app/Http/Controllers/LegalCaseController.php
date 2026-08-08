<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ExportsData;
use App\Http\Controllers\Concerns\RespondsWithFlashOrJson;
use App\Services\LegalCaseService;
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

    public function index(LegalCaseService $service)
    {
        $counsels = DB::table('employees')->select('id', 'full_name', 'position')->orderBy('full_name')->get()->map(fn ($r) => (array) $r)->all();

        return view('legal.index', [
            'pageTitle' => 'Legal Management',
            'cases' => $service->listCases(),
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
