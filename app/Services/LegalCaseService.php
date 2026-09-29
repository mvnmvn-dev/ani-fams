<?php

namespace App\Services;

use App\Repositories\LegalCaseRepository;

use App\Support\AuditLogger;
class LegalCaseService
{
    public function __construct(private LegalCaseRepository $repo) {}

    public function listCases(): array
    {
        return $this->repo->all();
    }

    public function caseDetails(int $id): ?array
    {
        $case = $this->repo->find($id);
        if (!$case) return null;

        $case['parties'] = $this->repo->getParties($id);
        $case['milestones'] = $this->repo->getMilestones($id);
        $case['deadlines'] = $this->repo->getDeadlines($id);
        $case['documents'] = $this->repo->getDocuments($id);
        $case['costs'] = $this->repo->getCosts($id);
        $case['vendors'] = $this->repo->getVendorsForCase($id);
        $case['outcome'] = $this->repo->getOutcome($id);
        $case['timeline'] = $this->getCaseTimeline($id);
        $case['timeline_stats'] = $this->getTimelineStats($id);
        $conflicts = $this->getCaseConflicts($id);
        $case['party_conflicts'] = $conflicts['party_conflicts'];
        $case['counsel_conflicts'] = $conflicts['counsel_conflicts'];
        $case['conflict_summary'] = $this->getConflictSummary($id);

        $totalCost = 0;
        foreach ($case['costs'] as $cost) {
            $totalCost += (float) $cost['amount'];
        }
        $case['total_cost'] = $totalCost;

        $budget = isset($case['budget_amount']) && $case['budget_amount'] !== null
            ? (float) $case['budget_amount']
            : null;
        $case['budget_amount'] = $budget;
        $case['variance'] = $budget !== null ? $budget - $totalCost : null;
        $case['pct_used'] = ($budget !== null && $budget > 0)
            ? round(($totalCost / $budget) * 100, 1)
            : null;

        return $case;
    }

    /**
     * @return array{success: bool, error?: string, id?: int}
     */
    public function createCase(array $input): array
    {
        $title      = trim($input['title'] ?? '');
        $type       = $input['case_type'] ?? 'other';
        $status     = $input['status'] ?? 'open';
        $filed      = $input['filed_date'] ?? null;
        $court      = trim($input['court_or_agency'] ?? '');
        $opposing   = trim($input['opposing_party'] ?? '');
        $counselId  = (int) ($input['assigned_counsel_id'] ?? 0);
        $external   = trim($input['external_counsel_name'] ?? '');
        $risk       = $input['risk_level'] ?? 'medium';
        $desc       = trim($input['description'] ?? '');
        $budgetRaw  = $input['budget_amount'] ?? null;
        $budget     = ($budgetRaw !== null && $budgetRaw !== '') ? (float) $budgetRaw : null;

        if ($title === '') {
            return ['success' => false, 'error' => 'Case title is required.'];
        }

        // Generate case number
        $caseNumber = 'LC-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(2)));

        $id = $this->repo->create([
            'case_number'           => $caseNumber,
            'title'                 => $title,
            'case_type'             => $type,
            'status'                => $status,
            'filed_date'            => $filed,
            'court_or_agency'       => $court,
            'opposing_party'        => $opposing,
            'assigned_counsel_id'   => $counselId ?: null,
            'external_counsel_name' => $external,
            'risk_level'            => $risk,
            'budget_amount'         => $budget,
            'description'           => $desc,
        ]);

        AuditLogger::log('legal', 'create', 'legal_case', $id, null, [
            'case_number' => $caseNumber,
            'title'       => $title,
            'case_type'   => $type,
            'status'      => $status,
            'risk_level'  => $risk,
        ]);
        $this->logLegalActivity($id, 'created', null, $status, 'Case opened');

        return ['success' => true, 'id' => $id];
    }

