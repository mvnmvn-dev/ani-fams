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
}
