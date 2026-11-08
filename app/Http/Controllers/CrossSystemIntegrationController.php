<?php

namespace App\Http\Controllers;

use App\Services\LegalCaseService;
use App\Services\VisitorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CrossSystemIntegrationController extends Controller
{
    public function vendorVisit(Request $request, VisitorService $visitors): JsonResponse
    {
        $source = $this->clientSource($request);
        if ($source !== 'scm') {
            return response()->json(['message' => 'This route requires the SCM integration client.'], 403);
        }
        $data = $request->validate([
            'supplier_external_id' => ['required', 'string', 'max:100'],
            'visitor_external_id' => ['required', 'string', 'max:100'],
            'full_name' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'company' => ['nullable', 'string', 'max:150'],
            'host_employee_no' => ['required', 'string', 'max:50'],
            'interview_external_id' => ['required', 'string', 'max:100'],
            'scheduled_at' => ['required', 'date'],
            'purpose' => ['required', 'string', 'max:255'],
            'building_name' => ['required', 'string', 'max:150'],
            'floor_designation' => ['required', 'string', 'max:100'],
        ]);
        $hostId = DB::table('employees')->where('source_system', 'hr1')->where('employee_no', $data['host_employee_no'])->value('id');
        if (! $hostId) {
            return response()->json(['message' => 'Unknown host employee number. Synchronize HR1 employees first.'], 422);
        }
        $visitorRef = DB::table('external_refs')->where([
            'source_system' => 'scm', 'resource' => 'vendor', 'external_id' => $data['visitor_external_id'],
        ])->first();
        if ($visitorRef && $visitorRef->local_entity !== 'visitors') {
            return response()->json(['message' => 'SCM visitor reference points to an unexpected FAMS entity.'], 409);
        }
        $visitorId = $visitorRef ? (int) $visitorRef->local_id : (int) DB::table('visitors')->insertGetId([
            'full_name' => $data['full_name'], 'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null, 'company' => $data['company'] ?? null,
            'id_number' => null, 'is_watchlisted' => false, 'visitor_type' => 'vendor',
            'source_system' => 'scm', 'source_ref' => $data['visitor_external_id'], 'created_at' => now(),
        ]);
        DB::table('visitors')->where('id', $visitorId)->update([
            'full_name' => $data['full_name'], 'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null, 'company' => $data['company'] ?? null,
            'visitor_type' => 'vendor', 'source_system' => 'scm', 'source_ref' => $data['visitor_external_id'],
        ]);
        $visitRef = DB::table('external_refs')->where([
            'source_system' => 'scm', 'resource' => 'vendor_visit', 'external_id' => $data['interview_external_id'],
        ])->first();
        if ($visitRef && $visitRef->local_entity !== 'visits') {
            return response()->json(['message' => 'SCM visit reference points to an unexpected FAMS entity.'], 409);
        }
        if ($visitRef) {
            if (! DB::table('visits')->where('id', $visitRef->local_id)->exists()) {
                return response()->json(['message' => 'Referenced FAMS visit no longer exists.'], 404);
            }
            DB::table('visits')->where('id', $visitRef->local_id)->update([
                'host_employee_id' => (int) $hostId, 'purpose' => $data['purpose'],
                'building_name' => $data['building_name'], 'floor_designation' => $data['floor_designation'],
                'scheduled_at' => $data['scheduled_at'],
            ]);
            $visitId = (int) $visitRef->local_id;
            $state = 'updated';
        } else {
            $result = $visitors->preRegister([
                'visitor_id' => $visitorId, 'full_name' => $data['full_name'],
                'id_number' => '', 'email' => $data['email'] ?? '', 'phone' => $data['phone'] ?? '',
                'company' => $data['company'] ?? '', 'host_employee_id' => (int) $hostId,
                'purpose' => $data['purpose'], 'building_name' => $data['building_name'],
                'floor_designation' => $data['floor_designation'], 'scheduled_at' => $data['scheduled_at'],
            ]);
            if (empty($result['id'])) {
                return response()->json(['message' => $result['error'] ?? 'Visitor registration failed.'], 422);
            }
            $visitId = (int) $result['id'];
            DB::table('visits')->where('id', $visitId)->update([
                'visit_category' => 'general', 'source_system' => 'scm', 'source_ref' => $data['interview_external_id'],
            ]);
            $state = DB::table('visits')->where('id', $visitId)->value('status') === 'denied' ? 'denied' : 'created';
        }
        $this->ref('scm', 'vendor', $data['visitor_external_id'], 'visitors', $visitorId, $request->all());
        $this->ref('scm', 'vendor_visit', $data['interview_external_id'], 'visits', $visitId, $request->all());
        $this->log('scm', 'vendor_visit', $data['interview_external_id'], $state === 'denied' ? 'rejected' : 'ok');
        return response()->json(['success' => true, 'visitor_id' => $visitorId, 'visit_id' => $visitId, 'status' => $state], $state === 'created' ? 201 : 200);
    }

    public function vehicle(string $system, Request $request): JsonResponse
    {
        $source = $this->clientSource($request);
        if (! in_array($system, ['fvm', 'vrds'], true) || $source !== $system) {
            return response()->json(['message' => 'The matching FVM or VRDS client is required.'], 403);
        }
        $data = $request->validate([
            'external_id' => ['required', 'string', 'max:100'],
            'visit_source_system' => ['required', 'in:hr1,scm'],
            'visit_external_id' => ['required', 'string', 'max:100'],
            'plate_number' => ['required', 'string', 'max:50'],
            'vehicle_make' => ['nullable', 'string', 'max:100'],
            'vehicle_model' => ['nullable', 'string', 'max:100'],
            'vehicle_color' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);
        $visitResource = $data['visit_source_system'] === 'hr1' ? 'interview' : 'vendor_visit';
        $visitRef = DB::table('external_refs')->where([
            'source_system' => $data['visit_source_system'], 'resource' => $visitResource, 'external_id' => $data['visit_external_id'],
        ])->first();
        if (! $visitRef || $visitRef->local_entity !== 'visits') {
            return response()->json(['message' => 'Visit reference was not found in FAMS.'], 422);
        }
        $visit = DB::table('visits')->where('id', $visitRef->local_id)->first();
        if (! $visit) {
            return response()->json(['message' => 'Visit no longer exists.'], 404);
        }
        $existing = DB::table('external_refs')->where([
            'source_system' => $system, 'resource' => 'visitor_vehicle', 'external_id' => $data['external_id'],
        ])->first();
        $vehicleValues = [
            'visitor_id' => $visit->visitor_id, 'visit_id' => $visit->id,
            'plate_number' => strtoupper(trim($data['plate_number'])), 'vehicle_make' => $data['vehicle_make'] ?? null,
            'vehicle_model' => $data['vehicle_model'] ?? null, 'vehicle_color' => $data['vehicle_color'] ?? null,
            'notes' => $data['notes'] ?? null,
        ];
        if ($existing) {
            DB::table('visitor_vehicles')->where('id', $existing->local_id)->update($vehicleValues);
            $vehicleId = (int) $existing->local_id;
            $status = 'updated';
        } else {
            $vehicleId = (int) DB::table('visitor_vehicles')->insertGetId($vehicleValues + ['created_at' => now()]);
            $status = 'created';
        }
        $this->ref($system, 'visitor_vehicle', $data['external_id'], 'visitor_vehicles', $vehicleId, $request->all());
        $this->log($system, 'visitor_vehicle', $data['external_id'], 'ok');
        return response()->json(['success' => true, 'vehicle_id' => $vehicleId, 'visit_id' => (int) $visit->id, 'status' => $status], $status === 'created' ? 201 : 200);
    }

    public function availability(string $system, Request $request): JsonResponse
    {
        $source = $this->clientSource($request);
        if (! in_array($system, ['fvm', 'vrds', 'sws', 'hr2'], true) || $source !== $system) {
            return response()->json(['message' => 'The matching Fleet, SWS, or HR2 client is required.'], 403);
        }
        $data = $request->validate([
            'external_id' => ['required', 'string', 'max:100'],
            'resource_type' => ['required', 'string', 'max:50'],
            'resource_name' => ['required', 'string', 'max:150'],
            'start_datetime' => ['required', 'date'], 'end_datetime' => ['required', 'date', 'after:start_datetime'],
            'availability' => ['required', 'in:available,unavailable'],
            'employee_no' => ['nullable', 'string', 'max:50'],
            'reason_code' => ['nullable', 'string', 'max:50'],
        ]);
        if ($system === 'hr2' && ! DB::table('employees')->where('source_system', 'hr1')->where('employee_no', $data['employee_no'] ?? '')->exists()) {
            return response()->json(['message' => 'HR2 availability requires a known HR1 employee_no.'], 422);
        }
        DB::table('integration_resource_availability')->updateOrInsert(
            ['source_system' => $system, 'external_id' => $data['external_id']],
            [
                'resource_type' => $data['resource_type'], 'resource_name' => $data['resource_name'],
                'employee_no' => $data['employee_no'] ?? null, 'reason_code' => $data['reason_code'] ?? null,
                'starts_at' => $data['start_datetime'], 'ends_at' => $data['end_datetime'],
                'is_available' => $data['availability'] === 'available', 'payload' => json_encode($request->all()),
                'updated_at' => now(), 'created_at' => now(),
            ]
        );
        $this->log($system, 'availability', $data['external_id'], 'ok');
        return response()->json(['success' => true, 'status' => 'accepted', 'note' => 'Availability is stored as a snapshot; FAMS reservation conflicts are not automatically blocked until resource mappings are agreed.'], 200);
    }

    public function contract(string $system, Request $request): JsonResponse
    {
        $source = $this->clientSource($request);
        if (! in_array($system, ['scm', 'hr1', 'subscriptions'], true) || $source !== $system) {
            return response()->json(['message' => 'The matching SCM, HR1, or subscriptions client is required.'], 403);
        }
        $data = $request->validate([
            'external_id' => ['required', 'string', 'max:100'], 'contract_type' => ['required', 'string', 'max:50'],
            'title' => ['required', 'string', 'max:255'], 'counterparty_name' => ['required', 'string', 'max:255'],
            'counterparty_contact' => ['nullable', 'string', 'max:255'],
            'purchase_order_ref' => ['nullable', 'string', 'max:100'],
            'value_amount' => ['nullable', 'numeric', 'min:0'], 'currency' => ['nullable', 'string', 'size:3'],
            'start_date' => ['required', 'date'], 'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);
        $ref = DB::table('external_refs')->where(['source_system' => $system, 'resource' => 'contract', 'external_id' => $data['external_id']])->first();
        $ownerId = DB::table('users')->where('email', config('integration.system_user_email'))->value('id');
        if (! $ownerId) {
            return response()->json(['message' => 'Integration service account is missing; run migrations first.'], 500);
        }
        $values = [
            'title' => $data['title'], 'counterparty_name' => $data['counterparty_name'],
            'counterparty_contact' => $data['counterparty_contact'] ?? null,
            'external_contract_type' => $data['contract_type'], 'purchase_order_ref' => $data['purchase_order_ref'] ?? null,
            'value_amount' => $data['value_amount'] ?? null, 'currency' => strtoupper($data['currency'] ?? 'PHP'),
            'start_date' => $data['start_date'], 'end_date' => $data['end_date'] ?? null,
        ];
        if ($ref && $ref->local_entity !== 'contracts') {
            return response()->json(['message' => 'Contract reference points to an unexpected FAMS entity.'], 409);
        }
        if ($ref) {
            if (! DB::table('contracts')->where('id', $ref->local_id)->exists()) {
                return response()->json(['message' => 'Referenced FAMS contract no longer exists.'], 404);
            }
            DB::table('contracts')->where('id', $ref->local_id)->update($values);
            $contractId = (int) $ref->local_id;
            $status = 'updated';
        } else {
            $number = 'EXT-' . strtoupper(substr(hash('sha256', $system . ':' . $data['external_id']), 0, 16));
            $contractId = (int) DB::table('contracts')->insertGetId(array_merge($values, [
                'contract_number' => $number, 'owner_id' => $ownerId, 'status' => 'draft', 'auto_renew' => false,
                'renewal_notice_days' => 30, 'created_at' => now(),
            ]));
            $status = 'created';
        }
        $this->ref($system, 'contract', $data['external_id'], 'contracts', $contractId, $request->all());
        $contractStatus = (string) DB::table('contracts')->where('id', $contractId)->value('status');
        $this->log($system, 'contract', $data['external_id'], 'ok');
        return response()->json(['success' => true, 'contract_id' => $contractId, 'status' => $status, 'contract_status' => $contractStatus], $status === 'created' ? 201 : 200);
    }

    public function dispute(string $system, Request $request, LegalCaseService $legal): JsonResponse
    {
        $source = $this->clientSource($request);
        if (! in_array($system, ['ecom', 'support'], true) || $source !== $system) {
            return response()->json(['message' => 'The matching ecommerce or support client is required.'], 403);
        }
        $data = $request->validate([
            'external_id' => ['required', 'string', 'max:100'], 'origin_system' => ['required', 'string', 'max:50'],
            'dispute_type' => ['required', 'string', 'max:50'], 'summary' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'], 'opposing_party' => ['nullable', 'string', 'max:255'],
            'filed_date' => ['nullable', 'date'],
        ]);
        $existing = DB::table('external_refs')->where(['source_system' => $system, 'resource' => 'dispute', 'external_id' => $data['external_id']])->first();
        if ($existing) {
            return response()->json(['success' => true, 'legal_case_id' => (int) $existing->local_id, 'status' => 'duplicate'], 200);
        }
        $created = $legal->createCase([
            'title' => $data['summary'], 'case_type' => 'other', 'status' => 'open',
            'filed_date' => $data['filed_date'] ?? now()->toDateString(),
            'opposing_party' => $data['opposing_party'] ?? $data['origin_system'],
            'risk_level' => 'medium', 'description' => $data['description'] ?? $data['dispute_type'],
        ]);
        if (! ($created['success'] ?? false)) {
            return response()->json(['message' => $created['error'] ?? 'Legal referral failed.'], 422);
        }
        $this->ref($system, 'dispute', $data['external_id'], 'legal_cases', (int) $created['id'], $request->all());
        $this->log($system, 'dispute', $data['external_id'], 'ok');
        return response()->json(['success' => true, 'legal_case_id' => (int) $created['id'], 'status' => 'open'], 201);
    }

    public function documentTracking(Request $request): JsonResponse
    {
        $source = $this->clientSource($request);
        if ($source !== 'dtrs') {
            return response()->json(['message' => 'This route requires the DTRS integration client.'], 403);
        }
        $data = $request->validate([
            'document_source_system' => ['required', 'in:hr1,hr2,hr3,hr4,fin,ecom'],
            'document_external_id' => ['required', 'string', 'max:100'],
            'tracking_external_id' => ['required', 'string', 'max:100'],
            'status' => ['required', 'in:received,routed,filed,returned'],
            'updated_at' => ['nullable', 'date'],
        ]);
        $docRef = DB::table('external_refs')->where(['source_system' => $data['document_source_system'], 'resource' => 'document', 'external_id' => $data['document_external_id']])->first();
        if (! $docRef || $docRef->local_entity !== 'documents') {
            return response()->json(['message' => 'Document reference was not found in FAMS.'], 422);
        }
        DB::table('document_tracking_refs')->updateOrInsert(
            ['source_system' => 'dtrs', 'tracking_external_id' => $data['tracking_external_id']],
            ['document_id' => $docRef->local_id, 'status' => $data['status'], 'source_updated_at' => $data['updated_at'] ?? now(), 'updated_at' => now(), 'created_at' => now()]
        );
        $this->log('dtrs', 'document_tracking', $data['tracking_external_id'], 'ok');
        return response()->json(['success' => true, 'document_id' => (int) $docRef->local_id, 'tracking_external_id' => $data['tracking_external_id']], 200);
    }

    private function clientSource(Request $request): string
    {
        return (string) ($request->attributes->get('integration_client')?->system_code ?? '');
    }

    private function ref(string $source, string $resource, string $externalId, string $entity, int $localId, array $payload): void
    {
        DB::table('external_refs')->updateOrInsert(
            ['source_system' => $source, 'resource' => $resource, 'external_id' => $externalId],
            ['local_entity' => $entity, 'local_id' => $localId, 'payload_hash' => hash('sha256', json_encode($payload)), 'last_synced_at' => now()]
        );
    }

    private function log(string $source, string $resource, ?string $externalId, string $status): void
    {
        DB::table('integration_log')->insert([
            'source_system' => $source, 'direction' => 'in', 'resource' => $resource,
            'external_id' => $externalId, 'status' => $status, 'created_at' => now(),
        ]);
    }
}
