<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ExportsData;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuditLogController extends Controller
{
    use ExportsData {
        exportCsv as streamExportCsv;
        exportPdf as streamExportPdf;
    }

    private function assertCanView(): void
    {
        $user = Auth::user();
        if (! $user->canPermission('audit.view') && ! $user->canPermission('users.manage')) {
            abort(403);
        }
    }

    private function filtersFromRequest(Request $request): array
    {
        return [
            'module' => trim((string) $request->query('module', '')),
            'action' => trim((string) $request->query('action', '')),
            'q' => trim((string) $request->query('q', '')),
        ];
    }

    public function index(Request $request, AuditLogService $service)
    {
        $this->assertCanView();

        $filters = $this->filtersFromRequest($request);
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

    public function exportCsv(Request $request, AuditLogService $service)
    {
        $this->assertCanView();
        $logs = $service->list($this->filtersFromRequest($request), 5000);
        $headers = ['ID', 'User', 'Module', 'Action', 'Entity Type', 'Entity ID', 'IP', 'Created At'];
        $rows = [];
        foreach ($logs as $row) {
            $rows[] = [
                $row['id'] ?? '',
                $row['actor_name'] ?? $row['user_name'] ?? $row['full_name'] ?? '',
                $row['module'] ?? '',
                $row['action'] ?? '',
                $row['entity_type'] ?? '',
                $row['entity_id'] ?? '',
                $row['ip_address'] ?? '',
                $row['created_at'] ?? '',
            ];
        }

        return $this->streamExportCsv('audit_logs', $headers, $rows);
    }

    public function exportPdf(Request $request, AuditLogService $service)
    {
        $this->assertCanView();
        $logs = $service->list($this->filtersFromRequest($request), 5000);
        $headers = ['ID', 'User', 'Module', 'Action', 'Entity', 'IP', 'Created At'];
        $rows = [];
        foreach ($logs as $row) {
            $rows[] = [
                $row['id'] ?? '',
                $row['actor_name'] ?? $row['user_name'] ?? $row['full_name'] ?? '',
                $row['module'] ?? '',
                $row['action'] ?? '',
                ($row['entity_type'] ?? '') . ' #' . ($row['entity_id'] ?? ''),
                $row['ip_address'] ?? '',
                $row['created_at'] ?? '',
            ];
        }

        return $this->streamExportPdf('audit_logs', 'Audit Log Export', $headers, $rows);
    }
}
