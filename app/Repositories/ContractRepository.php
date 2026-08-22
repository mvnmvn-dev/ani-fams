<?php

namespace App\Repositories;

use App\Support\DepartmentFilter;
use PDO;

class ContractRepository
{
    use UsesPdo;

    public function all(int $userId, bool $canManageAll): array
    {
        [$deptSql, $deptBindings] = DepartmentFilter::sqlOnUserAlias('u');

        if ($canManageAll) {
            $sql = "SELECT c.*, ct.name AS type_name, u.full_name AS owner_name
                    FROM contracts c
                    LEFT JOIN contract_types ct ON ct.id = c.contract_type_id
                    JOIN users u ON u.id = c.owner_id
                    WHERE c.deleted_at IS NULL
                      AND u.deleted_at IS NULL
                      {$deptSql}
                    ORDER BY c.created_at DESC";
            if ($deptBindings === []) {
                return $this->pdo()->query($sql)->fetchAll();
            }
            $stmt = $this->pdo()->prepare($sql);
            $stmt->execute($deptBindings);

            return $stmt->fetchAll();
        }

        $sql = "SELECT DISTINCT c.*, ct.name AS type_name, u.full_name AS owner_name
                FROM contracts c
                LEFT JOIN contract_types ct ON ct.id = c.contract_type_id
                JOIN users u ON u.id = c.owner_id
                LEFT JOIN contract_approvals ca ON ca.contract_id = c.id
                WHERE c.deleted_at IS NULL
                  AND u.deleted_at IS NULL
                  AND (c.owner_id = :owner_id OR ca.approver_id = :approver_id)
                  {$deptSql}
                ORDER BY c.created_at DESC";
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute(array_merge([
            'owner_id' => $userId,
            'approver_id' => $userId,
        ], $deptBindings));

        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo()->prepare(
            "SELECT c.*, ct.name AS type_name, u.full_name AS owner_name, lc.title AS case_title
             FROM contracts c
             LEFT JOIN contract_types ct ON ct.id = c.contract_type_id
             JOIN users u ON u.id = c.owner_id
             LEFT JOIN legal_cases lc ON lc.id = c.related_case_id
             WHERE c.id = :id AND c.deleted_at IS NULL"
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function softDelete(int $id): void
    {
        $stmt = $this->pdo()->prepare('UPDATE contracts SET deleted_at = CURRENT_TIMESTAMP WHERE id = :id AND deleted_at IS NULL');
        $stmt->execute(['id' => $id]);
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo()->prepare(
            "INSERT INTO contracts (contract_number, title, contract_type_id, template_id, counterparty_name, counterparty_contact,
                                    owner_id, value_amount, currency, start_date, end_date, auto_renew, renewal_notice_days, status, related_case_id)
             VALUES (:contract_number, :title, :contract_type_id, :template_id, :counterparty_name, :counterparty_contact,
                     :owner_id, :value_amount, :currency, :start_date, :end_date, :auto_renew, :renewal_notice_days, 'draft', :related_case_id)"
        );
        $stmt->execute([
            'contract_number' => $data['contract_number'],
            'title' => $data['title'],
            'contract_type_id' => ! empty($data['contract_type_id']) ? (int) $data['contract_type_id'] : null,
            'template_id' => ! empty($data['template_id']) ? (int) $data['template_id'] : null,
            'counterparty_name' => $data['counterparty_name'],
            'counterparty_contact' => $data['counterparty_contact'] ?? null,
            'owner_id' => $data['owner_id'],
            'value_amount' => isset($data['value_amount']) && $data['value_amount'] !== '' && $data['value_amount'] !== null
                ? (float) $data['value_amount'] : null,
            'currency' => $data['currency'] ?? 'PHP',
            'start_date' => $data['start_date'],
            'end_date' => ! empty($data['end_date']) ? $data['end_date'] : null,
            'auto_renew' => $this->boolParam(! empty($data['auto_renew'])),
            'renewal_notice_days' => ! empty($data['renewal_notice_days']) ? (int) $data['renewal_notice_days'] : 30,
            'related_case_id' => ! empty($data['related_case_id']) ? (int) $data['related_case_id'] : null,
        ]);

        return $this->insertId('contracts');
    }

    public function allTemplates(): array
    {
        return $this->pdo()->query(
            "SELECT t.*, ct.name AS type_name, u.full_name AS creator_name
             FROM contract_templates t
             LEFT JOIN contract_types ct ON ct.id = t.contract_type_id
             LEFT JOIN users u ON u.id = t.created_by
             WHERE {$this->isTrueCondition('t.is_active')}
             ORDER BY t.name ASC"
        )->fetchAll();
    }

    public function createTemplate(array $data): int
    {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO contract_templates (name, contract_type_id, body_text, default_value, is_active, created_by)
             VALUES (:name, :contract_type_id, :body_text, :default_value, :is_active, :created_by)'
        );
        $stmt->execute([
            'name' => $data['name'],
            'contract_type_id' => ! empty($data['contract_type_id']) ? (int) $data['contract_type_id'] : null,
            'body_text' => $data['body_text'] ?? '',
            'default_value' => isset($data['default_value']) && $data['default_value'] !== '' && $data['default_value'] !== null
                ? (float) $data['default_value'] : null,
            'is_active' => $this->boolParam(isset($data['is_active']) ? (bool) $data['is_active'] : true),
            'created_by' => ! empty($data['created_by']) ? (int) $data['created_by'] : null,
        ]);

        return $this->insertId('contract_templates');
    }

