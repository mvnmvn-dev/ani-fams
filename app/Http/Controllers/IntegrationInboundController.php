<?php

namespace App\Http\Controllers;

use App\Services\DocumentService;
use App\Services\RetentionService;
use App\Services\VisitorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class IntegrationInboundController extends Controller
{
    public function employees(Request $request): JsonResponse
    {
        $payload = $request->all();
        $items = $payload['employees'] ?? [$payload];
        if (! is_array($items) || $items === []) {
            return response()->json(['message' => 'employees must be a non-empty array.'], 422);
        }

        $source = $this->source($request, 'hr1');
        if ($source !== 'hr1') {
            return response()->json(['message' => 'Only the HR1 integration client may synchronize employees.'], 403);
        }
        $saved = [];
        foreach ($items as $item) {
            $validator = Validator::make($item, [
                'employee_no' => ['required', 'string', 'max:50'],
                'full_name' => ['required', 'string', 'max:150'],
                'email' => ['nullable', 'email', 'max:150'],
                'department' => ['nullable', 'string', 'max:150'],
                'department_id' => ['nullable', 'integer'],
                'position' => ['nullable', 'string', 'max:150'],
                'phone' => ['nullable', 'string', 'max:30'],
                'status' => ['nullable', 'string', 'max:30'],
            ]);
            if ($validator->fails()) {
                return response()->json(['message' => 'Employee validation failed.', 'errors' => $validator->errors()], 422);
            }

            $departmentId = $item['department_id'] ?? null;
            if (! $departmentId && ! empty($item['department'])) {
                $departmentId = DB::table('departments')->where('name', $item['department'])->value('id');
                if (! $departmentId) {
                    $departmentId = DB::table('departments')->insertGetId(['name' => $item['department']]);
                }
            }
            $userId = ! empty($item['email']) ? DB::table('users')->where('email', $item['email'])->value('id') : null;
            $values = [
                'user_id' => $userId,
                'department_id' => $departmentId,
                'full_name' => $item['full_name'],
                'position' => $item['position'] ?? null,
                'email' => $item['email'] ?? null,
                'phone' => $item['phone'] ?? null,
                'source_system' => $source,
                'employee_no' => $item['employee_no'],
            ];
            $employee = DB::table('employees')->where('source_system', $source)->where('employee_no', $item['employee_no'])->first();
            if ($employee) {
                DB::table('employees')->where('id', $employee->id)->update($values);
                $id = (int) $employee->id;
                $status = 'updated';
            } else {
                $id = (int) DB::table('employees')->insertGetId($values);
                $status = 'created';
            }
            $saved[] = ['id' => $id, 'employee_no' => $item['employee_no'], 'status' => $status];
        }
        $this->log($source, 'employees', null, 'ok');
        return response()->json(['success' => true, 'data' => $saved], 200);
    }

    public function interviews(Request $request, VisitorService $visitorService): JsonResponse
    {
        $data = $request->validate([
            'applicant.external_id' => ['required', 'string', 'max:100'],
            'applicant.full_name' => ['required', 'string', 'max:150'],
            'applicant.email' => ['nullable', 'email', 'max:150'],
            'applicant.phone' => ['nullable', 'string', 'max:30'],
            'applicant.position_applied' => ['nullable', 'string', 'max:150'],
            'applicant.id_number' => ['nullable', 'string', 'max:100'],
            'interview.external_id' => ['required', 'string', 'max:100'],
            'interview.scheduled_at' => ['required', 'date'],
            'interview.host_employee_no' => ['required', 'string', 'max:50'],
            'interview.purpose' => ['required', 'string', 'max:255'],
            'interview.building_name' => ['required', 'string', 'max:150'],
            'interview.floor_designation' => ['required', 'string', 'max:100'],
            'interview.notes' => ['nullable', 'string'],
        ]);
        $source = $this->source($request, 'hr1');
        if ($source !== 'hr1') {
            return response()->json(['message' => 'Only the HR1 integration client may submit interviews.'], 403);
        }
        $applicant = $data['applicant'];
        $interview = $data['interview'];
        $hostId = DB::table('employees')->where('employee_no', $interview['host_employee_no'])
            ->where('source_system', 'hr1')->value('id');
        if (! $hostId) {
            $this->log($source, 'interview', $interview['external_id'], 'rejected', 'Unknown HR1 host employee_no.');
            return response()->json(['message' => 'Unknown host employee_no.'], 422);
        }

        $existingRef = DB::table('external_refs')->where([
            'source_system' => $source, 'resource' => 'interview', 'external_id' => $interview['external_id'],
        ])->first();
        if ($existingRef) {
            DB::table('visits')->where('id', $existingRef->local_id)->update([
                'scheduled_at' => $interview['scheduled_at'],
                'host_employee_id' => (int) $hostId,
                'purpose' => $interview['purpose'],
                'building_name' => $interview['building_name'],
                'floor_designation' => $interview['floor_designation'],
                'notes' => $interview['notes'] ?? null,
            ]);
            $this->log($source, 'interview', $interview['external_id'], 'duplicate');
            return response()->json(['success' => true, 'visit_id' => (int) $existingRef->local_id, 'status' => 'updated'], 200);
        }

        $visitorRef = DB::table('external_refs')->where([
            'source_system' => $source, 'resource' => 'applicant', 'external_id' => $applicant['external_id'],
        ])->first();
        $visitorId = $visitorRef?->local_id;
        if (! $visitorId && ! empty($applicant['email'])) {
            $visitorId = DB::table('visitors')->where('email', $applicant['email'])->value('id');
        }
        if ($visitorId) {
            DB::table('visitors')->where('id', $visitorId)->update([
                'full_name' => $applicant['full_name'], 'email' => $applicant['email'] ?? null,
                'phone' => $applicant['phone'] ?? null, 'id_number' => $applicant['id_number'] ?? null,
                'visitor_type' => 'applicant', 'source_system' => $source, 'source_ref' => $applicant['external_id'],
            ]);
        } else {
            $visitorId = DB::table('visitors')->insertGetId([
                'full_name' => $applicant['full_name'], 'email' => $applicant['email'] ?? null,
                'phone' => $applicant['phone'] ?? null, 'id_number' => $applicant['id_number'] ?? null,
                'visitor_type' => 'applicant', 'source_system' => $source, 'source_ref' => $applicant['external_id'],
                'is_watchlisted' => false, 'created_at' => now(),
            ]);
        }

        $result = $visitorService->preRegister([
            'visitor_id' => (int) $visitorId,
            'full_name' => $applicant['full_name'],
            'id_number' => $applicant['id_number'] ?? '',
            'email' => $applicant['email'] ?? '',
            'phone' => $applicant['phone'] ?? '',
            'host_employee_id' => (int) $hostId,
            'purpose' => $interview['purpose'],
            'building_name' => $interview['building_name'],
            'floor_designation' => $interview['floor_designation'],
            'scheduled_at' => $interview['scheduled_at'],
            'notes' => $interview['notes'] ?? '',
        ]);
        if (! ($result['success'] ?? false) && empty($result['id'])) {
            $this->log($source, 'interview', $interview['external_id'], 'rejected', $result['error'] ?? 'Visit rejected.');
            return response()->json($result, 422);
        }
        $visitId = (int) ($result['id'] ?? 0);
        DB::table('visits')->where('id', $visitId)->update([
            'visit_category' => 'interview', 'source_system' => $source, 'source_ref' => $interview['external_id'],
        ]);
        $this->upsertRef($source, 'applicant', $applicant['external_id'], 'visitors', (int) $visitorId, $request->all());
        $this->upsertRef($source, 'interview', $interview['external_id'], 'visits', $visitId, $request->all());
        $this->log($source, 'interview', $interview['external_id'], ($result['success'] ?? false) ? 'ok' : 'rejected');
        return response()->json(['success' => true, 'visit_id' => $visitId, 'visitor_id' => (int) $visitorId, 'status' => $result['status'] ?? 'pending_approval'], 201);
    }

    public function documents(string $system, Request $request, DocumentService $documentService, RetentionService $retentionService): JsonResponse
    {
        $clientSource = $this->source($request, '');
        if ($clientSource !== $system) {
            return response()->json(['message' => 'The authenticated integration client does not match the requested source system.'], 403);
        }
        $data = $request->validate([
            'external_id' => ['required', 'string', 'max:100'],
            'doc_type' => ['required', 'string', 'max:50'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'employee_no' => ['nullable', 'string', 'max:50'],
            'period_start' => ['nullable', 'date'],
            'period_end' => ['nullable', 'date'],
            'checksum_sha256' => ['required', 'string', 'size:64'],
            'file' => ['required', 'file', 'max:51200'],
        ]);
        if ($system === 'hr4' && in_array($data['doc_type'], ['payroll_register', 'payslip_batch', 'remittance_report', 'bir_2316'], true) && empty($data['period_end'])) {
            return response()->json(['message' => 'period_end is required for payroll records.'], 422);
        }
        $rule = DB::table('retention_source_rules')->where(['source_system' => $system, 'doc_type' => $data['doc_type'], 'is_active' => true])->first();
        if (! $rule) {
            return response()->json(['message' => 'No active retention source rule exists for this document type.'], 422);
        }
        $sourceRef = DB::table('external_refs')->where(['source_system' => $system, 'resource' => 'document', 'external_id' => $data['external_id']])->first();
        $file = $request->file('file');
        $actualChecksum = strtolower(hash_file('sha256', $file->getRealPath()));
        if (! hash_equals(strtolower($data['checksum_sha256']), $actualChecksum)) {
            $this->log($system, 'document', $data['external_id'], 'rejected', 'Checksum mismatch.');
            return response()->json(['message' => 'Checksum mismatch.'], 422);
        }
        $fileInput = ['name' => $file->getClientOriginalName(), 'type' => $file->getMimeType(), 'tmp_name' => $file->getRealPath(), 'error' => $file->getError(), 'size' => $file->getSize()];
        if ($sourceRef) {
            $currentChecksum = DB::table('document_versions')->where('document_id', $sourceRef->local_id)
                ->orderByDesc('version_no')->value('checksum');
            if ($currentChecksum && hash_equals(strtolower($currentChecksum), $actualChecksum)) {
                DB::table('documents')->where('id', $sourceRef->local_id)->update([
                    'title' => $data['title'], 'description' => $data['description'] ?? '',
                    'source_doc_type' => $data['doc_type'], 'employee_no' => $data['employee_no'] ?? null,
                    'period_start' => $data['period_start'] ?? null, 'period_end' => $data['period_end'] ?? null,
                ]);
                $this->upsertRef($system, 'document', $data['external_id'], 'documents', (int) $sourceRef->local_id, $request->all());
                $this->log($system, 'document', $data['external_id'], 'duplicate');
                return response()->json(['success' => true, 'document_id' => (int) $sourceRef->local_id, 'status' => 'duplicate'], 200);
            }
            $result = $documentService->uploadNewVersion((int) $sourceRef->local_id, [
                'change_notes' => 'Updated by ' . strtoupper($system) . ' integration.',
            ], $fileInput);
            if (! ($result['success'] ?? false)) {
                $this->log($system, 'document', $data['external_id'], 'rejected', $result['error'] ?? 'Version upload failed.');
                return response()->json($result, 422);
            }
            $documentId = (int) $sourceRef->local_id;
            DB::table('documents')->where('id', $documentId)->update([
                'title' => $data['title'], 'description' => $data['description'] ?? '',
                'employee_no' => $data['employee_no'] ?? null, 'period_start' => $data['period_start'] ?? null,
                'period_end' => $data['period_end'] ?? null,
            ]);
            $this->upsertRef($system, 'document', $data['external_id'], 'documents', $documentId, $request->all());
            $this->log($system, 'document', $data['external_id'], 'ok');
            return response()->json(['success' => true, 'document_id' => $documentId, 'status' => 'updated'], 200);
        }
        $result = $documentService->uploadDocument([
            'title' => $data['title'], 'description' => $data['description'] ?? '',
            'category_id' => $rule->category_id, 'confidentiality' => $rule->confidentiality,
        ], $fileInput);
        if (! ($result['success'] ?? false)) {
            $this->log($system, 'document', $data['external_id'], 'rejected', $result['error'] ?? 'Upload failed.');
            return response()->json($result, 422);
        }
        $documentId = (int) $result['id'];
        DB::table('documents')->where('id', $documentId)->update([
            'source_system' => $system, 'source_ref' => $data['external_id'], 'source_doc_type' => $data['doc_type'],
            'employee_no' => $data['employee_no'] ?? null, 'period_start' => $data['period_start'] ?? null,
            'period_end' => $data['period_end'] ?? null,
        ]);
        $startDate = $data['period_end'] ?? now()->toDateString();
        $assigned = $retentionService->assignPolicy(['document_id' => $documentId, 'policy_id' => $rule->policy_id, 'retention_start_date' => $startDate]);
        if (! ($assigned['success'] ?? false)) {
            $this->log($system, 'document', $data['external_id'], 'error', $assigned['error'] ?? 'Retention assignment failed.');
            return response()->json(['message' => $assigned['error'] ?? 'Retention assignment failed.'], 500);
        }
        $this->upsertRef($system, 'document', $data['external_id'], 'documents', $documentId, $request->all());
        $this->log($system, 'document', $data['external_id'], 'ok');
        return response()->json(['success' => true, 'document_id' => $documentId, 'retention' => $assigned], 201);
    }

    public function events(string $system, Request $request): JsonResponse
    {
        $data = $request->validate(['type' => ['required', 'string'], 'employee_no' => ['required', 'string', 'max:50'], 'separation_date' => ['required_if:type,employee.separated', 'date']]);
        if ($data['type'] !== 'employee.separated') {
            return response()->json(['message' => 'Unsupported HR event type.'], 422);
        }
        $source = $this->source($request, '');
        if (! in_array($system, ['hr1', 'hr2'], true) || $source !== $system) {
            return response()->json(['message' => 'Only the matching HR1 or HR2 integration client may submit employee events.'], 403);
        }
        $documents = DB::table('documents')->where('employee_no', $data['employee_no'])
            ->whereIn('source_system', ['hr1', 'hr2', 'hr3', 'hr4'])->pluck('id');
        $updated = 0;
        $employeeName = DB::table('employees')->where('employee_no', $data['employee_no'])->value('full_name') ?? $data['employee_no'];
        foreach ($documents as $documentId) {
            $assignments = DB::table('retention_assignments as ra')
                ->join('retention_policies as rp', 'rp.id', '=', 'ra.policy_id')
                ->where('ra.document_id', $documentId)->where('ra.status', '!=', 'disposed')
                ->get(['ra.id', 'rp.retention_period_months']);
            foreach ($assignments as $assignment) {
                $disposalDate = date('Y-m-d', strtotime('+' . (int) $assignment->retention_period_months . ' months', strtotime($data['separation_date'])));
                $updated += DB::table('retention_assignments')->where('id', $assignment->id)->update([
                    'retention_start_date' => $data['separation_date'], 'calculated_disposal_date' => $disposalDate,
                ]);
            }
            DB::table('legal_employee_departures')->insertOrIgnore([
                'employee_name' => $employeeName, 'departure_type' => 'resigned',
                'effective_date' => $data['separation_date'], 'source_document_id' => $documentId,
                'recorded_by' => auth()->id(), 'created_at' => now(),
            ]);
        }
        if ($documents->isEmpty()) {
            DB::table('legal_employee_departures')->insertOrIgnore([
                'employee_name' => $employeeName, 'departure_type' => 'resigned',
                'effective_date' => $data['separation_date'], 'source_document_id' => null,
                'recorded_by' => auth()->id(), 'created_at' => now(),
            ]);
        }
        $this->log($source, 'employee.separated', $data['employee_no'], 'ok');
        return response()->json(['success' => true, 'employee_no' => $data['employee_no'], 'documents_updated' => $updated], 200);
    }

    private function source(Request $request, string $fallback): string
    {
        return (string) ($request->attributes->get('integration_client')?->system_code ?? $fallback);
    }

    private function upsertRef(string $source, string $resource, string $externalId, string $entity, int $localId, array $payload): void
    {
        DB::table('external_refs')->updateOrInsert(
            ['source_system' => $source, 'resource' => $resource, 'external_id' => $externalId],
            ['local_entity' => $entity, 'local_id' => $localId, 'payload_hash' => hash('sha256', json_encode($payload)), 'last_synced_at' => now()]
        );
    }

    private function log(string $source, string $resource, ?string $externalId, string $status, ?string $error = null): void
    {
        DB::table('integration_log')->insert(['source_system' => $source, 'direction' => 'in', 'resource' => $resource, 'external_id' => $externalId, 'status' => $status, 'error' => $error, 'created_at' => now()]);
    }
}