    /**
     * Defense Ready workflow: Pending Review → Under Investigation → Legal Opinion → Resolved
     * @return array{success: bool, error?: string}
     */
    public function advanceWorkflow(int $caseId, string $toStatus, ?string $remarks = null): array
    {
        if (! Auth::user()?->canPermission('legal.manage')) {
            return ['success' => false, 'error' => 'Permission denied.'];
        }

        $map = [
            'pending_review' => 'pending_review',
            'under_investigation' => 'under_investigation',
            'legal_opinion' => 'legal_opinion',
            'resolved' => 'resolved',
            'open' => 'pending_review',
            'in_progress' => 'under_investigation',
            'closed' => 'resolved',
        ];
        $normalized = $map[$toStatus] ?? $toStatus;
        $allowed = ['pending_review', 'under_investigation', 'legal_opinion', 'resolved'];
        if (! in_array($normalized, $allowed, true)) {
            return ['success' => false, 'error' => 'Invalid workflow status.'];
        }

        $case = $this->repo->find($caseId);
        if (! $case) {
            return ['success' => false, 'error' => 'Case not found.'];
        }

        $from = (string) ($case['status'] ?? '');
        $this->repo->update($caseId, ['status' => $normalized]);
        $this->logLegalActivity($caseId, 'workflow', $from, $normalized, $remarks);
        AuditLogger::log('legal', 'workflow', 'legal_case', $caseId, ['status' => $from], ['status' => $normalized]);

        return ['success' => true, 'status' => $normalized];
    }

