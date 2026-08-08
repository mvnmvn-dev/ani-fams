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
        private AttachmentService $attachments,
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

    public function templates(): array
    {
        return $this->repo->allTemplates();
    }

    public function approvalChains(): array
    {
        return $this->repo->allChains();
    }

    public function spendRollup(): array
    {
        return $this->repo->spendRollupByType();
    }

    /**
     * @return array{success: bool, error?: string, id?: int}
     */
    public function createTemplate(array $input): array
    {
        if (! Auth::user()?->canPermission('contract.manage')) {
            return ['success' => false, 'error' => 'You do not have permission to manage templates.'];
        }
        $name = trim($input['name'] ?? '');
        $body = trim($input['body_text'] ?? '');
        if ($name === '' || $body === '') {
            return ['success' => false, 'error' => 'Template name and body are required.'];
        }
        $id = $this->repo->createTemplate([
            'name' => $name,
            'contract_type_id' => (int) ($input['contract_type_id'] ?? 0) ?: null,
            'body_text' => $body,
            'default_value' => $input['default_value'] ?? null,
            'created_by' => Auth::id(),
        ]);

        return ['success' => true, 'id' => $id];
    }

    /**
     * @return array{success: bool, error?: string, id?: int}
     */
    public function saveApprovalChain(array $input): array
    {
        if (! Auth::user()?->canPermission('contract.manage')) {
            return ['success' => false, 'error' => 'You do not have permission to manage approval chains.'];
        }
        $name = trim($input['name'] ?? '');
        $approvers = array_values(array_filter(array_map('intval', (array) ($input['approvers'] ?? []))));
        if ($name === '' || $approvers === []) {
            return ['success' => false, 'error' => 'Chain name and at least one approver are required.'];
        }
        $id = $this->repo->createChain(
            $name,
            (int) ($input['contract_type_id'] ?? 0) ?: null,
            ! empty($input['is_default']),
            $approvers
        );

        return ['success' => true, 'id' => $id];
    }

    public function contractDetails(int $id): ?array
    {
        $contract = $this->repo->find($id);
        if (!$contract) return null;

        $contract['approvals'] = $this->repo->getApprovals($id);
        $contract['amendments'] = $this->repo->getAmendments($id);
        $contract['documents'] = $this->repo->getDocuments($id);
        if (! empty($contract['signature_token'])) {
            $contract['sign_url'] = url('/contracts/sign/'.$contract['signature_token']);
        }

        return $contract;
    }

    /**
     * @return array{success: bool, error?: string, id?: int}
     */
    public function draftContract(array $input, array $fileInput): array
    {
        $title          = trim($input['title'] ?? '');
        $typeId         = (int) ($input['contract_type_id'] ?? 0);
        $templateId     = (int) ($input['template_id'] ?? 0);
        $counterparty   = trim($input['counterparty_name'] ?? '');
        $contact        = trim($input['counterparty_contact'] ?? '');
        $value          = !empty($input['value_amount']) ? (float) $input['value_amount'] : null;
        $currency       = $input['currency'] ?? 'PHP';
        $startDate      = $input['start_date'] ?? '';
        $endDate        = $input['end_date'] ?? null;
        $autoRenew      = !empty($input['auto_renew']);
        $noticeDays     = !empty($input['renewal_notice_days']) ? (int) $input['renewal_notice_days'] : 30;
        $caseId         = (int) ($input['related_case_id'] ?? 0);
        $approverIds    = array_values(array_filter(array_map('intval', (array) ($input['approvers'] ?? []))));
        $chainId        = (int) ($input['approval_chain_id'] ?? 0);
        $skipSignature  = ! empty($input['skip_signature']);

        if ($templateId > 0) {
            $template = $this->repo->findTemplate($templateId);
            if ($template) {
                if ($title === '') {
                    $title = (string) ($template['name'] ?? '');
                }
                if ($value === null && isset($template['default_value']) && $template['default_value'] !== null) {
                    $value = (float) $template['default_value'];
                }
                if (! $typeId && ! empty($template['contract_type_id'])) {
                    $typeId = (int) $template['contract_type_id'];
                }
            }
        }

        if ($approverIds === [] && $chainId > 0) {
            foreach ($this->repo->getChainSteps($chainId) as $step) {
                $approverIds[] = (int) $step['approver_id'];
            }
        } elseif ($approverIds === [] && $typeId > 0) {
            $defaultChain = $this->repo->findDefaultChainForType($typeId);
            if ($defaultChain) {
                foreach ($this->repo->getChainSteps((int) $defaultChain['id']) as $step) {
                    $approverIds[] = (int) $step['approver_id'];
                }
            }
        }

        if ($title === '' || $counterparty === '' || !$startDate) {
            return ['success' => false, 'error' => 'Title, counterparty, and start date are required.'];
        }

        $contractNumber = 'CON-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(2)));

        $contractId = $this->repo->create([
            'contract_number'     => $contractNumber,
            'title'               => $title,
            'contract_type_id'    => $typeId ?: null,
            'template_id'         => $templateId ?: null,
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

        if (!empty($fileInput['name']) && ($fileInput['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $docId = $this->uploadContractDocument($contractNumber . ' Attachment', $fileInput);
            if ($docId) {
                $this->repo->addDocument($contractId, $docId);
            }
        }

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

            $notificationService = app(NotificationService::class);
            $notificationService->send(
                (int) $approverIds[0],
                'contracts',
                "Contract Approval Required: {$contractNumber}",
                "You have been assigned to approve the contract '{$title}'.",
                "/contracts?open={$contractId}",
                true
            );
        } elseif ($skipSignature) {
            $this->repo->updateContractStatus($contractId, 'active');
        } else {
            $this->moveToPendingSignature($contractId, $contractNumber, $title, (int) Auth::id());
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
        $notificationService = app(NotificationService::class);
        $contractModel = \App\Models\Contract::find($contractId);

        if ($decision === 'rejected') {
            $this->repo->updateContractStatus($contractId, 'draft');
            $contractStatus = 'draft';

            if ($contractModel) {
                $notificationService->send(
                    $contractModel->owner_id,
                    'contracts',
                    "Contract Rejected: {$contractModel->contract_number}",
                    "Your contract '{$contractModel->title}' was rejected.",
                    "/contracts?open={$contractId}",
                    true
                );
            }
        } else {
            $approvals = $this->repo->getApprovals($contractId);
            $allApproved = true;
            $nextApproverId = null;

            foreach ($approvals as $app) {
                if ($app['status'] !== 'approved') {
                    $allApproved = false;
                    if ($app['status'] === 'pending' && $nextApproverId === null) {
                        $nextApproverId = $app['approver_id'];
                    }
                    break;
                }
            }
            if ($allApproved) {
                $this->moveToPendingSignature(
                    $contractId,
                    (string) ($contractModel->contract_number ?? ''),
                    (string) ($contractModel->title ?? ''),
                    (int) ($contractModel->owner_id ?? 0)
                );
                $contractStatus = 'pending_signature';
            } elseif ($nextApproverId && $contractModel) {
                $notificationService->send(
                    $nextApproverId,
                    'contracts',
                    "Contract Approval Required: {$contractModel->contract_number}",
                    "It is your turn to review contract '{$contractModel->title}'.",
                    "/contracts?open={$contractId}",
                    true
                );
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
     * @return array{success: bool, error?: string, contract?: array}
     */
    public function contractBySignToken(string $token): array
    {
        $contract = $this->repo->findBySignatureToken($token);
        if (! $contract) {
            return ['success' => false, 'error' => 'Invalid or expired signing link.'];
        }
        if (($contract['status'] ?? '') !== 'pending_signature') {
            return ['success' => false, 'error' => 'This contract is not awaiting signature.'];
        }

        return ['success' => true, 'contract' => $contract];
    }

    /**
     * @return array{success: bool, error?: string}
     */
    public function signByToken(string $token, string $signerName, ?string $ip, ?string $signatureData = null): array
    {
        $signerName = trim($signerName);
        if ($signerName === '') {
            return ['success' => false, 'error' => 'Signer name is required.'];
        }

        $found = $this->contractBySignToken($token);
        if (! $found['success']) {
            return $found;
        }

        $signaturePath = $this->storeSignatureImage($signatureData);
        if ($signaturePath === null) {
            return ['success' => false, 'error' => 'Please draw your signature before submitting.'];
        }

        $contract = $found['contract'];
        $this->repo->setSignature(
            (int) $contract['id'],
            null,
            date('Y-m-d H:i:s'),
            $signerName,
            $ip,
            $signaturePath
        );
        $this->repo->updateContractStatus((int) $contract['id'], 'active');

        AuditLogger::log('contracts', 'sign', 'contract', (int) $contract['id'], null, [
            'signed_by_name' => $signerName,
            'signature_path' => $signaturePath,
        ]);

        return ['success' => true, 'id' => (int) $contract['id']];
    }

    private function storeSignatureImage(?string $signatureData): ?string
    {
        if ($signatureData === null || $signatureData === '') {
            return null;
        }

        if (! preg_match('#^data:image/(png|jpeg);base64,#i', $signatureData)) {
            return null;
        }

        // Reject nearly-blank canvases (very small payloads)
        $raw = preg_replace('#^data:image/\w+;base64,#i', '', $signatureData);
        $binary = base64_decode($raw, true);
        if ($binary === false || strlen($binary) < 800) {
            return null;
        }

        $dir = public_path('uploads/signatures');
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $filename = 'sig_'.bin2hex(random_bytes(8)).'.png';
        $full = $dir.DIRECTORY_SEPARATOR.$filename;
        if (file_put_contents($full, $binary) === false) {
            return null;
        }

        return '/uploads/signatures/'.$filename;
    }

    /**
     * @return array{success: bool, error?: string, diff?: array, meta?: array}
     */
    public function amendmentDiff(int $contractId, int $amendmentId): array
    {
        $amendment = $this->repo->findAmendment($contractId, $amendmentId);
        if (! $amendment) {
            return ['success' => false, 'error' => 'Amendment not found.'];
        }
        if (empty($amendment['file_path'])) {
            return ['success' => false, 'error' => 'Amendment has no attached document to compare.'];
        }

        $docs = $this->repo->getDocuments($contractId);
        $baseline = null;
        foreach ($docs as $doc) {
            if ((int) ($doc['id'] ?? 0) === (int) ($amendment['document_id'] ?? 0)) {
                continue;
            }
            if (! empty($doc['file_path'])) {
                $baseline = $doc;
                break;
            }
        }
        if (! $baseline) {
            return ['success' => false, 'error' => 'No prior contract document found to diff against.'];
        }

        $leftPath = public_path(ltrim((string) $baseline['file_path'], '/'));
        $rightPath = public_path(ltrim((string) $amendment['file_path'], '/'));
        $docService = app(DocumentService::class);

        return $docService->textDiffFromPaths($leftPath, $rightPath, [
            'left_label' => $baseline['title'] ?? 'Original',
            'right_label' => 'Amendment #'.($amendment['amendment_number'] ?? ''),
        ]);
    }

    private function moveToPendingSignature(int $contractId, string $number, string $title, int $ownerId): void
    {
        $token = bin2hex(random_bytes(32));
        $this->repo->updateContractStatus($contractId, 'pending_signature');
        $this->repo->setSignature($contractId, $token, null, null, null);

        if ($ownerId > 0) {
            app(NotificationService::class)->send(
                $ownerId,
                'contracts',
                "Signature required: {$number}",
                "Contract '{$title}' is ready for e-signature.",
                '/contracts/sign/'.$token,
                true
            );
        }
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

        try {
            \App\Models\Attachment::create([
                'module' => 'contracts',
                'entity_type' => 'document_version',
                'entity_id' => $versionId,
                'file_name' => (string) ($fileInput['name'] ?? basename($filePath)),
                'file_path' => $filePath,
                'mime_type' => is_file($destination) && function_exists('mime_content_type')
                    ? (mime_content_type($destination) ?: null)
                    : null,
                'file_size' => is_file($destination) ? filesize($destination) : null,
                'uploaded_by' => Auth::id(),
                'uploaded_at' => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }

        return $documentId;
    }

    public function softDelete(int $contractId): array
    {
        $contract = $this->repo->find($contractId);
        if (! $contract) {
            return ['success' => false, 'error' => 'Contract not found.'];
        }
        $isOwner = (int) ($contract['owner_id'] ?? 0) === (int) Auth::id();
        if (! $isOwner && ! Auth::user()?->canPermission('contract.manage')) {
            return ['success' => false, 'error' => 'You do not have permission to delete this contract.'];
        }
        $this->repo->softDelete($contractId);
        AuditLogger::log('contracts', 'soft_delete', 'contract', $contractId);

        return ['success' => true];
    }

    public function activeCount(): int
    {
        return $this->repo->countActiveContracts();
    }
}
