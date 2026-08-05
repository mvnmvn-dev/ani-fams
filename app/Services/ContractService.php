<?php

namespace App\Services;

use App\Repositories\ContractRepository;
use App\Repositories\DocumentRepository;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\Auth;

class ContractService
{
    public function __construct(
        private ContractRepository $repo,
        private DocumentRepository $docRepo,
    ) {}

    public function listContracts(): array
    {
        $canManageAll = Auth::user()?->canPermission('contract.manage') || Auth::user()?->canPermission('contract.approve');
        return $this->repo->all(Auth::id(), $canManageAll);
    }

    public function types(): array
    {
        return $this->repo->allTypes();
    }

    public function contractDetails(int $id): ?array
    {
        $contract = $this->repo->find($id);
        if (!$contract) return null;

        $contract['approvals'] = $this->repo->getApprovals($id);
        $contract['amendments'] = $this->repo->getAmendments($id);
        $contract['documents'] = $this->repo->getDocuments($id);
        
        return $contract;
    }

    /**
     * @return array{success: bool, error?: string, id?: int}
     */
    public function draftContract(array $input, array $fileInput): array
    {
        $title          = trim($input['title'] ?? '');
        $typeId         = (int) ($input['contract_type_id'] ?? 0);
        $counterparty   = trim($input['counterparty_name'] ?? '');
        $contact        = trim($input['counterparty_contact'] ?? '');
        $value          = !empty($input['value_amount']) ? (float) $input['value_amount'] : null;
        $currency       = $input['currency'] ?? 'PHP';
        $startDate      = $input['start_date'] ?? '';
        $endDate        = $input['end_date'] ?? null;
        $autoRenew      = !empty($input['auto_renew']);
        $noticeDays     = !empty($input['renewal_notice_days']) ? (int) $input['renewal_notice_days'] : 30;
        $caseId         = (int) ($input['related_case_id'] ?? 0);
        $approverIds    = $input['approvers'] ?? [];

        if ($title === '' || $counterparty === '' || !$startDate) {
            return ['success' => false, 'error' => 'Title, counterparty, and start date are required.'];
        }

        // Generate contract number
        $contractNumber = 'CON-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(2)));

        $contractId = $this->repo->create([
            'contract_number'     => $contractNumber,
            'title'               => $title,
            'contract_type_id'    => $typeId ?: null,
            'counterparty_name'   => $counterparty,
            'counterparty_contact'=> $contact !== '' ? $contact : null,
            'owner_id'            => Auth::id(),
            'value_amount'        => $value,
            'currency'            => $currency,
            'start_date'          => $startDate,
            'end_date'            => $endDate !== '' ? $endDate : null,
            'auto_renew'          => $autoRenew,
            'renewal_notice_days' => $noticeDays,
            'related_case_id'     => $caseId ?: null,
        ]);

        // Process file upload if any
        if (!empty($fileInput['name']) && ($fileInput['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $docId = $this->uploadContractDocument($contractNumber . ' Attachment', $fileInput);
            if ($docId) {
                $this->repo->addDocument($contractId, $docId);
            }
        }

        // Set up approval sequence
        if (!empty($approverIds)) {
            $seq = 1;
            foreach ($approverIds as $appId) {
                $this->repo->addApprovalStep([
                    'contract_id'    => $contractId,
                    'approver_id'    => (int) $appId,
                    'sequence_order' => $seq++,
                ]);
            }
            $this->repo->updateContractStatus($contractId, 'in_review');
        } else {
            $this->repo->updateContractStatus($contractId, 'active');
        }

        AuditLogger::log('contracts', 'create', 'contract', $contractId, null, [
            'title'           => $title,
            'contract_number' => $contractNumber,
        ]);

        return ['success' => true, 'id' => $contractId];
    }

    /**
     * @return array{success: bool, error?: string}
     */
    public function decideApproval(int $contractId, int $approvalId, string $decision, ?string $comments): array
    {
        if (!in_array($decision, ['approved', 'rejected'], true)) {
            return ['success' => false, 'error' => 'Invalid decision.'];
        }

        $this->repo->updateApprovalStepStatus($approvalId, $decision, $comments);

        $contractStatus = 'in_review';
        if ($decision === 'rejected') {
            $this->repo->updateContractStatus($contractId, 'draft');
            $contractStatus = 'draft';
        } else {
            $approvals = $this->repo->getApprovals($contractId);
            $allApproved = true;
            foreach ($approvals as $app) {
                if ($app['status'] !== 'approved') {
                    $allApproved = false;
                    break;
                }
            }
            if ($allApproved) {
                $this->repo->updateContractStatus($contractId, 'active');
                $contractStatus = 'active';
            }
        }

        AuditLogger::log(
            'contracts',
            $decision === 'approved' ? 'approve' : 'reject',
            'contract',
            $contractId,
            null,
            ['approval_id' => $approvalId, 'decision' => $decision, 'comments' => $comments]
        );

        return [
            'success' => true,
            'id' => $contractId,
            'status' => $contractStatus,
            'decision' => $decision,
        ];
    }

    /**
     * @return array{success: bool, error?: string}
     */
    public function addAmendment(int $contractId, array $input, array $fileInput): array
    {
        $contract = $this->repo->find($contractId);
        if (! $contract) {
            return ['success' => false, 'error' => 'Contract not found.'];
        }

        $isOwner = (int) ($contract['owner_id'] ?? 0) === (int) Auth::id();
        if (! $isOwner && ! Auth::user()?->canPermission('contract.manage')) {
            return ['success' => false, 'error' => 'You do not have permission to amend this contract.'];
        }

        $desc = trim($input['description'] ?? '');
        $date = $input['effective_date'] ?? '';

        if ($desc === '' || ! $date) {
            return ['success' => false, 'error' => 'Description and effective date are required.'];
        }

        $amendments = $this->repo->getAmendments($contractId);
        $nextNo = count($amendments) + 1;

        $docId = null;
        if (! empty($fileInput['name']) && ($fileInput['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $docId = $this->uploadContractDocument("Contract Amendment #$nextNo", $fileInput);
        }

        $this->repo->createAmendment([
            'contract_id' => $contractId,
            'amendment_number' => $nextNo,
            'description' => $desc,
            'effective_date' => $date,
            'document_id' => $docId,
            'created_by' => Auth::id(),
        ]);

        AuditLogger::log('contracts', 'amend', 'contract', $contractId, null, [
            'amendment_number' => $nextNo,
            'description' => $desc,
            'effective_date' => $date,
        ]);

        return ['success' => true];
    }

    private function uploadContractDocument(string $title, array $fileInput): ?int
    {
        $uploadDir = public_path('uploads');
        if (! is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $extension = strtolower(pathinfo((string) ($fileInput['name'] ?? ''), PATHINFO_EXTENSION));
        $uniqueName = uniqid('fams_con_doc_', true).($extension !== '' ? '.'.$extension : '');
        $destination = $uploadDir.DIRECTORY_SEPARATOR.$uniqueName;
        $tmp = (string) ($fileInput['tmp_name'] ?? '');

        if ($tmp === '' || (! @move_uploaded_file($tmp, $destination) && ! @rename($tmp, $destination) && ! @copy($tmp, $destination))) {
            return null;
        }

        $filePath = '/uploads/'.$uniqueName;
        $checksum = hash_file('sha256', $destination);

        $catId = null;
        foreach ($this->docRepo->allCategories() as $cat) {
            if (($cat['name'] ?? '') === 'Contracts') {
                $catId = (int) $cat['id'];
                break;
            }
        }

        $documentId = $this->docRepo->createDocument([
            'category_id' => $catId,
            'title' => $title,
            'description' => 'Contract attachment',
            'document_number' => 'DOC-CON-'.strtoupper(bin2hex(random_bytes(2))),
            'owner_id' => Auth::id(),
            'confidentiality' => 'internal',
        ]);

        $versionId = $this->docRepo->createDocumentVersion([
            'document_id' => $documentId,
            'version_no' => 1,
            'file_path' => $filePath,
            'checksum' => $checksum,
            'uploaded_by' => Auth::id(),
            'change_notes' => 'Uploaded contract document.',
        ]);

        $this->docRepo->updateCurrentVersion($documentId, $versionId);

        return $documentId;
    }

    public function activeCount(): int
    {
        return $this->repo->countActiveContracts();
    }
}