    private function logLegalActivity(int $caseId, string $action, ?string $from, ?string $to, ?string $remarks = null, array $meta = []): void
    {
        try {
            if (! \Illuminate\Support\Facades\Schema::hasTable('legal_activity_logs')) {
                return;
            }
            \Illuminate\Support\Facades\DB::table('legal_activity_logs')->insert([
                'case_id' => $caseId,
                'actor_id' => Auth::id(),
                'action' => $action,
                'from_status' => $from,
                'to_status' => $to,
                'remarks' => $remarks,
                'meta' => $meta === [] ? null : json_encode($meta),
                'created_at' => now()->format('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
        }
    }

    /**
     * @return array{success: bool, error?: string}
     */
    public function addParty(array $input): array
    {
        $caseId      = (int) ($input['case_id'] ?? 0);
        $partyName   = trim($input['party_name'] ?? '');
        $role        = $input['role'] ?? '';
        $contactInfo = trim($input['contact_info'] ?? '');

        if (!$caseId || $partyName === '' || $role === '') {
            return ['success' => false, 'error' => 'Case ID, party name, and role are required.'];
        }

        $this->repo->addParty([
            'case_id'      => $caseId,
            'party_name'   => $partyName,
            'role'         => $role,
            'contact_info' => $contactInfo,
        ]);

        return ['success' => true];
    }

    /**
     * @return array{success: bool, error?: string}
     */
    public function addMilestone(array $input): array
    {
        $caseId   = (int) ($input['case_id'] ?? 0);
        $type     = $input['milestone_type'] ?? '';
        $date     = $input['milestone_date'] ?? '';
        $desc     = trim($input['description'] ?? '');

        if (!$caseId || $type === '' || !$date) {
            return ['success' => false, 'error' => 'Case ID, milestone type, and milestone date are required.'];
        }

        $this->repo->addMilestone([
            'case_id'        => $caseId,
            'milestone_type' => $type,
            'milestone_date' => $date,
            'description'    => $desc,
        ]);

        return ['success' => true];
    }

    /**
     * @return array{success: bool, error?: string}
     */
    public function toggleMilestone(int $milestoneId, bool $completed): array
    {
        $this->repo->toggleMilestone($milestoneId, $completed ? 1 : 0);
        return [
            'success' => true,
            'id' => $milestoneId,
            'completed' => $completed,
        ];
    }

    /**
     * @return array{success: bool, error?: string}
     */
    public function addDeadline(array $input): array
    {
        $caseId   = (int) ($input['case_id'] ?? 0);
        $title    = trim($input['title'] ?? '');
        $date     = $input['due_date'] ?? '';
        $desc     = trim($input['description'] ?? '');

        if (!$caseId || $title === '' || !$date) {
            return ['success' => false, 'error' => 'Case ID, title, and due date are required.'];
        }

        $id = $this->repo->addDeadline([
            'case_id'     => $caseId,
            'title'       => $title,
            'due_date'    => $date,
            'description' => $desc,
        ]);

        return ['success' => true, 'id' => $id];
    }

    /**
     * @return array{success: bool, error?: string}
     */
    public function toggleDeadline(int $deadlineId, bool $completed): array
    {
        $this->repo->toggleDeadline($deadlineId, $completed ? 1 : 0);
        return [
            'success' => true,
            'id' => $deadlineId,
            'completed' => $completed,
        ];
    }

    public function getUpcomingDeadlines(int $daysAhead = 30): array
    {
        return $this->repo->getUpcomingDeadlines($daysAhead);
    }

    public function sendDeadlineReminders(): array
    {
        $deadlines = $this->repo->getUpcomingDeadlines(7); // Next 7 days
        $sentCount = 0;
        
        $notificationService = app(NotificationService::class);
        foreach ($deadlines as $d) {
            if (!empty($d['counsel_user_id'])) {
                $notificationService->send(
                    (int) $d['counsel_user_id'],
                    'legal',
                    'Upcoming Deadline',
                    "Deadline '{$d['title']}' for case '{$d['case_title']}' is due on {$d['due_date']}.",
                    '/legal/' . $d['case_id'],
                    true
                );
                $sentCount++;
            }
        }
        
        return ['success' => true, 'sent_count' => $sentCount];
    }

    /**
     * @return array{success: bool, error?: string}
     */
    public function linkDocument(int $caseId, int $documentId): array
    {
        if (!$caseId || !$documentId) {
            return ['success' => false, 'error' => 'Case ID and document ID are required.'];
        }

        $this->repo->addDocument($caseId, $documentId);
        return ['success' => true];
    }

    /**
     * @return array{success: bool, error?: string}
     */
    public function addCost(array $input): array
    {
        $caseId      = (int) ($input['case_id'] ?? 0);
        $description = trim($input['description'] ?? '');
        $amount      = (float) ($input['amount'] ?? 0);
        $date        = $input['incurred_date'] ?? date('Y-m-d');

        if (!$caseId || $description === '' || $amount <= 0) {
            return ['success' => false, 'error' => 'Case ID, description, and positive amount are required.'];
        }

        $this->repo->addCost([
            'case_id'       => $caseId,
            'description'   => $description,
            'amount'        => $amount,
            'incurred_date' => $date,
        ]);

        return ['success' => true];
    }

    public function softDelete(int $id): array
    {
        $case = $this->repo->find($id);
        if (! $case) {
            return ['success' => false, 'error' => 'Legal case not found.'];
        }
        $this->repo->softDelete($id);
        AuditLogger::log('legal', 'soft_delete', 'legal_case', $id);

        return ['success' => true];
    }

    /**
     * @return array{success: bool, error?: string, id?: int}
     */
    public function addVendor(array $input): array
    {
        $name = trim($input['name'] ?? '');
        if ($name === '') {
            return ['success' => false, 'error' => 'Vendor name is required.'];
        }

        $id = $this->repo->createVendor([
            'name' => $name,
            'vendor_type' => $input['vendor_type'] ?? 'counsel',
            'contact_name' => trim($input['contact_name'] ?? '') ?: null,
            'email' => trim($input['email'] ?? '') ?: null,
            'phone' => trim($input['phone'] ?? '') ?: null,
            'firm' => trim($input['firm'] ?? '') ?: null,
            'notes' => trim($input['notes'] ?? '') ?: null,
        ]);

        return ['success' => true, 'id' => $id];
    }

    /**
     * @return array{success: bool, error?: string}
     */
    public function linkVendorToCase(int $caseId, int $vendorId, ?string $roleNote = null): array
    {
        if (! $caseId || ! $vendorId) {
            return ['success' => false, 'error' => 'Case ID and vendor ID are required.'];
        }
        if (! $this->repo->find($caseId)) {
            return ['success' => false, 'error' => 'Legal case not found.'];
        }

        $this->repo->linkVendor($caseId, $vendorId, $roleNote ? trim($roleNote) : null);

        return ['success' => true];
    }

    /**
     * @return array{success: bool, error?: string}
     */
    public function updateBudget(int $caseId, $amount): array
    {
        if (! $this->repo->find($caseId)) {
            return ['success' => false, 'error' => 'Legal case not found.'];
        }

        $value = ($amount === null || $amount === '') ? null : (float) $amount;
        if ($value !== null && $value < 0) {
            return ['success' => false, 'error' => 'Budget amount cannot be negative.'];
        }

        $this->repo->updateBudget($caseId, $value);

        return ['success' => true];
    }

    public function allVendors(): array
    {
        return $this->repo->allVendors();
    }

    public function budgetRollup(): array
    {
        return $this->repo->budgetRollup();
    }

    public function activeCount(): int
    {
        return $this->repo->countActiveCases();
    }

    // ==================== OUTCOME/SETTLEMENT TRACKING ====================

    /**
     * Add or update case outcome/settlement
     * @return array{success: bool, error?: string, id?: int}
     */
    public function recordOutcome(array $input): array
    {
        $caseId = (int) ($input['case_id'] ?? 0);
        if (!$caseId) {
            return ['success' => false, 'error' => 'Case ID is required.'];
        }

        $case = $this->repo->find($caseId);
        if (!$case) {
            return ['success' => false, 'error' => 'Legal case not found.'];
        }

        $outcomeType = $input['outcome_type'] ?? null;
        if (!$outcomeType) {
            return ['success' => false, 'error' => 'Outcome type is required.'];
        }

        $data = [
            'case_id' => $caseId,
            'outcome_type' => $outcomeType,
            'outcome_date' => $input['outcome_date'] ?? date('Y-m-d'),
            'description' => trim($input['description'] ?? ''),
            'recorded_by' => auth()->id(),
        ];

        if ($outcomeType === 'settled') {
            $data['settlement_amount'] = $input['settlement_amount'] ?? null;
            $data['settlement_terms'] = trim($input['settlement_terms'] ?? '');
            $data['settlement_date'] = $input['settlement_date'] ?? date('Y-m-d');
        }

        if (in_array($outcomeType, ['won', 'lost'])) {
            $data['judgment_summary'] = trim($input['judgment_summary'] ?? '');
            $data['awarded_amount'] = $input['awarded_amount'] ?? null;
        }

        if ($input['is_appealed'] ?? false) {
            $data['is_appealed'] = true;
            $data['appeal_reason'] = trim($input['appeal_reason'] ?? '');
            $data['appeal_deadline'] = $input['appeal_deadline'] ?? null;
        }

        $id = $this->repo->createOutcome($data);

        // Update case status
        $this->repo->update($caseId, ['status' => $outcomeType]);

        AuditLogger::log('legal', 'record_outcome', 'legal_case_outcome', $id, null, [
            'case_id' => $caseId,
            'outcome_type' => $outcomeType,
        ]);

        return ['success' => true, 'id' => $id];
    }

    /**
     * Get outcome information for a case
     */
    public function getOutcome(int $caseId): ?array
    {
        return $this->repo->getOutcome($caseId);
    }

    // ==================== CASE TIMELINE VISUALIZATION ====================

    /**
     * Get timeline events for a case (milestones, deadlines, outcomes)
     */
    public function getCaseTimeline(int $caseId): array
    {
        $case = $this->repo->find($caseId);
        if (!$case) {
            return [];
        }

        $timeline = [];

        // Add filed date
        if ($case['filed_date']) {
            $timeline[] = [
                'type' => 'filed',
                'date' => $case['filed_date'],
                'title' => 'Case Filed',
                'description' => $case['court_or_agency'] ?? 'N/A',
                'icon' => 'file-document',
            ];
        }

        // Add milestones
        $milestones = $this->repo->getMilestones($caseId);
        foreach ($milestones as $m) {
            $timeline[] = [
                'type' => 'milestone',
                'date' => $m['milestone_date'],
                'title' => ucfirst(str_replace('_', ' ', $m['milestone_type'])),
                'description' => $m['description'] ?? '',
                'completed' => (bool) $m['is_completed'],
                'icon' => 'calendar-check',
            ];
        }

        // Add deadlines
        $deadlines = $this->repo->getDeadlines($caseId);
        foreach ($deadlines as $d) {
            $timeline[] = [
                'type' => 'deadline',
                'date' => $d['due_date'],
                'title' => $d['title'] ?? 'Deadline',
                'description' => $d['description'] ?? '',
                'completed' => (bool) $d['is_completed'],
                'icon' => 'clock-alert',
            ];
        }

        // Add outcome
        $outcome = $this->repo->getOutcome($caseId);
        if ($outcome) {
            $timeline[] = [
                'type' => 'outcome',
                'date' => $outcome['outcome_date'],
                'title' => ucfirst(str_replace('_', ' ', $outcome['outcome_type'])),
                'description' => $outcome['description'] ?? '',
                'amount' => $outcome['settlement_amount'] ?? $outcome['awarded_amount'] ?? null,
                'icon' => 'flag-checkered',
            ];
        }

        // Sort by date
        usort($timeline, fn($a, $b) => strcmp($a['date'], $b['date']));

        return $timeline;
    }

    /**
     * Get timeline summary/stats for dashboard
     */
    public function getTimelineStats(int $caseId): array
    {
        $case = $this->repo->find($caseId);
        if (!$case) {
            return [];
        }

        $milestones = $this->repo->getMilestones($caseId);
        $completedMilestones = count(array_filter($milestones, fn($m) => $m['is_completed']));

        $deadlines = $this->repo->getDeadlines($caseId);
        $completedDeadlines = count(array_filter($deadlines, fn($d) => $d['is_completed']));
        $overdue = count(array_filter($deadlines, fn($d) => !$d['is_completed'] && $d['due_date'] < date('Y-m-d')));

        return [
            'milestones_total' => count($milestones),
            'milestones_completed' => $completedMilestones,
            'deadlines_total' => count($deadlines),
            'deadlines_completed' => $completedDeadlines,
            'deadlines_overdue' => $overdue,
            'days_since_filed' => $case['filed_date'] ? (int) (strtotime('now') - strtotime($case['filed_date'])) / 86400 : 0,
        ];
    }

    // ==================== CONFLICT-OF-INTEREST CHECKING ====================

    /**
     * Check for conflicts between parties
     * @return array{success: bool, error?: string, id?: int}
     */
    public function flagPartyConflict(array $input): array
    {
        $caseId = (int) ($input['case_id'] ?? 0);
        $partyAId = (int) ($input['party_a_id'] ?? 0);
        $partyBId = (int) ($input['party_b_id'] ?? 0);
        $conflictType = $input['conflict_type'] ?? null;
        $severity = $input['severity'] ?? 'medium';

        if (!$caseId || !$partyAId || !$partyBId || !$conflictType) {
            return ['success' => false, 'error' => 'Case ID, both parties, and conflict type are required.'];
        }

        if ($partyAId === $partyBId) {
            return ['success' => false, 'error' => 'Cannot flag conflict between same party.'];
        }

        $data = [
            'case_id' => $caseId,
            'party_a_id' => min($partyAId, $partyBId),
            'party_b_id' => max($partyAId, $partyBId),
            'conflict_type' => $conflictType,
            'description' => trim($input['description'] ?? ''),
            'severity' => $severity,
            'conflict_flagged' => true,
            'flagged_by' => auth()->id(),
            'flagged_at' => now(),
            'resolution_status' => 'pending',
        ];

        $id = $this->repo->createPartyConflict($data);

        AuditLogger::log('legal', 'flag_conflict', 'legal_case_party_conflict', $id, null, [
            'case_id' => $caseId,
            'conflict_type' => $conflictType,
            'severity' => $severity,
        ]);

        return ['success' => true, 'id' => $id];
    }

    /**
     * Check counsel for conflicts
     * @return array{success: bool, error?: string, id?: int}
     */
    public function flagCounselConflict(array $input): array
    {
        $caseId = (int) ($input['case_id'] ?? 0);
        $counselId = (int) ($input['counsel_id'] ?? 0);
        $externalParty = trim($input['external_party'] ?? '');
        $conflictType = $input['conflict_type'] ?? null;

        if (!$caseId || (!$counselId && !$externalParty) || !$conflictType) {
            return ['success' => false, 'error' => 'Case ID, counsel/party, and conflict type are required.'];
        }

        $data = [
            'case_id' => $caseId,
            'counsel_id' => $counselId ?: null,
            'external_party' => $externalParty ?: null,
            'conflict_type' => $conflictType,
            'description' => trim($input['description'] ?? ''),
            'severity' => $input['severity'] ?? 'medium',
            'conflict_verified' => false,
            'status' => 'under_review',
        ];

        $id = $this->repo->createCounselConflict($data);

        AuditLogger::log('legal', 'flag_counsel_conflict', 'legal_case_counsel_conflict', $id, null, [
            'case_id' => $caseId,
            'conflict_type' => $conflictType,
        ]);

        return ['success' => true, 'id' => $id];
    }

    /**
     * Update conflict resolution
     * @return array{success: bool, error?: string}
     */
    public function resolveConflict(int $conflictId, array $input): array
    {
        $status = $input['resolution_status'] ?? null;
        if (!$status) {
            return ['success' => false, 'error' => 'Resolution status is required.'];
        }

        $data = [
            'resolution_status' => $status,
            'resolution_notes' => trim($input['resolution_notes'] ?? ''),
        ];

        $this->repo->updatePartyConflict($conflictId, $data);

        return ['success' => true];
    }

    /**
     * Verify counsel conflict
     * @return array{success: bool, error?: string}
     */
    public function verifyCounselConflict(int $conflictId, bool $verified, ?string $plan = null): array
    {
        $data = [
            'conflict_verified' => $verified,
            'verified_by' => auth()->id(),
            'verified_at' => now(),
            'status' => $verified ? 'confirmed' : 'dismissed',
        ];

        if ($plan) {
            $data['mitigation_plan'] = trim($plan);
            $data['status'] = 'resolved';
        }

        $this->repo->updateCounselConflict($conflictId, $data);

        return ['success' => true];
    }

    /**
     * Get all conflicts for a case
     */
    public function getCaseConflicts(int $caseId): array
    {
        return [
            'party_conflicts' => $this->repo->getPartyConflicts($caseId),
            'counsel_conflicts' => $this->repo->getCounselConflicts($caseId),
        ];
    }

    /**
     * Get conflict summary for dashboard
     */
    public function getConflictSummary(int $caseId): array
    {
        $partyConflicts = $this->repo->getPartyConflicts($caseId);
        $counselConflicts = $this->repo->getCounselConflicts($caseId);

        $unresolved = count(array_filter($partyConflicts, fn($c) => $c['resolution_status'] === 'pending'));
        $critical = count(array_filter($partyConflicts, fn($c) => $c['severity'] === 'critical'));
        $counselUnverified = count(array_filter($counselConflicts, fn($c) => !$c['conflict_verified']));

        return [
            'total_party_conflicts' => count($partyConflicts),
            'total_counsel_conflicts' => count($counselConflicts),
            'unresolved_party_conflicts' => $unresolved,
            'critical_conflicts' => $critical,
            'unverified_counsel_conflicts' => $counselUnverified,
            'has_critical_issues' => $critical > 0 || $counselUnverified > 0,
        ];
    }

    /**
     * Scan for potential conflicts: party name matches across open cases, and shared counsel.
     *
     * @return array{success: bool, error?: string, flagged?: int, matches?: array}
     */
    public function runConflictScan(int $caseId): array
    {
        $case = $this->repo->find($caseId);
        if (! $case) {
            return ['success' => false, 'error' => 'Legal case not found.'];
        }

        $flagged = 0;
        $matches = [];

        foreach ($this->repo->findCrossCasePartyNameMatches($caseId) as $match) {
            $matches[] = [
                'type' => 'party_name',
                'party_name' => $match['party_name'],
                'other_case' => $match['other_case_number'] . ' — ' . $match['other_case_title'],
            ];

            // Flag against opposing party on this case when present, else skip auto-create
            $parties = $this->repo->getParties($caseId);
            $opposing = null;
            foreach ($parties as $p) {
                if ((int) $p['id'] !== (int) $match['party_id'] && in_array($p['role'] ?? '', ['plaintiff', 'defendant'], true)) {
                    $opposing = $p;
                    break;
                }
            }
            if (! $opposing) {
                continue;
            }

            $existing = $this->repo->getPartyConflicts($caseId);
            $a = min((int) $match['party_id'], (int) $opposing['id']);
            $b = max((int) $match['party_id'], (int) $opposing['id']);
            $already = false;
            foreach ($existing as $c) {
                if ((int) $c['party_a_id'] === $a && (int) $c['party_b_id'] === $b) {
                    $already = true;
                    break;
                }
            }
            if ($already) {
                continue;
            }

            $result = $this->flagPartyConflict([
                'case_id' => $caseId,
                'party_a_id' => (int) $match['party_id'],
                'party_b_id' => (int) $opposing['id'],
                'conflict_type' => 'prior_representation',
                'severity' => 'high',
                'description' => 'Auto-detected: "' . $match['party_name'] . '" also appears on open case '
                    . $match['other_case_number'] . ' (' . $match['other_case_title'] . ').',
            ]);
            if ($result['success'] ?? false) {
                $flagged++;
            }
        }

        foreach ($this->repo->findSharedCounselCases($caseId) as $shared) {
            $matches[] = [
                'type' => 'shared_counsel',
                'party_name' => $shared['counsel_name'] ?? 'Counsel',
                'other_case' => $shared['other_case_number'] . ' — ' . $shared['other_case_title'],
            ];

            $existingCounsel = $this->repo->getCounselConflicts($caseId);
            $already = false;
            foreach ($existingCounsel as $c) {
                if ((int) ($c['counsel_id'] ?? 0) === (int) $shared['counsel_id']
                    && ($c['status'] ?? '') !== 'dismissed') {
                    $already = true;
                    break;
                }
            }
            if ($already) {
                continue;
            }

            $result = $this->flagCounselConflict([
                'case_id' => $caseId,
                'counsel_id' => (int) $shared['counsel_id'],
                'conflict_type' => 'prior_representation',
                'severity' => 'medium',
                'description' => 'Auto-detected: counsel also assigned to open case '
                    . $shared['other_case_number'] . ' (' . $shared['other_case_title'] . ').',
            ]);
            if ($result['success'] ?? false) {
                $flagged++;
            }
        }

        AuditLogger::log('legal', 'conflict_scan', 'legal_case', $caseId, null, [
            'matches' => count($matches),
            'flagged' => $flagged,
        ]);

        return [
            'success' => true,
            'flagged' => $flagged,
            'matches' => $matches,
        ];
    }
}
