<?php

namespace App\Services;

use App\Repositories\DocumentRepository;

use App\Support\AuditLogger;
use Illuminate\Support\Facades\Auth;
class DocumentService
{
    public function __construct(private DocumentRepository $repo) {}

    public function listDocuments(): array
    {
        $user = Auth::user();
        $canManageAll = $user?->canPermission('document.manage') || $user?->canPermission('document.upload');

        return $this->repo->all(
            (int) Auth::id(),
            (string) ($user?->role?->code ?? ''),
            (int) ($user?->role_id ?? 0),
            (bool) $canManageAll
        );
    }

    public function categories(): array
    {
        return $this->repo->allCategories();
    }

    public function tags(): array
    {
        return $this->repo->allTags();
    }

    public function documentDetails(int $id): ?array
    {
        $doc = $this->repo->find($id);
        if (!$doc) return null;
        
        $doc['versions'] = $this->repo->getVersions($id);
        $doc['access_list'] = $this->repo->getDocumentAccess($id);
        return $doc;
    }

    /**
     * @return array{success: bool, error?: string, id?: int}
     */
    public function uploadDocument(array $input, array $fileInput): array
    {
        $title           = trim($input['title'] ?? '');
        $description     = trim($input['description'] ?? '');
        $categoryId      = !empty($input['category_id']) ? (int) $input['category_id'] : null;
        $confidentiality = $input['confidentiality'] ?? 'internal';
        $tagsString      = trim($input['tags'] ?? '');

        if ($title === '') {
            return ['success' => false, 'error' => 'Title is required.'];
        }

        if (empty($fileInput['name']) || $fileInput['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => 'Please select a valid file to upload.'];
        }

        // Generate a document number
        $docNumber = 'DOC-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));

        // Handle File upload
        $uploadResult = $this->uploadFile($fileInput);
        if (!$uploadResult['success']) {
            return ['success' => false, 'error' => $uploadResult['error']];
        }

        $filePath = $uploadResult['filePath'];
        $checksum = $uploadResult['checksum'];

        // Create document record
        $documentId = $this->repo->createDocument([
            'category_id'     => $categoryId,
            'title'           => $title,
            'description'     => $description,
            'document_number' => $docNumber,
            'owner_id'        => Auth::id(),
            'confidentiality' => $confidentiality,
        ]);

        // Create version record (version 1)
        $versionId = $this->repo->createDocumentVersion([
            'document_id' => $documentId,
            'version_no'  => 1,
            'file_path'   => $filePath,
            'checksum'    => $checksum,
            'uploaded_by' => Auth::id(),
            'change_notes'=> 'Initial upload.',
        ]);

        // Update current version in document
        $this->repo->updateCurrentVersion($documentId, $versionId);

        // Process tags
        if ($tagsString !== '') {
            $tags = explode(',', $tagsString);
            foreach ($tags as $tagName) {
                $tagName = trim($tagName);
                if ($tagName !== '') {
                    $tagId = $this->repo->findOrCreateTag($tagName);
                    $this->repo->addTagToDocument($documentId, $tagId);
                }
            }
        }

        AuditLogger::log('documents', 'upload', 'document', $documentId, null, [
            'title'           => $title,
            'document_number' => $docNumber,
            'category_id'     => $categoryId,
            'confidentiality' => $confidentiality,
        ]);

        return ['success' => true, 'id' => $documentId];
    }

    /**
     * @return array{success: bool, error?: string}
     */
    public function uploadNewVersion(int $documentId, array $input, array $fileInput): array
    {
        $doc = $this->repo->find($documentId);
        if (!$doc) {
            return ['success' => false, 'error' => 'Document not found.'];
        }

        $changeNotes = trim($input['change_notes'] ?? '');

        if (empty($fileInput['name']) || $fileInput['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => 'Please select a valid file to upload.'];
        }

        // Handle File upload
        $uploadResult = $this->uploadFile($fileInput);
        if (!$uploadResult['success']) {
            return ['success' => false, 'error' => $uploadResult['error']];
        }

        $filePath = $uploadResult['filePath'];
        $checksum = $uploadResult['checksum'];

        // Get the next version number
        $versions = $this->repo->getVersions($documentId);
        $nextVersionNo = empty($versions) ? 1 : ($versions[0]['version_no'] + 1);

        // Create version record
        $versionId = $this->repo->createDocumentVersion([
            'document_id' => $documentId,
            'version_no'  => $nextVersionNo,
            'file_path'   => $filePath,
            'checksum'    => $checksum,
            'uploaded_by' => Auth::id(),
            'change_notes'=> $changeNotes ?: 'Version upload.',
        ]);

        // Update current version in document
        $this->repo->updateCurrentVersion($documentId, $versionId);

        AuditLogger::log('documents', 'version', 'document', $documentId, null, [
            'version_no'   => $nextVersionNo,
            'change_notes' => $changeNotes ?: 'Version upload.',
        ]);

        return ['success' => true];
    }

    /**
     * @return array{success: bool, error?: string}
     */
    public function manageAccess(int $documentId, array $input): array
    {
        $doc = $this->repo->find($documentId);
        if (!$doc) {
            return ['success' => false, 'error' => 'Document not found.'];
        }

        // Only owner or admin can manage access
        if ((int) $doc['owner_id'] !== (int) Auth::id() && ! Auth::user()?->canPermission('document.manage')) {
            return ['success' => false, 'error' => 'You do not have permission to manage access for this document.'];
        }

        $this->repo->clearDocumentAccess($documentId);

        $roleAccess = $input['role_access'] ?? [];
        foreach ($roleAccess as $roleId => $level) {
            if ($level !== '') {
                $this->repo->addDocumentAccess($documentId, null, (int) $roleId, $level);
            }
        }

        AuditLogger::log('documents', 'access_update', 'document', $documentId, null, [
            'role_access' => $roleAccess,
        ]);

        return ['success' => true];
    }

    private function uploadFile(array $fileInput): array
    {
        // Target folder: c:/xampp/htdocs/capstone-1/public/uploads/
        $uploadDir = public_path('uploads') . DIRECTORY_SEPARATOR;
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $extension = strtolower(pathinfo($fileInput['name'], PATHINFO_EXTENSION));
        $uniqueName = uniqid('fams_doc_', true) . '.' . $extension;
        $destination = $uploadDir . $uniqueName;

        if (! @move_uploaded_file($fileInput['tmp_name'], $destination) && ! @rename($fileInput['tmp_name'], $destination) && ! @copy($fileInput['tmp_name'], $destination)) {
            return ['success' => false, 'error' => 'Failed to save the uploaded file.'];
        }

        // Relative path for database: /uploads/unique_name
        $dbPath = '/uploads/' . $uniqueName;
        $checksum = hash_file('sha256', $destination);

        return [
            'success' => true,
            'filePath' => $dbPath,
            'checksum' => $checksum
        ];
    }

    public function activeCount(): int
    {
        return $this->repo->countActive();
    }
}
