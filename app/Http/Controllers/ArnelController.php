<?php

namespace App\Http\Controllers;

use App\Services\ArnelAiService;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ArnelController extends Controller
{
    public function analyzeDocument(Request $request, ArnelAiService $arnel): JsonResponse
    {
        if (! Auth::user()?->canPermission('document.view')) return response()->json(['message' => 'Document permission required.'], 403);
        $data = $request->validate(['document_id' => ['nullable', 'integer'], 'text' => ['nullable', 'string', 'max:100000'], 'filename' => ['nullable', 'string', 'max:255'], 'title' => ['nullable', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:5000']]);
        $document = ! empty($data['document_id']) ? DB::table('documents')->whereNull('deleted_at')->find((int) $data['document_id']) : null;
        if ($data['document_id'] && ! $document) return response()->json(['message' => 'Document not found.'], 404);
        $version = $document && $document->current_version_id ? DB::table('document_versions')->find($document->current_version_id) : null;
        $analysis = $arnel->analyzeDocument((string) ($data['text'] ?? $version->content_text ?? ''), (string) ($data['filename'] ?? ''), (string) ($data['title'] ?? $document->title ?? ''), (string) ($data['description'] ?? $document->description ?? ''));
        if (! $analysis) return response()->json(['message' => 'No analyzable document content supplied.'], 422);
        if ($document && Auth::user()?->canPermission('document.manage')) {
            DB::table('documents')->where('id', $document->id)->update(['ai_category' => $analysis['classification'], 'ai_classification' => $analysis['classification'], 'ai_summary' => $analysis['summary'], 'ai_tags' => json_encode($analysis['keywords']), 'ai_keywords' => json_encode($analysis['keywords']), 'ai_metadata' => json_encode($analysis['metadata']), 'ai_confidence' => $analysis['confidence'], 'ai_processed_at' => now()]);
        }
        AuditLogger::log('arnel', 'document_analysis', 'document', $document->id ?? 0, null, ['persisted' => (bool) ($document && Auth::user()?->canPermission('document.manage')), 'category' => $analysis['classification'], 'confidence' => $analysis['confidence']]);
        return response()->json(['success' => true, 'persisted' => (bool) ($document && Auth::user()?->canPermission('document.manage')), 'data' => ['summary' => $analysis['summary'], 'category' => $analysis['classification'], 'tags' => $analysis['keywords'], 'metadata' => $analysis['metadata'], 'confidence' => $analysis['confidence']]]);
    }

    public function analyzeReservation(Request $request, ArnelAiService $arnel): JsonResponse
    {
        if (! Auth::user()?->canPermission('reservation.view')) return response()->json(['message' => 'Reservation permission required.'], 403);
        $data = $request->validate(['reservation_id' => ['nullable', 'integer'], 'facility_id' => ['nullable', 'integer'], 'facility_name' => ['nullable', 'string', 'max:150'], 'start_datetime' => ['nullable', 'date'], 'end_datetime' => ['nullable', 'date', 'after:start_datetime']]);
        $reservation = ! empty($data['reservation_id']) ? DB::table('reservations')->find((int) $data['reservation_id']) : null;
        if ($data['reservation_id'] && ! $reservation) return response()->json(['message' => 'Reservation not found.'], 404);
        $facilityId = (int) ($data['facility_id'] ?? $reservation->facility_id ?? 0);
        if (! $facilityId && ! empty($data['facility_name'])) $facilityId = (int) (DB::table('facilities')->where('name', $data['facility_name'])->value('id') ?? 0);
        $start = $data['start_datetime'] ?? $reservation->start_datetime ?? null; $end = $data['end_datetime'] ?? $reservation->end_datetime ?? null;
        if (! $facilityId || ! $start || ! $end) return response()->json(['message' => 'facility_id, start_datetime, and end_datetime are required.'], 422);
        $conflict = DB::table('reservations')->where('facility_id', $facilityId)->whereIn('status', ['pending', 'approved'])->where('start_datetime', '<', $end)->where('end_datetime', '>', $start)->when($reservation, fn ($q) => $q->where('id', '!=', $reservation->id))->exists();
        $suggested = DB::table('facilities')->where('status', 'available')->where('id', '!=', $facilityId)->orderBy('name')->first();
        $recommendation = $arnel->reservationRecommendation(['facility_id' => $facilityId, 'start_datetime' => $start, 'end_datetime' => $end], $conflict, $suggested->name ?? null);
        $result = ['conflict_status' => $conflict ? 'conflict_detected' : 'clear', 'suggested_facility' => $conflict ? ($suggested->name ?? null) : null, 'suggested_schedule' => ['start_datetime' => $start, 'end_datetime' => $end], 'ai_suggestion' => $recommendation['recommendation'], 'ai_rationale' => $recommendation['rationale']];
        if ($reservation && Auth::user()?->canPermission('reservation.approve')) DB::table('reservations')->where('id', $reservation->id)->update(['conflict_status' => $result['conflict_status'], 'ai_suggestion' => $result['ai_suggestion'], 'ai_notes' => json_encode($result)]);
        AuditLogger::log('arnel', 'reservation_analysis', 'reservation', $reservation->id ?? 0, null, ['persisted' => (bool) ($reservation && Auth::user()?->canPermission('reservation.approve')), 'conflict_status' => $result['conflict_status']]);
        return response()->json(['success' => true, 'persisted' => (bool) ($reservation && Auth::user()?->canPermission('reservation.approve')), 'data' => $result]);
    }

    public function analyzeContract(Request $request, ArnelAiService $arnel): JsonResponse
    {
        if (! Auth::user()?->canPermission('contract.view')) return response()->json(['message' => 'Contract permission required.'], 403);
        $data = $request->validate(['contract_id' => ['required', 'integer']]); $contract = DB::table('contracts')->whereNull('deleted_at')->find($data['contract_id']);
        if (! $contract) return response()->json(['message' => 'Contract not found.'], 404);
        $insights = $arnel->contractInsights([(array) $contract]); $item = $insights['expiring'][0] ?? null; $alert = $item ? $item['priority'] : 'none';
        $summary = $insights['summary'] . ' Contract: ' . $contract->title . ' between ANI and ' . $contract->counterparty_name . '.';
        if (Auth::user()?->canPermission('contract.manage')) DB::table('contracts')->where('id', $contract->id)->update(['ai_summary' => $summary, 'expiration_alert' => $alert]);
        AuditLogger::log('arnel', 'contract_analysis', 'contract', $contract->id, null, ['persisted' => Auth::user()?->canPermission('contract.manage'), 'expiration_alert' => $alert]);
        return response()->json(['success' => true, 'data' => ['summary' => $summary, 'expiration' => $item, 'parties' => ['ANI', $contract->counterparty_name], 'contract_value' => $contract->value_amount, 'effective_date' => $contract->start_date, 'expiration_date' => $contract->end_date]]);
    }

    public function analyzeLegalCase(Request $request, ArnelAiService $arnel): JsonResponse
    {
        if (! Auth::user()?->canPermission('legal.view')) return response()->json(['message' => 'Legal permission required.'], 403);
        $data = $request->validate(['legal_case_id' => ['required', 'integer']]); $case = DB::table('legal_cases')->whereNull('deleted_at')->find($data['legal_case_id']);
        if (! $case) return response()->json(['message' => 'Legal case not found.'], 404);
        $insights = $arnel->legalCaseInsights([(array) $case]); $deadline = $insights['deadlines'][0]['date'] ?? null; $summary = $insights['summary'] . ' Case: ' . $case->title . '.';
        if (Auth::user()?->canPermission('legal.manage')) DB::table('legal_cases')->where('id', $case->id)->update(['ai_summary' => $summary, 'next_deadline' => $deadline]);
        AuditLogger::log('arnel', 'legal_case_analysis', 'legal_case', $case->id, null, ['persisted' => Auth::user()?->canPermission('legal.manage'), 'next_deadline' => $deadline]);
        return response()->json(['success' => true, 'data' => ['summary' => $summary, 'important_dates' => $insights['deadlines'], 'related_documents' => $insights['related_documents'], 'next_deadline' => $deadline]]);
    }

    public function analyzeRetention(Request $request, ArnelAiService $arnel): JsonResponse
    {
        if (! Auth::user()?->canPermission('retention.view')) return response()->json(['message' => 'Retention permission required.'], 403);
        $data = $request->validate(['assignment_id' => ['required', 'integer']]); $assignment = DB::table('retention_assignments')->where('id', $data['assignment_id'])->first();
        if (! $assignment) return response()->json(['message' => 'Retention assignment not found.'], 404);
        $doc = DB::table('documents')->find($assignment->document_id); $insights = $arnel->retentionInsights([(array) $assignment], $doc ? [(array) $doc] : []); $category = $insights['suggestions'][0]['category'] ?? null; $archive = in_array($assignment->status, ['due_for_review'], true) || strtotime($assignment->calculated_disposal_date) <= strtotime('+30 days') ? 'recommended' : 'not_ready';
        if (Auth::user()?->canPermission('retention.manage')) DB::table('retention_assignments')->where('id', $assignment->id)->update(['ai_category' => $category, 'archive_status' => $archive]);
        AuditLogger::log('arnel', 'retention_analysis', 'retention_assignment', $assignment->id, null, ['persisted' => Auth::user()?->canPermission('retention.manage'), 'suggested_category' => $category, 'archive_status' => $archive]);
        return response()->json(['success' => true, 'data' => ['suggested_category' => $category, 'archive_status' => $archive, 'summary' => $insights['summary']]]);
    }

    public function chat(Request $request, ArnelAiService $arnel): JsonResponse
    {
        $data = $request->validate(['message' => ['required', 'string', 'max:2000'], 'action' => ['nullable', 'in:reservation,document,contract,legal,retention'], 'facility_name' => ['nullable', 'string', 'max:150'], 'facility_id' => ['nullable', 'integer'], 'start_datetime' => ['nullable', 'date'], 'end_datetime' => ['nullable', 'date', 'after:start_datetime'], 'document_id' => ['nullable', 'integer'], 'contract_id' => ['nullable', 'integer'], 'legal_case_id' => ['nullable', 'integer'], 'assignment_id' => ['nullable', 'integer']]);
        if (($data['action'] ?? null) === 'reservation') return $this->analyzeReservation($request, $arnel);
        if (($data['action'] ?? null) === 'document') return $this->analyzeDocument($request, $arnel);
        if (($data['action'] ?? null) === 'contract') return $this->analyzeContract($request, $arnel);
        if (($data['action'] ?? null) === 'legal') return $this->analyzeLegalCase($request, $arnel);
        if (($data['action'] ?? null) === 'retention') return $this->analyzeRetention($request, $arnel);
        $user = Auth::user();
        $context = ['pending_reservations' => 0, 'active_contracts' => 0, 'active_documents' => 0, 'active_legal_cases' => 0, 'records' => []];
        if ($user?->canPermission('reservation.view')) {
            $context['pending_reservations'] = DB::table('reservations')->where('status', 'pending')->count();
            $context['records']['reservations'] = DB::table('reservations')->where('status', 'pending')->select('id', 'purpose', 'facility_id', 'start_datetime', 'end_datetime', 'status')->orderBy('start_datetime')->limit(12)->get()->map(fn ($r) => ['title' => $r->purpose ?? 'Reservation', 'facility_id' => $r->facility_id, 'start' => $r->start_datetime, 'end' => $r->end_datetime, 'status' => $r->status, 'url' => url('/reservations/' . $r->id)])->all();
        }
        if ($user?->canPermission('contract.view')) {
            $context['active_contracts'] = DB::table('contracts')->whereIn('status', ['active', 'renewed'])->count();
            $context['records']['contracts'] = DB::table('contracts')->whereIn('status', ['active', 'renewed'])->select('id', 'title', 'counterparty_name', 'end_date', 'status')->orderBy('end_date')->limit(12)->get()->map(fn ($r) => ['title' => $r->title, 'counterparty' => $r->counterparty_name, 'end_date' => $r->end_date, 'status' => $r->status, 'url' => url('/contracts/' . $r->id)])->all();
        }
        if ($user?->canPermission('document.view')) {
            $context['active_documents'] = DB::table('documents')->where('status', 'active')->whereNull('deleted_at')->count();
            $context['records']['documents'] = DB::table('documents')->where('status', 'active')->whereNull('deleted_at')->select('id', 'title', 'document_number', 'ai_classification', 'ai_summary')->orderByDesc('created_at')->limit(12)->get()->map(fn ($r) => ['title' => $r->title, 'number' => $r->document_number, 'classification' => $r->ai_classification, 'summary' => $r->ai_summary, 'url' => url('/documents/' . $r->id)])->all();
        }
        if ($user?->canPermission('legal.view')) {
            $context['active_legal_cases'] = DB::table('legal_cases')->whereIn('status', ['open', 'in_progress', 'on_hold'])->count();
            $context['records']['legal_cases'] = DB::table('legal_cases')->whereIn('status', ['open', 'in_progress', 'on_hold'])->select('id', 'title', 'case_number', 'next_deadline', 'status')->orderBy('next_deadline')->limit(12)->get()->map(fn ($r) => ['title' => $r->title, 'case_number' => $r->case_number, 'next_deadline' => $r->next_deadline, 'status' => $r->status, 'url' => url('/legal/' . $r->id)])->all();
        }
        $history = session('arnel.chat_history', []);
        $reply = $arnel->assistantReply($data['message'], $context, $history);
        $history[] = ['role' => 'user', 'content' => $data['message']];
        $history[] = ['role' => 'assistant', 'content' => $reply['answer'] ?? ''];
        session(['arnel.chat_history' => array_slice($history, -8)]);
        AuditLogger::log('arnel', 'assistant_chat', 'assistant', 0, null, ['message_length' => strlen($data['message']), 'source_count' => count($reply['sources'] ?? [])]);
        return response()->json(['success' => true] + $reply);
    }

    public function correctDocumentClassification(Request $request, int $id): JsonResponse
    {
        if (! Auth::user()?->canPermission('document.manage')) return response()->json(['message' => 'Document management permission required.'], 403);
        $data = $request->validate(['correct' => ['required', 'boolean'], 'corrected_classification' => ['nullable', 'string', 'max:150']]);
        $document = DB::table('documents')->whereNull('deleted_at')->find($id);
        if (! $document) return response()->json(['message' => 'Document not found.'], 404);
        AuditLogger::log('arnel', 'document_classification_feedback', 'document', $id, null, ['correct' => $data['correct'], 'original_classification' => $document->ai_classification, 'corrected_classification' => $data['corrected_classification'] ?? null]);
        if (! $data['correct'] && ! empty($data['corrected_classification'])) DB::table('documents')->where('id', $id)->update(['ai_classification' => $data['corrected_classification'], 'ai_category' => $data['corrected_classification']]);
        return response()->json(['success' => true]);
    }
}
