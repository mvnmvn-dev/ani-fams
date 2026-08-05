<?php

namespace App\Repositories;

use PDO;

class LegalCaseRepository
{
    use UsesPdo;

    public function all(): array
    {
        $sql = "SELECT lc.*, e.full_name AS counsel_name
                FROM legal_cases lc
                LEFT JOIN employees e ON e.id = lc.assigned_counsel_id
                ORDER BY lc.created_at DESC";
        return $this->pdo()->query($sql)->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo()->prepare(
            "SELECT lc.*, e.full_name AS counsel_name
             FROM legal_cases lc
             LEFT JOIN employees e ON e.id = lc.assigned_counsel_id
             WHERE lc.id = :id"
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo()->prepare(
            "INSERT INTO legal_cases (case_number, title, case_type, status, filed_date, court_or_agency, 
                                      opposing_party, assigned_counsel_id, external_counsel_name, risk_level, description)
             VALUES (:case_number, :title, :case_type, :status, :filed_date, :court_or_agency, 
                     :opposing_party, :assigned_counsel_id, :external_counsel_name, :risk_level, :description)"
        );
        $stmt->execute([
            'case_number'           => $data['case_number'],
            'title'                 => $data['title'],
            'case_type'             => $data['case_type'] ?? 'other',
            'status'                => $data['status'] ?? 'open',
            'filed_date'            => $data['filed_date'] ?: null,
            'court_or_agency'       => $data['court_or_agency'] ?? null,
            'opposing_party'        => $data['opposing_party'] ?? null,
            'assigned_counsel_id'   => $data['assigned_counsel_id'] ? (int) $data['assigned_counsel_id'] : null,
            'external_counsel_name' => $data['external_counsel_name'] ?? null,
            'risk_level'            => $data['risk_level'] ?? 'medium',
            'description'           => $data['description'] ?? null,
        ]);
        return (int) $this->pdo()->lastInsertId();
    }

    public function getParties(int $caseId): array
    {
        $stmt = $this->pdo()->prepare("SELECT * FROM legal_case_parties WHERE case_id = :caseId ORDER BY role");
        $stmt->execute(['caseId' => $caseId]);
        return $stmt->fetchAll();
    }

    public function addParty(array $data): int
    {
        $stmt = $this->pdo()->prepare(
            "INSERT INTO legal_case_parties (case_id, party_name, role, contact_info)
             VALUES (:case_id, :party_name, :role, :contact_info)"
        );
        $stmt->execute([
            'case_id'      => $data['case_id'],
            'party_name'   => $data['party_name'],
            'role'         => $data['role'],
            'contact_info' => $data['contact_info'] ?? null,
        ]);
        return (int) $this->pdo()->lastInsertId();
    }

    public function getMilestones(int $caseId): array
    {
        $stmt = $this->pdo()->prepare("SELECT * FROM legal_case_milestones WHERE case_id = :caseId ORDER BY milestone_date ASC");
        $stmt->execute(['caseId' => $caseId]);
        return $stmt->fetchAll();
    }

    public function addMilestone(array $data): int
    {
        $stmt = $this->pdo()->prepare(
            "INSERT INTO legal_case_milestones (case_id, milestone_type, milestone_date, description, is_completed)
             VALUES (:case_id, :milestone_type, :milestone_date, :description, 0)"
        );
        $stmt->execute([
            'case_id'        => $data['case_id'],
            'milestone_type' => $data['milestone_type'],
            'milestone_date' => $data['milestone_date'],
            'description'    => $data['description'] ?? null,
        ]);
        return (int) $this->pdo()->lastInsertId();
    }

    public function toggleMilestone(int $milestoneId, int $isCompleted): void
    {
        $stmt = $this->pdo()->prepare("UPDATE legal_case_milestones SET is_completed = :status WHERE id = :id");
        $stmt->execute(['status' => $isCompleted, 'id' => $milestoneId]);
    }

    public function getDocuments(int $caseId): array
    {
        $stmt = $this->pdo()->prepare(
            "SELECT d.*, dc.name AS category_name, u.full_name AS owner_name, dv.file_path
             FROM documents d
             JOIN legal_case_documents lcd ON lcd.document_id = d.id
             LEFT JOIN document_categories dc ON dc.id = d.category_id
             JOIN users u ON u.id = d.owner_id
             LEFT JOIN document_versions dv ON dv.id = d.current_version_id
             WHERE lcd.case_id = :caseId"
        );
        $stmt->execute(['caseId' => $caseId]);
        return $stmt->fetchAll();
    }

    public function addDocument(int $caseId, int $documentId): void
    {
        $stmt = $this->pdo()->prepare(
            "INSERT INTO legal_case_documents (case_id, document_id) VALUES (:caseId, :documentId)
             ON DUPLICATE KEY UPDATE document_id = document_id"
        );
        $stmt->execute(['caseId' => $caseId, 'documentId' => $documentId]);
    }

    public function getCosts(int $caseId): array
    {
        $stmt = $this->pdo()->prepare("SELECT * FROM legal_case_costs WHERE case_id = :caseId ORDER BY incurred_date DESC");
        $stmt->execute(['caseId' => $caseId]);
        return $stmt->fetchAll();
    }

    public function addCost(array $data): int
    {
        $stmt = $this->pdo()->prepare(
            "INSERT INTO legal_case_costs (case_id, description, amount, incurred_date)
             VALUES (:case_id, :description, :amount, :incurred_date)"
        );
        $stmt->execute([
            'case_id'       => $data['case_id'],
            'description'   => $data['description'],
            'amount'        => (float) $data['amount'],
            'incurred_date' => $data['incurred_date'],
        ]);
        return (int) $this->pdo()->lastInsertId();
    }

    public function countActiveCases(): int
    {
        return (int) $this->pdo()
            ->query("SELECT COUNT(*) FROM legal_cases WHERE status IN ('open','in_progress')")
            ->fetchColumn();
    }
}
