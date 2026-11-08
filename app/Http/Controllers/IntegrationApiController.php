<?php

namespace App\Http\Controllers;

use App\Services\ReservationService;
use App\Services\VisitorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IntegrationApiController extends Controller
{
    public function departments(): JsonResponse
    {
        return response()->json(['data' => DB::table('departments')->orderBy('name')->get(['id', 'name'])]);
    }

    public function facilities(): JsonResponse
    {
        return response()->json(['data' => DB::table('facilities')
            ->where('status', '!=', 'retired')
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'location', 'building_name', 'floor_designation', 'capacity', 'status'])]);
    }

    public function visitors(Request $request): JsonResponse
    {
        $query = DB::table('visitors')->whereNull('deleted_at')->orderBy('full_name');
        if ($request->filled('q')) {
            $term = '%' . $request->string('q') . '%';
            $query->where(function ($builder) use ($term): void {
                $builder->where('full_name', 'like', $term)
                    ->orWhere('id_number', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('company', 'like', $term);
            });
        }
        return response()->json(['data' => $query->get()]);
    }

    public function visitor(string $id): JsonResponse
    {
        $visitor = DB::table('visitors')->where('id', (int) $id)->whereNull('deleted_at')->first();
        if (! $visitor) {
            return response()->json(['message' => 'Visitor profile not found.'], 404);
        }
        return response()->json(['data' => $visitor]);
    }

    public function visitorHistory(string $id): JsonResponse
    {
        $visitorId = (int) $id;
        $visitor = DB::table('visitors')->where('id', $visitorId)->whereNull('deleted_at')->first();
        if (! $visitor) {
            return response()->json(['message' => 'Visitor profile not found.'], 404);
        }

        $visits = DB::table('visits as v')
            ->join('employees as e', 'e.id', '=', 'v.host_employee_id')
            ->leftJoin('visitor_badges as b', 'b.visit_id', '=', 'v.id')
            ->where('v.visitor_id', $visitorId)
            ->whereNull('v.deleted_at')
            ->orderByDesc('v.scheduled_at')
            ->get([
                'v.id', 'v.visit_category', 'v.source_system', 'v.source_ref', 'v.purpose',
                'v.building_name', 'v.floor_designation', 'v.scheduled_at', 'v.check_in_time',
                'v.check_out_time', 'v.status', 'v.nda_required', 'v.nda_signed',
                'e.id as host_employee_id', 'e.full_name as host_name',
                'b.badge_code', 'b.issued_at as badge_issued_at', 'b.expires_at as badge_expires_at',
            ]);

        $visitIds = $visits->pluck('id')->all();
        $areas = $visitIds === [] ? collect() : DB::table('visit_area_access as vaa')
            ->join('visit_areas as va', 'va.id', '=', 'vaa.visit_area_id')
            ->whereIn('vaa.visit_id', $visitIds)
            ->get(['vaa.visit_id', 'va.id', 'va.name']);
        $vehicles = DB::table('visitor_vehicles')
            ->where('visitor_id', $visitorId)
            ->orderByDesc('created_at')
            ->get(['id', 'visit_id', 'plate_number', 'vehicle_make', 'vehicle_model', 'vehicle_color', 'created_at']);

        $total = $visits->count();
        $firstVisit = $visits->first();
        $lastVisit = $visits->last();
        return response()->json([
            'visitor' => $visitor,
            'summary' => [
                'total_visits' => $total,
                'first_visit' => $lastVisit?->scheduled_at,
                'last_visit' => $firstVisit?->scheduled_at,
                'no_shows' => $visits->where('status', 'no_show')->count(),
                'last_host' => $firstVisit?->host_name,
            ],
            'visits' => $visits,
            'areas' => $areas,
            'vehicles' => $vehicles,
        ]);
    }

    public function storeVisitor(Request $request): JsonResponse
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:150'],
            'id_number' => ['required', 'string', 'max:100'],
            'company' => ['nullable', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);
        $id = DB::table('visitors')->insertGetId(array_merge($data, [
            'is_watchlisted' => false,
            'created_at' => now(),
        ]));
        return response()->json(['data' => DB::table('visitors')->where('id', $id)->first()], 201);
    }

    public function visits(Request $request): JsonResponse
    {
        $query = DB::table('visits as v')
            ->join('visitors as p', 'p.id', '=', 'v.visitor_id')
            ->join('employees as e', 'e.id', '=', 'v.host_employee_id')
            ->whereNull('v.deleted_at')
            ->whereNull('p.deleted_at')
            ->orderByDesc('v.scheduled_at');
        if ($request->filled('status')) {
            $query->where('v.status', $request->string('status'));
        }
        return response()->json(['data' => $query->get([
            'v.id', 'v.visitor_id', 'p.full_name as visitor_name', 'p.id_number',
            'v.host_employee_id', 'e.full_name as host_name', 'v.purpose',
            'v.building_name', 'v.floor_designation', 'v.scheduled_at',
            'v.check_in_time', 'v.check_out_time', 'v.badge_code', 'v.status',
        ])]);
    }

    public function storeVisit(Request $request, VisitorService $service): JsonResponse
    {
        $data = $request->validate([
            'visitor_id' => ['nullable', 'integer', 'exists:visitors,id'],
            'full_name' => ['required_without:visitor_id', 'string', 'max:150'],
            'id_number' => ['required_without:visitor_id', 'string', 'max:100'],
            'company' => ['nullable', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'host_employee_id' => ['required', 'integer', 'exists:employees,id'],
            'purpose' => ['required', 'string', 'max:255'],
            'building_name' => ['required', 'string', 'max:150'],
            'floor_designation' => ['required', 'string', 'max:100'],
            'scheduled_at' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ]);
        $result = $service->preRegister($data);
        return response()->json($result, ($result['success'] ?? false) ? 201 : 422);
    }

    public function reservations(Request $request): JsonResponse
    {
        $query = DB::table('reservations as r')
            ->join('facilities as f', 'f.id', '=', 'r.facility_id')
            ->join('users as u', 'u.id', '=', 'r.requested_by')
            ->leftJoin('departments as d', 'd.id', '=', 'r.department_id')
            ->whereNull('r.deleted_at')
            ->orderByDesc('r.start_datetime');
        if ($request->filled('status')) {
            $query->where('r.status', $request->string('status'));
        }
        return response()->json(['data' => $query->get([
            'r.id', 'r.facility_id', 'f.name as facility_name', 'f.building_name', 'f.floor_designation',
            'r.requested_by', 'u.full_name as requester_name', 'r.department_id', 'd.name as department_name',
            'r.purpose', 'r.start_datetime', 'r.end_datetime', 'r.status', 'r.check_in_code',
        ])]);
    }

    public function storeReservation(Request $request, ReservationService $service): JsonResponse
    {
        $data = $request->validate([
            'facility_id' => ['required', 'integer', 'exists:facilities,id'],
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'purpose' => ['required', 'string', 'max:255'],
            'start_datetime' => ['required', 'date'],
            'end_datetime' => ['required', 'date', 'after:start_datetime'],
            'recurrence_rule' => ['nullable', 'in:none,daily,weekly,monthly'],
            'recurrence_until' => ['nullable', 'date'],
            'attendee_count' => ['nullable', 'integer', 'min:1'],
        ]);
        $result = $service->submitRequest($data);
        return response()->json($result, ($result['success'] ?? false) ? 201 : 422);
    }

    /**
     * Read-only integration feed for the remaining top-level modules and
     * supporting records. The allowlist prevents arbitrary table access.
     */
    public function moduleData(Request $request, string $resource): JsonResponse
    {
        $feeds = [
            'users' => ['table' => 'users', 'permission' => 'users.manage', 'columns' => ['id', 'department_id', 'full_name', 'email', 'role_id', 'status', 'photo_path', 'created_at', 'updated_at']],
            'employees' => ['table' => 'employees', 'permission' => 'visitor.view', 'columns' => ['id', 'user_id', 'department_id', 'full_name', 'position', 'email', 'phone', 'source_system', 'employee_no']],
            'visitors' => ['table' => 'visitors', 'permission' => 'visitor.view', 'columns' => ['id', 'full_name', 'id_number', 'company', 'email', 'phone', 'visitor_type', 'source_system', 'source_ref', 'id_verified_at', 'created_at']],
            'visits' => ['table' => 'visits', 'permission' => 'visitor.view', 'columns' => ['id', 'visitor_id', 'host_employee_id', 'purpose', 'building_name', 'floor_designation', 'visit_category', 'source_system', 'source_ref', 'scheduled_at', 'check_in_time', 'check_out_time', 'status']],
            'equipment' => ['table' => 'equipment_items', 'permission' => 'reservation.view', 'columns' => ['id', 'name', 'category', 'quantity_available', 'status', 'notes', 'created_at']],
            'visit-areas' => ['table' => 'visit_areas', 'permission' => 'visitor.view', 'columns' => ['id', 'name']],
            'vehicles' => ['table' => 'visitor_vehicles', 'permission' => 'visitor.view', 'columns' => ['id', 'visitor_id', 'visit_id', 'plate_number', 'vehicle_make', 'vehicle_model', 'vehicle_color', 'notes', 'created_at']],
            'badges' => ['table' => 'visitor_badges', 'permission' => 'visitor.view', 'columns' => ['id', 'visit_id', 'visitor_id', 'badge_code', 'visitor_name', 'id_number', 'company', 'host_name', 'purpose', 'building_name', 'floor_designation', 'issued_at', 'expires_at', 'status', 'created_at']],
            'reservation-equipment' => ['table' => 'reservation_equipment', 'permission' => 'reservation.view', 'columns' => ['reservation_id', 'equipment_id', 'quantity']],
            'reservation-waitlist' => ['table' => 'reservation_waitlist', 'permission' => 'reservation.view', 'columns' => ['id', 'facility_id', 'requested_by', 'purpose', 'preferred_start_datetime', 'preferred_end_datetime', 'status', 'offered_reservation_id', 'notes', 'created_at', 'updated_at']],
            'documents' => ['table' => 'documents', 'permission' => 'document.view', 'columns' => ['id', 'document_number', 'title', 'description', 'category_id', 'owner_id', 'confidentiality', 'status', 'current_version_id', 'source_system', 'source_ref', 'source_doc_type', 'employee_no', 'period_start', 'period_end', 'created_at', 'deleted_at']],
            'document-versions' => ['table' => 'document_versions', 'permission' => 'document.view', 'scope' => 'documents:read', 'columns' => ['id', 'document_id', 'version_no', 'checksum', 'ocr_processed', 'uploaded_at', 'change_notes']],
            'document-categories' => ['table' => 'document_categories', 'permission' => 'document.view', 'columns' => ['id', 'name', 'parent_id']],
            'contracts' => ['table' => 'contracts', 'permission' => 'contract.view', 'columns' => ['id', 'contract_number', 'title', 'contract_type_id', 'counterparty_name', 'counterparty_contact', 'status', 'start_date', 'end_date', 'owner_id', 'created_at', 'deleted_at']],
            'contract-obligations' => ['table' => 'contract_obligations', 'permission' => 'contract.view', 'columns' => ['id', 'contract_id', 'title', 'description', 'responsible_party', 'due_date', 'is_completed', 'completed_at', 'created_by', 'created_at', 'updated_at']],
            'legal-cases' => ['table' => 'legal_cases', 'permission' => 'legal.view', 'columns' => ['id', 'case_number', 'title', 'case_type', 'status', 'filed_date', 'court_or_agency', 'opposing_party', 'assigned_counsel_id', 'risk_level', 'next_deadline', 'created_at', 'deleted_at']],
            'legal-deadlines' => ['table' => 'legal_case_deadlines', 'permission' => 'legal.view', 'columns' => ['id', 'case_id', 'title', 'due_date', 'description', 'is_completed', 'created_at', 'updated_at']],
            'legal-milestones' => ['table' => 'legal_case_milestones', 'permission' => 'legal.view', 'columns' => ['id', 'case_id', 'milestone_type', 'milestone_date', 'description', 'is_completed', 'reminder_sent_at']],
            'retention-policies' => ['table' => 'retention_policies', 'permission' => 'retention.view', 'columns' => ['id', 'name', 'record_category', 'retention_period_months', 'trigger_event', 'disposal_action', 'legal_basis', 'created_at', 'updated_at']],
            'retention-assignments' => ['table' => 'retention_assignments', 'permission' => 'retention.view', 'columns' => ['id', 'document_id', 'policy_id', 'retention_start_date', 'calculated_disposal_date', 'status', 'ai_category', 'archive_status']],
            'notifications' => ['table' => 'notifications', 'permission' => 'reservation.view', 'columns' => ['id', 'user_id', 'module', 'title', 'body', 'is_read', 'link', 'created_at']],
            'audit-logs' => ['table' => 'audit_logs', 'permission' => 'audit.view', 'columns' => ['id', 'user_id', 'module', 'action', 'entity_type', 'entity_id', 'old_values', 'new_values', 'ip_address', 'created_at']],
        ];

        $feed = $feeds[$resource] ?? null;
        if (! $feed) {
            return response()->json(['message' => 'Unsupported integration resource.'], 404);
        }
        $scopeByPermission = [
            'users.manage' => 'users:read', 'visitor.view' => 'visitors:read',
            'reservation.view' => 'reservations:read', 'document.view' => 'documents:read',
            'contract.view' => 'contracts:read', 'legal.view' => 'legal:read',
            'retention.view' => 'retention:read', 'audit.view' => 'audit:read',
        ];
        $requiredScope = $feed['scope'] ?? ($scopeByPermission[$feed['permission']] ?? null);
        $client = $request->attributes->get('integration_client');
        if (! $requiredScope || ! in_array($requiredScope, (array) ($client?->scopes ?? []), true)) {
            return response()->json(['message' => 'Integration scope is not permitted for this resource.'], 403);
        }
        $query = DB::table($feed['table'])->select($feed['columns']);
        if ($request->filled('id') && in_array('id', $feed['columns'], true)) {
            $query->where('id', (int) $request->query('id'));
        }
        if ($request->filled('updated_since') && in_array('updated_at', $feed['columns'], true)) {
            $query->where('updated_at', '>=', $request->query('updated_since'));
        }
        $limit = min(max((int) $request->query('limit', 100), 1), 500);
        $rows = $query->limit($limit)->get();

        return response()->json([
            'resource' => $resource,
            'count' => $rows->count(),
            'data' => $rows,
        ]);
    }
}
