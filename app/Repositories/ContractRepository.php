<?php

namespace App\Repositories;

use PDO;

class ContractRepository
{
    use UsesPdo;

    public function all(int $userId, bool $canManageAll): array
    {
        if ($canManageAll) {
            $sql = "SELECT c.*, ct.name AS type_name, u.full_name AS owner_name
                    FROM contracts c
                    LEFT JOIN contract_types ct ON ct.id = c.contract_type_id
                    JOIN users u ON u.id = c.owner_id
                    ORDER BY c.created_at DESC";
            return $this->pdo()->query($sql)->fetchAll();
        } else {
            // Employees only see contracts they own, or are asked to approve
            $sql = "SELECT DISTINCT c.*, ct.name AS type_name, u.full_name AS owner_name
                    FROM contracts c
                    LEFT JOIN contract_types ct ON ct.id = c.contract_type_id
                    JOIN users u ON u.id = c.owner_id
                    LEFT JOIN contract_approvals ca ON ca.contract_id = c.id
                    WHERE c.owner_id = :owner_id OR ca.approver_id = :approver_id
                    ORDER BY c.created_at DESC";
            $stmt = $this->pdo()->prepare($sql);
            $stmt->execute([
                'owner_id'    => $userId,
                'approver_id' => $userId,
            ]);
            return $stmt->fetchAll();
        }
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo()->prepare(
            "SELECT c.*, ct.name AS type_name, u.full_name AS owner_name, lc.title AS case_title
             FROM contracts c
             LEFT JOIN contract_types ct ON ct.id = c.contract_type_id
             JOIN users u ON u.id = c.owner_id
             LEFT JOIN legal_cases lc ON lc.id = c.related_case_id
             WHERE c.id = :id"
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo()->prepare(
            "INSERT INTO contracts (contract_number, title, contract_type_id, counterparty_name, counterparty_contact, 
                                    owner_id, value_amount, currency, start_date, end_date, auto_renew, renewal_notice_days, status, related_case_id)
             VALUES (:contract_number, :title, :contract_type_id, :counterparty_name, :counterparty_contact, 
                     :owner_id, :value_amount, :currency, :start_date, :end_date, :auto_renew, :renewal_notice_days, 'draft', :related_case_id)"
        );
        $stmt->execute([
            'contract_number'     => $data['contract_number'],
            'title'               => $data['title'],
            'contract_type_id'    => !empty($data['contract_type_id']) ? (int) $data['contract_type_id'] : null,
            'counterparty_name'   => $data['counterparty_name'],
            'counterparty_contact'=> $data['counterparty_contact'] ?? null,
            'owner_id'            => $data['owner_id'],
            'value_amount'        => isset($data['value_amount']) && $data['value_amount'] !== '' && $data['value_amount'] !== null
                ? (float) $data['value_amount'] : null,
            'currency'            => $data['currency'] ?? 'PHP',
            'start_date'          => $data['start_date'],
            'end_date'            => !empty($data['end_date']) ? $data['end_date'] : null,
            'auto_renew'          => !empty($data['auto_renew']) ? 1 : 0,
            'renewal_notice_days' => !empty($data['renewal_notice_days']) ? (int) $data['renewal_notice_days'] : 30,
            'related_case_id'     => !empty($data['related_case_id']) ? (int) $data['related_case_id'] : null,
        ]);
        return (int) $this->pdo()->lastInsertId();
    }

    public function allTypes(): array
    {
        return $this->pdo()->query("SELECT * FROM contract_types ORDER BY name")->fetchAll();
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
            'contract_id'    => $data['contract_id'],
            'approver_id'    => $data['approver_id'],
            'sequence_order' => $data['sequence_order'],
        ]);
    }

    public function updateApprovalStepStatus(int $approvalId, string $status, ?string $comments): void
    {
        $stmt = $this->pdo()->prepare(
            "UPDATE contract_approvals SET status = :status, comments = :comments, decided_at = NOW() WHERE id = :id"
        );
        $stmt->execute(['status' => $status, 'comments' => $comments, 'id' => $approvalId]);
    }

    public function updateContractStatus(int $contractId, string $status): void
    {
        $stmt = $this->pdo()->prepare("UPDATE contracts SET status = :status WHERE id = :id");
        $stmt->execute(['status' => $status, 'id' => $contractId]);
    }

    public function getAmendments(int $contractId): array
    {
        $stmt = $this->pdo()->prepare(
            "SELECT ca.*, u.full_name AS creator_name, d.title AS document_title, d.current_version_id, dv.file_path
             FROM contract_amendments ca
             JOIN users u ON u.id = ca.created_by
             LEFT JOIN documents d ON d.id = ca.document_id
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
            "INSERT INTO contract_amendments (contract_id, amendment_number, description, effective_date, document_id, created_by)
             VALUES (:contract_id, :amendment_number, :description, :effective_date, :document_id, :created_by)"
        );
        $stmt->execute([
            'contract_id'      => $data['contract_id'],
            'amendment_number' => $data['amendment_number'],
            'description'      => $data['description'],
            'effective_date'   => $data['effective_date'],
            'document_id'      => $data['document_id'] ?: null,
            'created_by'       => $data['created_by'],
        ]);
        return (int) $this->pdo()->lastInsertId();
    }

    public function getDocuments(int $contractId): array
    {
        $stmt = $this->pdo()->prepare(
            "SELECT d.*, dc.name AS category_name, dv.file_path
             FROM documents d
             JOIN contract_documents cd ON cd.document_id = d.id
             LEFT JOIN document_categories dc ON dc.id = d.category_id
             LEFT JOIN document_versions dv ON dv.id = d.current_version_id
             WHERE cd.contract_id = :contractId"
        );
        $stmt->execute(['contractId' => $contractId]);
        return $stmt->fetchAll();
    }

    public function addDocument(int $contractId, int $documentId): void
    {
        $stmt = $this->pdo()->prepare(
            "INSERT INTO contract_documents (contract_id, document_id) VALUES (:contractId, :documentId)
             ON DUPLICATE KEY UPDATE document_id = document_id"
        );
        $stmt->execute(['contractId' => $contractId, 'documentId' => $documentId]);
    }

    public function countActiveContracts(): int
    {
        return (int) $this->pdo()
            ->query("SELECT COUNT(*) FROM contracts WHERE status = 'active'")
            ->fetchColumn();
    }
}
