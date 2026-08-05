<?php

namespace App\Http\Controllers;

use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuditLogController extends Controller
{
    public function index(Request $request, AuditLogService $service)
    {
        $user = Auth::user();
        if (! $user->canPermission('audit.view') && ! $user->canPermission('users.manage')) {
            abort(403);
        }

        $filters = [
            'module' => trim((string) $request->query('module', '')),
            'action' => trim((string) $request->query('action', '')),
            'q' => trim((string) $request->query('q', '')),
        ];

        $options = $service->filterOptions();

        return view('audit.index', [
            'pageTitle' => 'Audit Log',
            'logs' => $service->list($filters),
            'filters' => $filters,
            'modules' => $options['modules'] ?? [],
            'actions' => $options['actions'] ?? [],
            'total' => $options['total'] ?? 0,
        ]);
    }
}