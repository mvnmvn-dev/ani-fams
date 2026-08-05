<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespondsWithFlashOrJson;
use App\Services\RetentionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RetentionController extends Controller
{
    use RespondsWithFlashOrJson;

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
}
