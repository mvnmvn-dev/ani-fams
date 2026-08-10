<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ExportsData;
use App\Http\Controllers\Concerns\RespondsWithFlashOrJson;
use App\Services\ContractService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ContractController extends Controller
{
    use RespondsWithFlashOrJson;
    use ExportsData {
        exportCsv as streamExportCsv;
        exportPdf as streamExportPdf;
    }

    public function index(ContractService $service)
    {
        $users = DB::table('users')->where('status', 'active')->select('id', 'full_name', 'email')->orderBy('full_name')->get()->map(fn ($r) => (array) $r)->all();
        $cases = DB::table('legal_cases')->whereIn('status', ['open', 'in_progress', 'on_hold'])->select('id', 'title', 'case_number')->orderBy('title')->get()->map(fn ($r) => (array) $r)->all();

        return view('contracts.index', [
            'pageTitle' => 'Contract Management',
            'contracts' => $service->listContracts(),
            'types' => $service->types(),
            'templates' => $service->templates(),
            'chains' => $service->approvalChains(),
            'spendRollup' => $service->spendRollup(),
            'vendorScoreRollup' => $service->vendorScoreRollup(),
            'users' => $users,
            'cases' => $cases,
            'canCreate' => Auth::user()->canPermission('contract.create'),
            'canManage' => Auth::user()->canPermission('contract.manage'),
            'openRecordId' => request()->query('open'),
            'openEsign' => request()->boolean('esign'),
        ]);
    }

    public function storeTemplate(Request $request, ContractService $service)
    {
        $result = $service->createTemplate($request->all());

        return redirect()->route('contracts.index')->with(
            $result['success'] ? 'flash_success' : 'flash_error',
            $result['success'] ? 'Contract template saved.' : $result['error']
        );
    }

    public function storeChain(Request $request, ContractService $service)
    {
        $result = $service->saveApprovalChain($request->all());

        return redirect()->route('contracts.index')->with(
            $result['success'] ? 'flash_success' : 'flash_error',
            $result['success'] ? 'Approval chain saved.' : $result['error']
        );
    }

    public function showSignForm(string $token, ContractService $service)
    {
        $result = $service->contractBySignToken($token);
        if (! $result['success']) {
            return redirect()->route('contracts.index')->with('flash_error', $result['error']);
        }

        return redirect()->route('contracts.index', [
            'open' => $result['contract']['id'],
            'esign' => 1,
        ]);
    }

    public function submitSign(Request $request, string $token, ContractService $service)
    {
        $result = $service->signByToken(
            $token,
            (string) $request->input('signer_name', ''),
            $request->ip(),
            $request->input('signature_data')
        );

        $contractId = $result['id'] ?? null;

        return redirect()->route('contracts.index', $contractId ? ['open' => $contractId] : [])->with(
            $result['success'] ? 'flash_success' : 'flash_error',
            $result['success'] ? 'Contract signed and activated.' : $result['error']
        );
    }

    public function amendmentDiff(string $id, string $amendmentId, ContractService $service)
    {
        $result = $service->amendmentDiff((int) $id, (int) $amendmentId);
        if (request()->ajax() || request()->wantsJson()) {
            return response()->json($result);
        }
        if (! ($result['success'] ?? false)) {
            return redirect()->route('contracts.index', ['open' => $id])->with('flash_error', $result['error'] ?? 'Unable to diff.');
        }

        return view('contracts.amendment_diff', [
            'pageTitle' => 'Amendment Diff',
            'contractId' => (int) $id,
            'result' => $result,
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

    public function autoDraftRenewal(string $id, ContractService $service)
    {
        $result = $service->autoDraftRenewal((int) $id);
        
        return redirect()->route('contracts.index', ['open' => $result['id'] ?? $id])->with(
            $result['success'] ? 'flash_success' : 'flash_error',
            $result['success'] ? 'Contract renewal drafted successfully.' : $result['error']
        );
    }

    public function addObligation(Request $request, string $id, ContractService $service)
    {
        $data = $request->all();
        $data['contract_id'] = (int) $id;
        $result = $service->addObligation($data);

        return redirect()->route('contracts.index', ['open' => $id])->with(
            $result['success'] ? 'flash_success' : 'flash_error',
            $result['success'] ? 'Obligation added.' : ($result['error'] ?? 'Unable to add obligation.')
        );
    }

    public function toggleObligation(Request $request, string $contractId, string $obligationId, ContractService $service)
    {
        $result = $service->toggleObligation((int) $obligationId, $request->boolean('completed'));
        $success = (bool) ($result['success'] ?? false);

        return $this->flashOrJson(
            $request,
            redirect()->route('contracts.index', ['open' => $contractId]),
            $success,
            $success ? 'Obligation updated.' : (string) ($result['error'] ?? 'Unable to update.'),
            array_merge(['action' => 'contract.obligation.toggle', 'contract_id' => (int) $contractId], $result)
        );
    }

    public function recordVendorScore(Request $request, string $id, ContractService $service)
    {
        $data = $request->all();
        $data['contract_id'] = (int) $id;
        $result = $service->recordVendorScore($data);

        return redirect()->route('contracts.index', ['open' => $id])->with(
            $result['success'] ? 'flash_success' : 'flash_error',
            $result['success'] ? 'Vendor score recorded.' : ($result['error'] ?? 'Unable to record score.')
        );
    }

    public function signatureCertificate(string $id, ContractService $service)
    {
        $result = $service->generateSignatureCertificate((int) $id);
        if (! ($result['success'] ?? false)) {
            return redirect()->route('contracts.index', ['open' => $id])->with(
                'flash_error',
                $result['error'] ?? 'Unable to generate certificate.'
            );
        }

        return response($result['html'], 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="signature_audit_' . $id . '.html"',
        ]);
    }

    public function destroy(string $id, ContractService $service)
    {
        $result = $service->softDelete((int) $id);

        return redirect()->route('contracts.index')->with(
            $result['success'] ? 'flash_success' : 'flash_error',
            $result['success'] ? 'Contract moved to trash.' : ($result['error'] ?? 'Unable to delete.')
        );
    }

    public function exportCsv(ContractService $service)
    {
        $contracts = $service->listContracts();
        $headers = ['ID', 'Contract Number', 'Title', 'Type', 'Counterparty Name', 'Counterparty Contact', 'Owner Name', 'Value Amount', 'Currency', 'Start Date', 'End Date', 'Auto Renew', 'Status', 'Created At'];
        $rows = [];
        foreach ($contracts as $row) {
            $rows[] = [
                $row['id'] ?? '',
                $row['contract_number'] ?? '',
                $row['title'] ?? '',
                $row['type_name'] ?? '',
                $row['counterparty_name'] ?? '',
                $row['counterparty_contact'] ?? '',
                $row['owner_name'] ?? '',
                $row['value_amount'] ?? '',
                $row['currency'] ?? '',
                $row['start_date'] ?? '',
                $row['end_date'] ?? '',
                ! empty($row['auto_renew']) ? 'Yes' : 'No',
                $row['status'] ?? '',
                $row['created_at'] ?? '',
            ];
        }

        return $this->streamExportCsv('contracts', $headers, $rows);
    }

    public function exportPdf(ContractService $service)
    {
        $contracts = $service->listContracts();
        $headers = ['ID', 'Number', 'Title', 'Type', 'Counterparty', 'Owner', 'Value', 'Status'];
        $rows = [];
        foreach ($contracts as $row) {
            $rows[] = [
                $row['id'] ?? '',
                $row['contract_number'] ?? '',
                $row['title'] ?? '',
                $row['type_name'] ?? '',
                $row['counterparty_name'] ?? '',
                $row['owner_name'] ?? '',
                trim(($row['currency'] ?? '') . ' ' . ($row['value_amount'] ?? '')),
                $row['status'] ?? '',
            ];
        }

        return $this->streamExportPdf('contracts', 'Contracts Export', $headers, $rows);
    }
}
