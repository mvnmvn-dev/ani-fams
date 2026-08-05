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
        
        $totalCost = 0;
        foreach ($case['costs'] as $cost) {
            $totalCost += $cost['amount'];
        }
        $case['total_cost'] = $totalCost;

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
        return ['success' => true];
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

    public function activeCount(): int
    {
        return $this->repo->countActiveCases();
    }
}
