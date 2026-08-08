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
        $case['documents'] = $this->repo->getDocuments($id);
        $case['costs'] = $this->repo->getCosts($id);
        $case['vendors'] = $this->repo->getVendorsForCase($id);

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

        return ['success' => true, 'id' => $id];
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
}
