<?php

namespace App\Http\Controllers;

use App\Services\LegalCaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LegalCaseController extends Controller
{
    public function index(LegalCaseService $service)
    {
        $counsels = DB::table('employees')->select('id', 'full_name', 'position')->orderBy('full_name')->get()->map(fn ($r) => (array) $r)->all();

        return view('legal.index', [
            'pageTitle' => 'Legal Management',
            'cases' => $service->listCases(),
            'counsels' => $counsels,
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
        return redirect()->route('legal.index', ['open' => $caseId])->with(
            $result['success'] ? 'flash_success' : 'flash_error',
            $result['success'] ? 'Milestone status updated.' : $result['error']
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
}
