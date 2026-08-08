<?php

namespace App\Repositories;

use App\Support\DepartmentFilter;
use PDO;

class RetentionRepository
{
    use UsesPdo;

    private function docsConcatSql(): string
    {
        $driver = $this->pdo()->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            return 'GROUP_CONCAT(d.title, \', \')';
        }

        return "GROUP_CONCAT(d.title SEPARATOR ', ')";
    }

    public function allPolicies(): array
    {
        return $this->pdo()->query('SELECT * FROM retention_policies ORDER BY name')->fetchAll();
    }

    public function allAssignments(): array
    {
        [$deptSql, $deptBindings] = DepartmentFilter::sqlOnUserAlias('u');
        $sql = "SELECT ra.*, d.title AS document_title, d.document_number,
                       u.full_name AS owner_name, rp.name AS policy_name, rp.disposal_action
                FROM retention_assignments ra
                JOIN documents d ON d.id = ra.document_id
                JOIN users u ON u.id = d.owner_id
                JOIN retention_policies rp ON rp.id = ra.policy_id
                WHERE d.deleted_at IS NULL
                  {$deptSql}
                ORDER BY ra.calculated_disposal_date ASC";
        if ($deptBindings === []) {
            return $this->pdo()->query($sql)->fetchAll();
        }
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($deptBindings);

        return $stmt->fetchAll();
    }

    public function allHolds(): array
    {
        $docsExpr = $this->docsConcatSql();
        $sql = "SELECT lh.*, u.full_name AS placed_by_name, lc.title AS case_title,
                       (SELECT {$docsExpr}
                        FROM legal_hold_documents lhd
                        JOIN documents d ON d.id = lhd.document_id
                        WHERE lhd.legal_hold_id = lh.id AND d.deleted_at IS NULL) AS documents_on_hold
                FROM legal_holds lh
                JOIN users u ON u.id = lh.placed_by
                LEFT JOIN legal_cases lc ON lc.id = lh.related_case_id
                ORDER BY lh.placed_at DESC";

        return $this->pdo()->query($sql)->fetchAll();
    }

    public function createPolicy(array $data): int
    {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO retention_policies (name, record_category, retention_period_months, trigger_event, disposal_action, legal_basis)
             VALUES (:name, :record_category, :retention_period_months, :trigger_event, :disposal_action, :legal_basis)'
        );
        $stmt->execute([
            'name' => $data['name'],
            'record_category' => $data['record_category'],
            'retention_period_months' => (int) $data['retention_period_months'],
            'trigger_event' => $data['trigger_event'] ?? 'creation_date',
            'disposal_action' => $data['disposal_action'] ?? 'review',
            'legal_basis' => $data['legal_basis'] ?? null,
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    public function assignPolicy(array $data): int
    {
        $stmt = $this->pdo()->prepare(
            "INSERT INTO retention_assignments (document_id, policy_id, retention_start_date, calculated_disposal_date, status)
             VALUES (:document_id, :policy_id, :start_date, :disposal_date, 'active')"
        );
        $stmt->execute([
            'document_id' => $data['document_id'],
            'policy_id' => $data['policy_id'],
            'start_date' => $data['retention_start_date'],
            'disposal_date' => $data['calculated_disposal_date'],
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    public function findAssignment(int $id): ?array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT ra.*, rp.disposal_action, ra.document_id
             FROM retention_assignments ra
             JOIN retention_policies rp ON rp.id = ra.policy_id
             WHERE ra.id = :id'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function updateAssignmentStatus(int $id, string $status): void
    {
        $stmt = $this->pdo()->prepare(
            'UPDATE retention_assignments SET status = :status WHERE id = :id'
        );
        $stmt->execute(['status' => $status, 'id' => $id]);
    }

    public function updateDocumentStatus(int $documentId, string $status): void
    {
        $stmt = $this->pdo()->prepare(
            'UPDATE documents SET status = :status WHERE id = :id'
        );
        $stmt->execute(['status' => $status, 'id' => $documentId]);
    }

    public function createDisposalLog(array $data): int
    {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO disposal_logs (retention_assignment_id, action_taken, performed_by, certificate_path)
             VALUES (:assignment_id, :action_taken, :performed_by, :certificate_path)'
        );
        $stmt->execute([
            'assignment_id' => $data['retention_assignment_id'],
            'action_taken' => $data['action_taken'],
            'performed_by' => $data['performed_by'],
            'certificate_path' => $data['certificate_path'] ?? null,
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    public function createLegalHold(array $data): int
    {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO legal_holds (reason, related_case_id, placed_by)
             VALUES (:reason, :related_case_id, :placed_by)'
        );
        $stmt->execute([
            'reason' => $data['reason'],
            'related_case_id' => $data['related_case_id'] ?: null,
            'placed_by' => $data['placed_by'],
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    public function addDocumentToHold(int $holdId, int $documentId): void
    {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO legal_hold_documents (legal_hold_id, document_id) VALUES (:holdId, :docId)'
        );
        $stmt->execute(['holdId' => $holdId, 'docId' => $documentId]);

        $stmt = $this->pdo()->prepare(
            "UPDATE retention_assignments SET status = 'on_legal_hold' WHERE document_id = :docId AND status = 'active'"
        );
        $stmt->execute(['docId' => $documentId]);
    }

    public function releaseLegalHold(int $holdId): void
    {
        $db = $this->pdo();

        $stmt = $db->prepare('SELECT document_id FROM legal_hold_documents WHERE legal_hold_id = :holdId');
        $stmt->execute(['holdId' => $holdId]);
        $docIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $stmt = $db->prepare('UPDATE legal_holds SET released_at = CURRENT_TIMESTAMP WHERE id = :holdId');
        $stmt->execute(['holdId' => $holdId]);

        foreach ($docIds as $docId) {
            if (! $this->isDocumentOnHold((int) $docId)) {
                $stmt = $db->prepare(
                    "UPDATE retention_assignments SET status = 'active' WHERE document_id = :docId AND status = 'on_legal_hold'"
                );
                $stmt->execute(['docId' => $docId]);
            }
        }
    }

    public function isDocumentOnHold(int $documentId): bool
    {
        $sql = 'SELECT COUNT(*) FROM legal_hold_documents lhd
                JOIN legal_holds lh ON lh.id = lhd.legal_hold_id
                WHERE lhd.document_id = :docId AND lh.released_at IS NULL';
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute(['docId' => $documentId]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function allDocumentsWithoutAssignment(): array
    {
        [$deptSql, $deptBindings] = DepartmentFilter::sqlOnUserAlias('u');
        $sql = "SELECT d.id, d.title, d.document_number FROM documents d
                JOIN users u ON u.id = d.owner_id
                LEFT JOIN retention_assignments ra ON ra.document_id = d.id
                WHERE ra.id IS NULL AND d.status = 'active' AND d.deleted_at IS NULL
                  {$deptSql}";
        if ($deptBindings === []) {
            return $this->pdo()->query($sql)->fetchAll();
        }
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($deptBindings);

        return $stmt->fetchAll();
    }

    public function allDocuments(): array
    {
        [$deptSql, $deptBindings] = DepartmentFilter::sqlOnUserAlias('u');
        $sql = "SELECT d.id, d.title, d.document_number
                FROM documents d
                JOIN users u ON u.id = d.owner_id
                WHERE d.status = 'active' AND d.deleted_at IS NULL
                  {$deptSql}";
        if ($deptBindings === []) {
            return $this->pdo()->query($sql)->fetchAll();
        }
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($deptBindings);

        return $stmt->fetchAll();
    }

    public function allLegalCases(): array
    {
        return $this->pdo()->query('SELECT id, title, case_number FROM legal_cases WHERE deleted_at IS NULL ORDER BY title')->fetchAll();
    }

    public function countPendingDisposal(): int
    {
        return (int) $this->pdo()
            ->query("SELECT COUNT(*) FROM retention_assignments WHERE calculated_disposal_date <= CURRENT_DATE AND status = 'active'")
            ->fetchColumn();
    }
}
