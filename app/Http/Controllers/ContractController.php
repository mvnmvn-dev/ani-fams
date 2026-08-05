<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespondsWithFlashOrJson;
use App\Services\ContractService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ContractController extends Controller
{
    use RespondsWithFlashOrJson;

    public function index(ContractService $service)
    {
        $users = DB::table('users')->where('status', 'active')->select('id', 'full_name', 'email')->orderBy('full_name')->get()->map(fn ($r) => (array) $r)->all();
        $cases = DB::table('legal_cases')->whereIn('status', ['open', 'in_progress', 'on_hold'])->select('id', 'title', 'case_number')->orderBy('title')->get()->map(fn ($r) => (array) $r)->all();

        return view('contracts.index', [
            'pageTitle' => 'Contract Management',
            'contracts' => $service->listContracts(),
            'types' => $service->types(),
            'users' => $users,
            'cases' => $cases,
            'canCreate' => Auth::user()->canPermission('contract.create'),
            'canManage' => Auth::user()->canPermission('contract.manage'),
            'openRecordId' => request()->query('open'),
        ]);
    }

    public function store(Request $request, ContractService $service)
    {
        $file = $request->file('contract_file');
        $fileInput = $file ? [
            'name' => $file->getClientOriginalName(),
            'tmp_name' => $file->getPathname(),
            'error' => UPLOAD_ERR_OK,
            'size' => $file->getSize(),
        ] : ['error' => UPLOAD_ERR_NO_FILE, 'name' => '', 'tmp_name' => ''];

        $result = $service->draftContract($request->all(), $fileInput);
        return redirect()->route('contracts.index')->with(
            $result['success'] ? 'flash_success' : 'flash_error',
            $result['success'] ? 'Contract drafted successfully.' : $result['error']
        );
    }

    public function show(Request $request, string $id, ContractService $service)
    {
        $c = $service->contractDetails((int) $id);
        if (! $c) {
            return redirect()->route('contracts.index')->with('flash_error', 'Contract not found.');
        }

        $myStep = null;
        foreach ($c['approvals'] ?? [] as $step) {
            if ((int) $step['approver_id'] === (int) Auth::id() && ($step['status'] ?? '') === 'pending') {
                $myStep = $step;
                break;
            }
        }

        $data = [
            'pageTitle' => $c['title'] ?? 'Contract',
            'c' => $c,
            'myStep' => $myStep,
            'canApprove' => Auth::user()->canPermission('contract.approve'),
            'canManage' => Auth::user()->canPermission('contract.manage') || (int) $c['owner_id'] === (int) Auth::id(),
            'asModal' => true,
        ];

        if ($request->boolean('modal') || $request->ajax()) {
            return response()->view('contracts.modal', $data);
        }

        return redirect()->route('contracts.index', ['open' => $id]);
    }

    public function approve(Request $request, string $id, ContractService $service)
    {
        $result = $service->decideApproval(
            (int) $id,
            (int) $request->input('approval_id'),
            (string) $request->input('decision'),
            (string) $request->input('comments', '')
        );
        $success = (bool) ($result['success'] ?? false);
        $message = $success ? 'Contract approval decision registered.' : (string) ($result['error'] ?? 'Unable to record decision.');

        return $this->flashOrJson(
            $request,
            redirect()->route('contracts.index', ['open' => $id]),
            $success,
            $message,
            array_merge(['action' => 'contract.approve'], $result)
        );
    }

    public function amend(Request $request, string $id, ContractService $service)
    {
        $file = $request->file('amendment_file');
        $fileInput = $file ? [
            'name' => $file->getClientOriginalName(),
            'tmp_name' => $file->getPathname(),
            'error' => UPLOAD_ERR_OK,
            'size' => $file->getSize(),
        ] : ['error' => UPLOAD_ERR_NO_FILE, 'name' => '', 'tmp_name' => ''];

        $result = $service->addAmendment((int) $id, $request->all(), $fileInput);
        return redirect()->route('contracts.index', ['open' => $id])->with(
            $result['success'] ? 'flash_success' : 'flash_error',
            $result['success'] ? 'Amendment recorded.' : $result['error']
        );
    }
}