    public function findTemplate(int $id): ?array
    {
        $stmt = $this->pdo()->prepare(
            "SELECT t.*, ct.name AS type_name
             FROM contract_templates t
             LEFT JOIN contract_types ct ON ct.id = t.contract_type_id
             WHERE t.id = :id"
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function allChains(): array
    {
        $chains = $this->pdo()->query(
            "SELECT c.*, ct.name AS type_name
             FROM contract_approval_chains c
             LEFT JOIN contract_types ct ON ct.id = c.contract_type_id
             ORDER BY c.is_default DESC, c.name ASC"
        )->fetchAll();

        foreach ($chains as &$chain) {
            $chain['steps'] = $this->getChainSteps((int) $chain['id']);
        }
        unset($chain);

        return $chains;
    }

    public function createChain(string $name, ?int $typeId, bool $isDefault, array $approverIds): int
    {
        if ($isDefault && $typeId) {
            $clear = $this->pdo()->prepare(
                'UPDATE contract_approval_chains SET is_default = '.$this->boolLiteral(false).' WHERE contract_type_id = :typeId'
            );
            $clear->execute(['typeId' => $typeId]);
        }

        $stmt = $this->pdo()->prepare(
            'INSERT INTO contract_approval_chains (name, contract_type_id, is_default)
             VALUES (:name, :contract_type_id, :is_default)'
        );
        $stmt->execute([
            'name' => $name,
            'contract_type_id' => $typeId ?: null,
            'is_default' => $this->boolParam($isDefault),
        ]);
        $chainId = $this->insertId('contract_approval_chains');

        $stepStmt = $this->pdo()->prepare(
            'INSERT INTO contract_approval_chain_steps (chain_id, approver_id, sequence_order)
             VALUES (:chain_id, :approver_id, :sequence_order)'
        );
        $seq = 1;
        foreach ($approverIds as $approverId) {
            $approverId = (int) $approverId;
            if ($approverId <= 0) {
                continue;
            }
            $stepStmt->execute([
                'chain_id' => $chainId,
                'approver_id' => $approverId,
                'sequence_order' => $seq++,
            ]);
        }

        return $chainId;
    }

    public function getChainSteps(int $chainId): array
    {
        $stmt = $this->pdo()->prepare(
            "SELECT s.*, u.full_name AS approver_name, u.email AS approver_email
             FROM contract_approval_chain_steps s
             JOIN users u ON u.id = s.approver_id
             WHERE s.chain_id = :chainId
             ORDER BY s.sequence_order ASC"
        );
        $stmt->execute(['chainId' => $chainId]);

        return $stmt->fetchAll();
    }

    public function findDefaultChainForType(int $typeId): ?array
    {
        $stmt = $this->pdo()->prepare(
            "SELECT * FROM contract_approval_chains
             WHERE contract_type_id = :typeId AND {$this->isTrueCondition('is_default')}
             ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute(['typeId' => $typeId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findChain(int $id): ?array
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM contract_approval_chains WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function spendRollupByType(): array
    {
        return $this->pdo()->query(
            "SELECT COALESCE(ct.name, 'General') AS type_name,
                    COUNT(*) AS contract_count,
                    COALESCE(SUM(c.value_amount), 0) AS total_value
             FROM contracts c
             LEFT JOIN contract_types ct ON ct.id = c.contract_type_id
             WHERE c.deleted_at IS NULL
             GROUP BY COALESCE(ct.name, 'General')
             ORDER BY total_value DESC"
        )->fetchAll();
    }

    public function findBySignatureToken(string $token): ?array
    {
        $stmt = $this->pdo()->prepare(
            "SELECT c.*, ct.name AS type_name, u.full_name AS owner_name
             FROM contracts c
             LEFT JOIN contract_types ct ON ct.id = c.contract_type_id
             JOIN users u ON u.id = c.owner_id
             WHERE c.signature_token = :token AND c.deleted_at IS NULL"
        );
        $stmt->execute(['token' => $token]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function setSignature(int $contractId, ?string $token, ?string $signedAt, ?string $name, ?string $ip, ?string $signaturePath = null): void
    {
        $stmt = $this->pdo()->prepare(
            'UPDATE contracts
             SET signature_token = :token,
                 signed_at = :signed_at,
                 signed_by_name = :signed_by_name,
                 signed_ip = :signed_ip,
                 signature_path = COALESCE(:signature_path, signature_path)
             WHERE id = :id'
        );
        $stmt->execute([
            'token' => $token,
            'signed_at' => $signedAt,
            'signed_by_name' => $name,
            'signed_ip' => $ip,
            'signature_path' => $signaturePath,
            'id' => $contractId,
        ]);
    }

    public function findAmendment(int $contractId, int $amendmentId): ?array
    {
        $stmt = $this->pdo()->prepare(
            "SELECT ca.*, u.full_name AS creator_name, d.title AS document_title, d.current_version_id, dv.file_path
             FROM contract_amendments ca
             JOIN users u ON u.id = ca.created_by
             LEFT JOIN documents d ON d.id = ca.document_id AND d.deleted_at IS NULL
             LEFT JOIN document_versions dv ON dv.id = d.current_version_id
             WHERE ca.contract_id = :contractId AND ca.id = :amendmentId"
        );
        $stmt->execute(['contractId' => $contractId, 'amendmentId' => $amendmentId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function allTypes(): array
    {
        return $this->pdo()->query('SELECT * FROM contract_types ORDER BY name')->fetchAll();
    }

    public function getApprovals(int $contractId): array
    {
        $stmt = $this->pdo()->prepare(
            "SELECT ca.*, u.full_name AS approver_name, u.email AS approver_email
             FROM contract_approvals ca
             JOIN users u ON u.id = ca.approver_id
             WHERE ca.contract_id = :contractId
             ORDER BY ca.sequence_order ASC"
        );
        $stmt->execute(['contractId' => $contractId]);

        return $stmt->fetchAll();
    }

    public function addApprovalStep(array $data): void
    {
        $stmt = $this->pdo()->prepare(
            "INSERT INTO contract_approvals (contract_id, approver_id, sequence_order, status)
             VALUES (:contract_id, :approver_id, :sequence_order, 'pending')"
        );
        $stmt->execute([
            'contract_id' => $data['contract_id'],
            'approver_id' => $data['approver_id'],
            'sequence_order' => $data['sequence_order'],
        ]);
    }

    public function updateApprovalStepStatus(int $approvalId, string $status, ?string $comments): void
    {
        $stmt = $this->pdo()->prepare(
            'UPDATE contract_approvals SET status = :status, comments = :comments, decided_at = CURRENT_TIMESTAMP WHERE id = :id'
        );
        $stmt->execute(['status' => $status, 'comments' => $comments, 'id' => $approvalId]);
    }

    public function updateContractStatus(int $contractId, string $status): void
    {
        $stmt = $this->pdo()->prepare('UPDATE contracts SET status = :status WHERE id = :id');
        $stmt->execute(['status' => $status, 'id' => $contractId]);
    }

    public function getAmendments(int $contractId): array
    {
        $stmt = $this->pdo()->prepare(
            "SELECT ca.*, u.full_name AS creator_name, d.title AS document_title, d.current_version_id, dv.file_path
             FROM contract_amendments ca
             JOIN users u ON u.id = ca.created_by
             LEFT JOIN documents d ON d.id = ca.document_id AND d.deleted_at IS NULL
             LEFT JOIN document_versions dv ON dv.id = d.current_version_id
             WHERE ca.contract_id = :contractId
             ORDER BY ca.amendment_number ASC"
        );
        $stmt->execute(['contractId' => $contractId]);

        return $stmt->fetchAll();
    }

    public function createAmendment(array $data): int
    {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO contract_amendments (contract_id, amendment_number, description, effective_date, document_id, created_by)
             VALUES (:contract_id, :amendment_number, :description, :effective_date, :document_id, :created_by)'
        );
        $stmt->execute([
            'contract_id' => $data['contract_id'],
            'amendment_number' => $data['amendment_number'],
            'description' => $data['description'],
            'effective_date' => $data['effective_date'],
            'document_id' => $data['document_id'] ?: null,
            'created_by' => $data['created_by'],
        ]);

        return $this->insertId('contract_amendments');
    }

    public function getDocuments(int $contractId): array
    {
        $stmt = $this->pdo()->prepare(
            "SELECT d.*, dc.name AS category_name, dv.file_path
             FROM documents d
             JOIN contract_documents cd ON cd.document_id = d.id
             LEFT JOIN document_categories dc ON dc.id = d.category_id
             LEFT JOIN document_versions dv ON dv.id = d.current_version_id
             WHERE cd.contract_id = :contractId AND d.deleted_at IS NULL"
        );
        $stmt->execute(['contractId' => $contractId]);

        return $stmt->fetchAll();
    }

    public function addDocument(int $contractId, int $documentId): void
    {
        $driver = $this->pdo()->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $stmt = $this->pdo()->prepare(
                'INSERT OR IGNORE INTO contract_documents (contract_id, document_id) VALUES (:contractId, :documentId)'
            );
        } elseif ($driver === 'pgsql') {
            $stmt = $this->pdo()->prepare(
                'INSERT INTO contract_documents (contract_id, document_id)
                 VALUES (:contractId, :documentId)
                 ON CONFLICT (contract_id, document_id) DO NOTHING'
            );
        } else {
            $stmt = $this->pdo()->prepare(
                'INSERT INTO contract_documents (contract_id, document_id) VALUES (:contractId, :documentId)
                 ON DUPLICATE KEY UPDATE document_id = document_id'
            );
        }
        $stmt->execute(['contractId' => $contractId, 'documentId' => $documentId]);
    }

    public function countActiveContracts(): int
    {
        return (int) $this->pdo()
            ->query("SELECT COUNT(*) FROM contracts WHERE status = 'active' AND deleted_at IS NULL")
            ->fetchColumn();
    }

    // ==================== OBLIGATIONS ====================

    public function getObligations(int $contractId): array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT o.*, u.full_name AS completed_by_name
             FROM contract_obligations o
             LEFT JOIN users u ON u.id = o.completed_by
             WHERE o.contract_id = :contractId
             ORDER BY o.is_completed ASC, o.due_date IS NULL, o.due_date ASC, o.id ASC'
        );
        $stmt->execute(['contractId' => $contractId]);

        return $stmt->fetchAll();
    }

    public function createObligation(array $data): int
    {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO contract_obligations
             (contract_id, title, description, responsible_party, due_date, is_completed, created_by, created_at, updated_at)
             VALUES (:contract_id, :title, :description, :responsible_party, :due_date, :is_completed, :created_by, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)'
        );
        $stmt->execute([
            'contract_id' => $data['contract_id'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'responsible_party' => $data['responsible_party'] ?? null,
            'due_date' => $data['due_date'] ?? null,
            'is_completed' => $this->boolParam(false),
            'created_by' => $data['created_by'] ?? null,
        ]);

        return $this->insertId('contract_obligations');
    }

    public function toggleObligation(int $obligationId, bool $completed, ?int $userId): void
    {
        $stmt = $this->pdo()->prepare(
            'UPDATE contract_obligations
             SET is_completed = :completed,
                 completed_at = '.$this->caseWhenParamTrue(':completed2', 'CURRENT_TIMESTAMP').',
                 completed_by = '.$this->caseWhenParamTrue(':completed3', ':userId').',
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $flag = $this->boolParam($completed);
        $stmt->execute([
            'completed' => $flag,
            'completed2' => $flag,
            'completed3' => $flag,
            'userId' => $userId,
            'id' => $obligationId,
        ]);
    }

    // ==================== VENDOR SCORECARDS ====================

    public function getVendorScores(int $contractId): array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT s.*, u.full_name AS scored_by_name
             FROM contract_vendor_scores s
             LEFT JOIN users u ON u.id = s.scored_by
             WHERE s.contract_id = :contractId
             ORDER BY s.scored_at DESC, s.id DESC'
        );
        $stmt->execute(['contractId' => $contractId]);

        return $stmt->fetchAll();
    }

    public function createVendorScore(array $data): int
    {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO contract_vendor_scores
             (contract_id, quality_score, timeliness_score, communication_score, value_score,
              overall_score, notes, scored_at, scored_by, created_at, updated_at)
             VALUES (:contract_id, :quality_score, :timeliness_score, :communication_score, :value_score,
                     :overall_score, :notes, :scored_at, :scored_by, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)'
        );
        $stmt->execute([
            'contract_id' => $data['contract_id'],
            'quality_score' => $data['quality_score'],
            'timeliness_score' => $data['timeliness_score'],
            'communication_score' => $data['communication_score'],
            'value_score' => $data['value_score'],
            'overall_score' => $data['overall_score'],
            'notes' => $data['notes'] ?? null,
            'scored_at' => $data['scored_at'] ?? date('Y-m-d'),
            'scored_by' => $data['scored_by'] ?? null,
        ]);

        return $this->insertId('contract_vendor_scores');
    }

    public function vendorScoreRollup(): array
    {
        $sql = "SELECT c.counterparty_name,
                       COUNT(s.id) AS score_count,
                       ROUND(AVG(s.overall_score), 2) AS avg_overall,
                       ROUND(AVG(s.quality_score), 2) AS avg_quality,
                       ROUND(AVG(s.timeliness_score), 2) AS avg_timeliness,
                       ROUND(AVG(s.communication_score), 2) AS avg_communication,
                       ROUND(AVG(s.value_score), 2) AS avg_value
                FROM contract_vendor_scores s
                JOIN contracts c ON c.id = s.contract_id AND c.deleted_at IS NULL
                GROUP BY c.counterparty_name
                ORDER BY avg_overall DESC, score_count DESC
                LIMIT 20";

        try {
            return $this->pdo()->query($sql)->fetchAll();
        } catch (\Throwable $e) {
            return [];
        }
    }
}
