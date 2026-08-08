<?php

namespace App\Repositories;

use App\Support\DepartmentFilter;
use PDO;

class LegalCaseRepository
{
    use UsesPdo;

    public function all(): array
    {
        [$deptSql, $deptBindings] = DepartmentFilter::sqlOnColumn('e.department_id');
        $sql = "SELECT lc.*, e.full_name AS counsel_name
                FROM legal_cases lc
                LEFT JOIN employees e ON e.id = lc.assigned_counsel_id
                WHERE lc.deleted_at IS NULL
                  {$deptSql}
                ORDER BY lc.created_at DESC";
        if ($deptBindings === []) {
            return $this->pdo()->query($sql)->fetchAll();
        }
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($deptBindings);

        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo()->prepare(
            "SELECT lc.*, e.full_name AS counsel_name
             FROM legal_cases lc
             LEFT JOIN employees e ON e.id = lc.assigned_counsel_id
             WHERE lc.id = :id AND lc.deleted_at IS NULL"
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function softDelete(int $id): void
    {
        $stmt = $this->pdo()->prepare('UPDATE legal_cases SET deleted_at = CURRENT_TIMESTAMP WHERE id = :id AND deleted_at IS NULL');
        $stmt->execute(['id' => $id]);
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO legal_cases (case_number, title, case_type, status, filed_date, court_or_agency,
                                      opposing_party, assigned_counsel_id, external_counsel_name, risk_level,
                                      budget_amount, description)
             VALUES (:case_number, :title, :case_type, :status, :filed_date, :court_or_agency,
                     :opposing_party, :assigned_counsel_id, :external_counsel_name, :risk_level,
                     :budget_amount, :description)'
        );
        $stmt->execute([
            'case_number' => $data['case_number'],
            'title' => $data['title'],
            'case_type' => $data['case_type'] ?? 'other',
            'status' => $data['status'] ?? 'open',
            'filed_date' => $data['filed_date'] ?: null,
            'court_or_agency' => $data['court_or_agency'] ?? null,
            'opposing_party' => $data['opposing_party'] ?? null,
            'assigned_counsel_id' => $data['assigned_counsel_id'] ? (int) $data['assigned_counsel_id'] : null,
            'external_counsel_name' => $data['external_counsel_name'] ?? null,
            'risk_level' => $data['risk_level'] ?? 'medium',
            'budget_amount' => isset($data['budget_amount']) && $data['budget_amount'] !== '' && $data['budget_amount'] !== null
                ? (float) $data['budget_amount']
                : null,
            'description' => $data['description'] ?? null,
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    public function updateBudget(int $caseId, ?float $amount): void
    {
        $stmt = $this->pdo()->prepare('UPDATE legal_cases SET budget_amount = :amount WHERE id = :id AND deleted_at IS NULL');
        $stmt->execute(['amount' => $amount, 'id' => $caseId]);
    }

    public function allVendors(): array
    {
        return $this->pdo()->query('SELECT * FROM legal_vendors ORDER BY name')->fetchAll();
    }

    public function createVendor(array $data): int
    {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO legal_vendors (name, vendor_type, contact_name, email, phone, firm, notes)
             VALUES (:name, :vendor_type, :contact_name, :email, :phone, :firm, :notes)'
        );
        $stmt->execute([
            'name' => $data['name'],
            'vendor_type' => $data['vendor_type'] ?? 'counsel',
            'contact_name' => $data['contact_name'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'firm' => $data['firm'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    public function linkVendor(int $caseId, int $vendorId, ?string $roleNote = null): void
    {
        $driver = $this->pdo()->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $stmt = $this->pdo()->prepare(
                'INSERT OR IGNORE INTO legal_case_vendors (case_id, vendor_id, role_note)
                 VALUES (:case_id, :vendor_id, :role_note)'
            );
        } else {
            $stmt = $this->pdo()->prepare(
                'INSERT INTO legal_case_vendors (case_id, vendor_id, role_note)
                 VALUES (:case_id, :vendor_id, :role_note)
                 ON DUPLICATE KEY UPDATE role_note = VALUES(role_note)'
            );
        }
        $stmt->execute([
            'case_id' => $caseId,
            'vendor_id' => $vendorId,
            'role_note' => $roleNote,
        ]);
    }

    public function getVendorsForCase(int $caseId): array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT v.*, lcv.role_note
             FROM legal_vendors v
             JOIN legal_case_vendors lcv ON lcv.vendor_id = v.id
             WHERE lcv.case_id = :caseId
             ORDER BY v.name'
        );
        $stmt->execute(['caseId' => $caseId]);

        return $stmt->fetchAll();
    }

    public function markMilestoneReminderSent(int $id): void
    {
        $stmt = $this->pdo()->prepare(
            'UPDATE legal_case_milestones SET reminder_sent_at = CURRENT_TIMESTAMP WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
    }

    /**
     * Cases with budget vs actual cost variance.
     *
     * @return list<array{id:int,case_number:string,title:string,budget_amount:?float,total_cost:float,variance:?float}>
     */
    public function budgetRollup(): array
    {
        $sql = "SELECT lc.id, lc.case_number, lc.title, lc.budget_amount,
                       COALESCE(SUM(c.amount), 0) AS total_cost,
                       CASE
                           WHEN lc.budget_amount IS NULL THEN NULL
                           ELSE lc.budget_amount - COALESCE(SUM(c.amount), 0)
                       END AS variance
                FROM legal_cases lc
                LEFT JOIN legal_case_costs c ON c.case_id = lc.id
                WHERE lc.deleted_at IS NULL
                GROUP BY lc.id, lc.case_number, lc.title, lc.budget_amount
                ORDER BY lc.created_at DESC";

        return $this->pdo()->query($sql)->fetchAll();
    }

    public function getParties(int $caseId): array
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM legal_case_parties WHERE case_id = :caseId ORDER BY role');
        $stmt->execute(['caseId' => $caseId]);

        return $stmt->fetchAll();
    }

    public function addParty(array $data): int
    {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO legal_case_parties (case_id, party_name, role, contact_info)
             VALUES (:case_id, :party_name, :role, :contact_info)'
        );
        $stmt->execute([
            'case_id' => $data['case_id'],
            'party_name' => $data['party_name'],
            'role' => $data['role'],
            'contact_info' => $data['contact_info'] ?? null,
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    public function getMilestones(int $caseId): array
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM legal_case_milestones WHERE case_id = :caseId ORDER BY milestone_date ASC');
        $stmt->execute(['caseId' => $caseId]);

        return $stmt->fetchAll();
    }

    public function addMilestone(array $data): int
    {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO legal_case_milestones (case_id, milestone_type, milestone_date, description, is_completed)
             VALUES (:case_id, :milestone_type, :milestone_date, :description, 0)'
        );
        $stmt->execute([
            'case_id' => $data['case_id'],
            'milestone_type' => $data['milestone_type'],
            'milestone_date' => $data['milestone_date'],
            'description' => $data['description'] ?? null,
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    public function toggleMilestone(int $milestoneId, int $isCompleted): void
    {
        $stmt = $this->pdo()->prepare('UPDATE legal_case_milestones SET is_completed = :status WHERE id = :id');
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
             WHERE lcd.case_id = :caseId AND d.deleted_at IS NULL"
        );
        $stmt->execute(['caseId' => $caseId]);

        return $stmt->fetchAll();
    }

    public function addDocument(int $caseId, int $documentId): void
    {
        $driver = $this->pdo()->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $stmt = $this->pdo()->prepare(
                'INSERT OR IGNORE INTO legal_case_documents (case_id, document_id) VALUES (:caseId, :documentId)'
            );
        } else {
            $stmt = $this->pdo()->prepare(
                'INSERT INTO legal_case_documents (case_id, document_id) VALUES (:caseId, :documentId)
                 ON DUPLICATE KEY UPDATE document_id = document_id'
            );
        }
        $stmt->execute(['caseId' => $caseId, 'documentId' => $documentId]);
    }

    public function getCosts(int $caseId): array
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM legal_case_costs WHERE case_id = :caseId ORDER BY incurred_date DESC');
        $stmt->execute(['caseId' => $caseId]);

        return $stmt->fetchAll();
    }

    public function addCost(array $data): int
    {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO legal_case_costs (case_id, description, amount, incurred_date)
             VALUES (:case_id, :description, :amount, :incurred_date)'
        );
        $stmt->execute([
            'case_id' => $data['case_id'],
            'description' => $data['description'],
            'amount' => (float) $data['amount'],
            'incurred_date' => $data['incurred_date'],
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    public function countActiveCases(): int
    {
        return (int) $this->pdo()
            ->query("SELECT COUNT(*) FROM legal_cases WHERE status IN ('open','in_progress') AND deleted_at IS NULL")
            ->fetchColumn();
    }

    public function upcomingMilestonesNeedingReminder(string $untilDatetime): array
    {
        $stmt = $this->pdo()->prepare(
            "SELECT m.*, lc.title AS case_title, lc.case_number, lc.assigned_counsel_id, e.user_id AS counsel_user_id
             FROM legal_case_milestones m
             JOIN legal_cases lc ON lc.id = m.case_id AND lc.deleted_at IS NULL
             LEFT JOIN employees e ON e.id = lc.assigned_counsel_id
             WHERE COALESCE(m.is_completed, 0) = 0
               AND m.reminder_sent_at IS NULL
               AND m.milestone_date <= :until
               AND m.milestone_date >= CURRENT_TIMESTAMP"
        );
        $stmt->execute(['until' => $untilDatetime]);

        return $stmt->fetchAll();
    }

    public function legalManagerUserIds(): array
    {
        $stmt = $this->pdo()->query(
            "SELECT DISTINCT u.id
             FROM users u
             JOIN role_permissions rp ON rp.role_id = u.role_id
             JOIN permissions p ON p.id = rp.permission_id
             WHERE p.code = 'legal.manage'
               AND u.status = 'active'
               AND u.deleted_at IS NULL"
        );

        return array_map('intval', array_column($stmt->fetchAll(), 'id'));
    }
}
