<?php

namespace App\Services;

use App\Repositories\RetentionRepository;

use App\Support\AuditLogger;
use Illuminate\Support\Facades\Auth;
class RetentionService
{
    public function __construct(private RetentionRepository $repo) {}

    public function listPolicies(): array
    {
        return $this->repo->allPolicies();
    }

    public function listAssignments(): array
    {
        return $this->repo->allAssignments();
    }

    public function listHolds(): array
    {
        return $this->repo->allHolds();
    }

    public function listAvailableDocuments(): array
    {
        return $this->repo->allDocumentsWithoutAssignment();
    }

    public function listAllDocuments(): array
    {
        return $this->repo->allDocuments();
    }

    public function listLegalCases(): array
    {
        return $this->repo->allLegalCases();
    }

    /**
     * @return array{success: bool, error?: string}
     */
    public function createPolicy(array $input): array
    {
        $name    = trim($input['name'] ?? '');
        $cat     = trim($input['record_category'] ?? '');
        $months  = (int) ($input['retention_period_months'] ?? 0);
        $trigger = $input['trigger_event'] ?? 'creation_date';
        $action  = $input['disposal_action'] ?? 'review';
        $basis   = trim($input['legal_basis'] ?? '');

        if ($name === '' || $cat === '' || $months <= 0) {
            return ['success' => false, 'error' => 'Policy name, record category, and duration are required.'];
        }

        $policyId = $this->repo->createPolicy([
            'name'                    => $name,
            'record_category'         => $cat,
            'retention_period_months' => $months,
            'trigger_event'           => $trigger,
            'disposal_action'         => $action,
            'legal_basis'             => $basis,
        ]);

        AuditLogger::log('retention', 'create', 'retention_policy', $policyId ?: 0, null, [
            'name'                    => $name,
            'record_category'         => $cat,
            'retention_period_months' => $months,
            'disposal_action'         => $action,
        ]);

        return ['success' => true];
    }

    /**
     * @return array{success: bool, error?: string}
     */
    public function assignPolicy(array $input): array
    {
        $docId     = (int) ($input['document_id'] ?? 0);
        $policyId  = (int) ($input['policy_id'] ?? 0);
        $startDate = $input['retention_start_date'] ?? date('Y-m-d');

        if (!$docId || !$policyId || !$startDate) {
            return ['success' => false, 'error' => 'Document, policy, and start date are required.'];
        }

        // Fetch policy to find duration
        $policies = $this->repo->allPolicies();
        $policy = null;
        foreach ($policies as $p) {
            if ((int)$p['id'] === $policyId) {
                $policy = $p;
                break;
            }
        }

        if (!$policy) {
            return ['success' => false, 'error' => 'Retention policy not found.'];
        }

        $months = (int) $policy['retention_period_months'];
        $disposalDate = date('Y-m-d', strtotime("+$months months", strtotime($startDate)));

        $this->repo->assignPolicy([
            'document_id'              => $docId,
            'policy_id'                => $policyId,
            'retention_start_date'     => $startDate,
            'calculated_disposal_date' => $disposalDate,
        ]);

        return ['success' => true];
    }

    /**
     * @return array{success: bool, error?: string}
     */
    public function executeDisposal(int $assignmentId): array
    {
        $assignment = $this->repo->findAssignment($assignmentId);
        if (!$assignment) {
            return ['success' => false, 'error' => 'Retention assignment not found.'];
        }

        $docId = (int) $assignment['document_id'];
        if ($this->repo->isDocumentOnHold($docId)) {
            return ['success' => false, 'error' => 'This document is on an active legal hold and cannot be disposed.'];
        }

        $action = $assignment['disposal_action'];
        $actionTaken = 'reviewed';
        
        if ($action === 'destroy') {
            $this->repo->updateDocumentStatus($docId, 'disposed');
            $this->repo->updateAssignmentStatus($assignmentId, 'disposed');
            $actionTaken = 'destroyed';
        } elseif ($action === 'archive') {
            $this->repo->updateDocumentStatus($docId, 'archived');
            $this->repo->updateAssignmentStatus($assignmentId, 'disposed');
            $actionTaken = 'archived';
        } else {
            // review action
            $this->repo->updateAssignmentStatus($assignmentId, 'due_for_review');
            $actionTaken = 'reviewed';
        }

        $newStatus = $action === 'review' ? 'due_for_review' : 'disposed';

        $this->repo->createDisposalLog([
            'retention_assignment_id' => $assignmentId,
            'action_taken'            => $actionTaken,
            'performed_by'            => Auth::id(),
            'certificate_path'        => null,
        ]);

        AuditLogger::log('retention', 'dispose', 'retention_assignment', $assignmentId, null, [
            'document_id'  => $docId,
            'action_taken' => $actionTaken,
        ]);

        return [
            'success' => true,
            'id' => $assignmentId,
            'status' => $newStatus,
            'action_taken' => $actionTaken,
        ];
    }

    /**
     * @return array{success: bool, error?: string}
     */
    public function placeHold(array $input): array
    {
        $reason = trim($input['reason'] ?? '');
        $caseId = (int) ($input['related_case_id'] ?? 0);
        $docIds = $input['document_ids'] ?? [];

        if ($reason === '' || empty($docIds)) {
            return ['success' => false, 'error' => 'Reason and at least one document are required.'];
        }

        $holdId = $this->repo->createLegalHold([
            'reason'          => $reason,
            'related_case_id' => $caseId,
            'placed_by'       => Auth::id(),
        ]);

        foreach ($docIds as $docId) {
            $this->repo->addDocumentToHold($holdId, (int) $docId);
        }

        AuditLogger::log('retention', 'create', 'legal_hold', $holdId, null, [
            'reason'          => $reason,
            'related_case_id' => $caseId,
            'document_ids'    => array_map('intval', $docIds),
        ]);

        return ['success' => true];
    }

    /**
     * @return array{success: bool, error?: string}
     */
    public function releaseHold(int $holdId): array
    {
        $this->repo->releaseLegalHold($holdId);
        AuditLogger::log('retention', 'update', 'legal_hold', $holdId, null, ['status' => 'released']);
        return ['success' => true, 'id' => $holdId, 'released' => true];
    }

    public function pendingDisposalCount(): int
    {
        return $this->repo->countPendingDisposal();
    }
}
