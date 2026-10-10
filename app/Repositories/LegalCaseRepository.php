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

        return $this->insertId('legal_cases');
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

        return $this->insertId('legal_vendors');
    }

    public function linkVendor(int $caseId, int $vendorId, ?string $roleNote = null): void
    {
        $driver = $this->pdo()->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $stmt = $this->pdo()->prepare(
                'INSERT OR IGNORE INTO legal_case_vendors (case_id, vendor_id, role_note)
                 VALUES (:case_id, :vendor_id, :role_note)'
            );
        } elseif ($driver === 'pgsql') {
            $stmt = $this->pdo()->prepare(
                'INSERT INTO legal_case_vendors (case_id, vendor_id, role_note)
                 VALUES (:case_id, :vendor_id, :role_note)
                 ON CONFLICT (case_id, vendor_id) DO UPDATE SET role_note = EXCLUDED.role_note'
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

        return $this->insertId('legal_case_parties');
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
             VALUES (:case_id, :milestone_type, :milestone_date, :description, :is_completed)'
        );
        $stmt->execute([
            'case_id' => $data['case_id'],
            'milestone_type' => $data['milestone_type'],
            'milestone_date' => $data['milestone_date'],
            'description' => $data['description'] ?? null,
            'is_completed' => $this->boolParam(false),
        ]);

        return $this->insertId('legal_case_milestones');
    }

    public function toggleMilestone(int $milestoneId, int $isCompleted): void
    {
        $stmt = $this->pdo()->prepare('UPDATE legal_case_milestones SET is_completed = :status WHERE id = :id');
        $stmt->execute(['status' => $this->boolParam((bool) $isCompleted), 'id' => $milestoneId]);
    }

    public function getDeadlines(int $caseId): array
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM legal_case_deadlines WHERE case_id = :caseId ORDER BY due_date ASC');
        $stmt->execute(['caseId' => $caseId]);

        return $stmt->fetchAll();
    }

    public function addDeadline(array $data): int
    {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO legal_case_deadlines (case_id, title, due_date, description, is_completed, created_at, updated_at)
             VALUES (:case_id, :title, :due_date, :description, :is_completed, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)'
        );
        $stmt->execute([
            'case_id' => $data['case_id'],
            'title' => $data['title'],
            'due_date' => $data['due_date'],
            'description' => $data['description'] ?? null,
            'is_completed' => $this->boolParam(false),
        ]);

        return $this->insertId('legal_case_deadlines');
    }

    public function toggleDeadline(int $deadlineId, int $isCompleted): void
    {
        $stmt = $this->pdo()->prepare('UPDATE legal_case_deadlines SET is_completed = :status, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
        $stmt->execute(['status' => $this->boolParam((bool) $isCompleted), 'id' => $deadlineId]);
    }

    public function getUpcomingDeadlines(int $daysAhead = 30): array
    {
        $driver = $this->pdo()->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'pgsql') {
            $stmt = $this->pdo()->prepare(
                "SELECT d.*, lc.title AS case_title, lc.case_number, e.user_id AS counsel_user_id
                 FROM legal_case_deadlines d
                 JOIN legal_cases lc ON lc.id = d.case_id AND lc.deleted_at IS NULL
                 LEFT JOIN employees e ON e.id = lc.assigned_counsel_id
                 WHERE {$this->isFalseCondition('d.is_completed')}
                   AND d.due_date BETWEEN CURRENT_DATE AND (CURRENT_DATE + (:days || ' days')::interval)
                 ORDER BY d.due_date ASC"
            );
        } else {
            $stmt = $this->pdo()->prepare(
                "SELECT d.*, lc.title AS case_title, lc.case_number, e.user_id AS counsel_user_id
                 FROM legal_case_deadlines d
                 JOIN legal_cases lc ON lc.id = d.case_id AND lc.deleted_at IS NULL
                 LEFT JOIN employees e ON e.id = lc.assigned_counsel_id
                 WHERE {$this->isFalseCondition('d.is_completed')}
                   AND d.due_date BETWEEN CURRENT_DATE AND DATE_ADD(CURRENT_DATE, INTERVAL :days DAY)
                 ORDER BY d.due_date ASC"
            );
        }
        $stmt->execute(['days' => $daysAhead]);
        return $stmt->fetchAll();
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
        } elseif ($driver === 'pgsql') {
            $stmt = $this->pdo()->prepare(
                'INSERT INTO legal_case_documents (case_id, document_id)
                 VALUES (:caseId, :documentId)
                 ON CONFLICT (case_id, document_id) DO NOTHING'
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

        return $this->insertId('legal_case_costs');
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
             WHERE {$this->coalesceFalseCondition('m.is_completed')}
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

    // ==================== OUTCOME/SETTLEMENT TRACKING ====================

    public function createOutcome(array $data): int
    {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO legal_case_outcomes 
             (case_id, outcome_type, outcome_date, description, settlement_amount, settlement_terms, 
              settlement_date, judgment_summary, awarded_amount, is_appealed, appeal_reason, 
              appeal_deadline, recorded_by, created_at, updated_at)
             VALUES (:case_id, :outcome_type, :outcome_date, :description, :settlement_amount, 
                     :settlement_terms, :settlement_date, :judgment_summary, :awarded_amount, 
                     :is_appealed, :appeal_reason, :appeal_deadline, :recorded_by, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)'
        );
        $stmt->execute([
            'case_id' => $data['case_id'],
            'outcome_type' => $data['outcome_type'],
            'outcome_date' => $data['outcome_date'],
            'description' => $data['description'] ?? null,
            'settlement_amount' => $data['settlement_amount'] ?? null,
            'settlement_terms' => $data['settlement_terms'] ?? null,
            'settlement_date' => $data['settlement_date'] ?? null,
            'judgment_summary' => $data['judgment_summary'] ?? null,
            'awarded_amount' => $data['awarded_amount'] ?? null,
            'is_appealed' => $this->boolParam((bool) ($data['is_appealed'] ?? false)),
            'appeal_reason' => $data['appeal_reason'] ?? null,
            'appeal_deadline' => $data['appeal_deadline'] ?? null,
            'recorded_by' => $data['recorded_by'] ?? null,
        ]);

        return $this->insertId('legal_case_outcomes');
    }

    public function getOutcome(int $caseId): ?array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT * FROM legal_case_outcomes 
             WHERE case_id = :caseId AND deleted_at IS NULL
             ORDER BY created_at DESC LIMIT 1'
        );
        $stmt->execute(['caseId' => $caseId]);
        return $stmt->fetch() ?: null;
    }

    public function update(int $caseId, array $data): void
    {
        $updates = [];
        $bindings = ['id' => $caseId];
        
        foreach ($data as $key => $value) {
            $updates[] = "{$key} = :{$key}";
            $bindings[$key] = $value;
        }
        
        if (empty($updates)) return;
        
        $sql = "UPDATE legal_cases SET " . implode(', ', $updates) . " WHERE id = :id";
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($this->normalizeBindings($bindings));
    }

    // ==================== CONFLICT-OF-INTEREST TRACKING ====================

    public function createPartyConflict(array $data): int
    {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO legal_case_party_conflicts 
             (case_id, party_a_id, party_b_id, conflict_type, description, severity, 
              conflict_flagged, flagged_by, flagged_at, resolution_status, created_at, updated_at)
             VALUES (:case_id, :party_a_id, :party_b_id, :conflict_type, :description, :severity, 
                     :conflict_flagged, :flagged_by, :flagged_at, :resolution_status, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)'
        );
        $stmt->execute([
            'case_id' => $data['case_id'],
            'party_a_id' => $data['party_a_id'],
            'party_b_id' => $data['party_b_id'],
            'conflict_type' => $data['conflict_type'],
            'description' => $data['description'] ?? null,
            'severity' => $data['severity'] ?? 'medium',
            'conflict_flagged' => $this->boolParam((bool) ($data['conflict_flagged'] ?? true)),
            'flagged_by' => $data['flagged_by'] ?? null,
            'flagged_at' => $data['flagged_at'] ?? null,
            'resolution_status' => $data['resolution_status'] ?? 'pending',
        ]);

        return $this->insertId('legal_case_party_conflicts');
    }

    public function updatePartyConflict(int $conflictId, array $data): void
    {
        $updates = [];
        $bindings = ['id' => $conflictId];
        
        foreach ($data as $key => $value) {
            $updates[] = "{$key} = :{$key}";
            $bindings[$key] = $value;
        }
        
        $bindings['updated_at'] = date('Y-m-d H:i:s');
        $updates[] = "updated_at = :updated_at";
        
        if (empty($updates)) return;
        
        $sql = "UPDATE legal_case_party_conflicts SET " . implode(', ', $updates) . " WHERE id = :id";
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($this->normalizeBindings($bindings));
    }

    public function getPartyConflicts(int $caseId): array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT pc.*,
                    pa.party_name AS party_a_name,
                    pb.party_name AS party_b_name,
                    u.full_name AS flagged_by_name
             FROM legal_case_party_conflicts pc
             LEFT JOIN legal_case_parties pa ON pa.id = pc.party_a_id
             LEFT JOIN legal_case_parties pb ON pb.id = pc.party_b_id
             LEFT JOIN users u ON u.id = pc.flagged_by
             WHERE pc.case_id = :caseId
             ORDER BY pc.severity DESC, pc.created_at DESC'
        );
        $stmt->execute(['caseId' => $caseId]);
        return $stmt->fetchAll();
    }

    public function createCounselConflict(array $data): int
    {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO legal_case_counsel_conflicts 
             (case_id, counsel_id, external_party, conflict_type, description, severity, 
              conflict_verified, status, created_at, updated_at)
             VALUES (:case_id, :counsel_id, :external_party, :conflict_type, :description, :severity, 
                     :conflict_verified, :status, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)'
        );
        $stmt->execute([
            'case_id' => $data['case_id'],
            'counsel_id' => $data['counsel_id'] ?? null,
            'external_party' => $data['external_party'] ?? null,
            'conflict_type' => $data['conflict_type'],
            'description' => $data['description'] ?? null,
            'severity' => $data['severity'] ?? 'medium',
            'conflict_verified' => $this->boolParam((bool) ($data['conflict_verified'] ?? false)),
            'status' => $data['status'] ?? 'under_review',
        ]);

        return $this->insertId('legal_case_counsel_conflicts');
    }

    public function updateCounselConflict(int $conflictId, array $data): void
    {
        $updates = [];
        $bindings = ['id' => $conflictId];
        
        foreach ($data as $key => $value) {
            $updates[] = "{$key} = :{$key}";
            $bindings[$key] = $value;
        }
        
        $bindings['updated_at'] = date('Y-m-d H:i:s');
        $updates[] = "updated_at = :updated_at";
        
        if (empty($updates)) return;
        
        $sql = "UPDATE legal_case_counsel_conflicts SET " . implode(', ', $updates) . " WHERE id = :id";
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($this->normalizeBindings($bindings));
    }

    public function getCounselConflicts(int $caseId): array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT cc.*,
                    e.full_name AS counsel_name,
                    u.full_name AS verified_by_name
             FROM legal_case_counsel_conflicts cc
             LEFT JOIN employees e ON e.id = cc.counsel_id
             LEFT JOIN users u ON u.id = cc.verified_by
             WHERE cc.case_id = :caseId
             ORDER BY cc.severity DESC, cc.created_at DESC'
        );
        $stmt->execute(['caseId' => $caseId]);
        return $stmt->fetchAll();
    }

    /**
     * Find parties on other open cases whose names match parties on this case.
     *
     * @return list<array{party_name: string, party_id: int, other_case_id: int, other_case_number: string, other_case_title: string, other_party_id: int}>
     */
    public function findCrossCasePartyNameMatches(int $caseId): array
    {
        $stmt = $this->pdo()->prepare(
            "SELECT p.party_name, p.id AS party_id,
                    op.id AS other_party_id,
                    oc.id AS other_case_id,
                    oc.case_number AS other_case_number,
                    oc.title AS other_case_title
             FROM legal_case_parties p
             JOIN legal_case_parties op
               ON LOWER(TRIM(op.party_name)) = LOWER(TRIM(p.party_name))
              AND op.case_id != p.case_id
             JOIN legal_cases oc ON oc.id = op.case_id AND oc.deleted_at IS NULL
             WHERE p.case_id = :caseId
               AND oc.status NOT IN ('won', 'lost', 'settled', 'dismissed', 'closed')
             ORDER BY p.party_name"
        );
        $stmt->execute(['caseId' => $caseId]);

        return $stmt->fetchAll();
    }

    /**
     * Find other open cases sharing the same assigned counsel.
     *
     * @return list<array{other_case_id: int, other_case_number: string, other_case_title: string, counsel_id: int, counsel_name: string}>
     */
    public function findSharedCounselCases(int $caseId): array
    {
        $case = $this->find($caseId);
        if (! $case || empty($case['assigned_counsel_id'])) {
            return [];
        }

        $stmt = $this->pdo()->prepare(
            "SELECT lc.id AS other_case_id, lc.case_number AS other_case_number,
                    lc.title AS other_case_title,
                    lc.assigned_counsel_id AS counsel_id,
                    e.full_name AS counsel_name
             FROM legal_cases lc
             LEFT JOIN employees e ON e.id = lc.assigned_counsel_id
             WHERE lc.assigned_counsel_id = :counselId
               AND lc.id != :caseId
               AND lc.deleted_at IS NULL
               AND lc.status NOT IN ('won', 'lost', 'settled', 'dismissed', 'closed')"
        );
        $stmt->execute([
            'counselId' => (int) $case['assigned_counsel_id'],
            'caseId' => $caseId,
        ]);

        return $stmt->fetchAll();
    }

    public function allEmployeeDepartures(): array
    {
        return $this->pdo()->query(
            "SELECT d.*, doc.title AS source_document_title, doc.document_number AS source_document_number,
                    u.full_name AS recorded_by_name
             FROM legal_employee_departures d
             JOIN documents doc ON doc.id = d.source_document_id
             LEFT JOIN users u ON u.id = d.recorded_by
             ORDER BY d.effective_date DESC, d.id DESC"
        )->fetchAll();
    }

    public function sourceDocument(int $documentId): ?array
    {
        $stmt = $this->pdo()->prepare(
            "SELECT d.id, d.status, d.deleted_at, dc.name AS category_name
             FROM documents d LEFT JOIN document_categories dc ON dc.id = d.category_id
             WHERE d.id = :id"
        );
        $stmt->execute(['id' => $documentId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function createEmployeeDeparture(array $data): int
    {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO legal_employee_departures
                (employee_name, departure_type, effective_date, source_document_id, notes, recorded_by)
             VALUES (:employee_name, :departure_type, :effective_date, :source_document_id, :notes, :recorded_by)'
        );
        $stmt->execute([
            'employee_name' => $data['employee_name'],
            'departure_type' => $data['departure_type'],
            'effective_date' => $data['effective_date'],
            'source_document_id' => $data['source_document_id'],
            'notes' => $data['notes'] ?? null,
            'recorded_by' => $data['recorded_by'] ?? null,
        ]);

        return $this->insertId('legal_employee_departures');
    }
}
