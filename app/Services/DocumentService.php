<?php

namespace App\Services;

use App\Repositories\DocumentRepository;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\Auth;

class DocumentService
{
    public function __construct(
        private DocumentRepository $repo,
        private AttachmentService $attachments,
    ) {}

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
        if (! $doc) {
            return null;
        }

        $doc['versions'] = $this->repo->getVersions($id);
        $doc['access_list'] = $this->repo->getDocumentAccess($id);

        return $doc;
    }

    /**
     * @return array{success: bool, error?: string, id?: int}
     */
    public function uploadDocument(array $input, array $fileInput): array
    {
        $title = trim($input['title'] ?? '');
        $description = trim($input['description'] ?? '');
        $categoryId = ! empty($input['category_id']) ? (int) $input['category_id'] : null;
        $confidentiality = $input['confidentiality'] ?? 'internal';
        $tagsString = trim($input['tags'] ?? '');

        if ($title === '') {
            return ['success' => false, 'error' => 'Title is required.'];
        }

        if (empty($fileInput['name']) || $fileInput['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => 'Please select a valid file to upload.'];
        }

        $docNumber = 'DOC-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));

        $uploadResult = $this->uploadFile($fileInput);
        if (! $uploadResult['success']) {
            return ['success' => false, 'error' => $uploadResult['error']];
        }

        $filePath = $uploadResult['filePath'];
        $checksum = $uploadResult['checksum'];

        $documentId = $this->repo->createDocument([
            'category_id' => $categoryId,
            'title' => $title,
            'description' => $description,
            'document_number' => $docNumber,
            'owner_id' => Auth::id(),
            'confidentiality' => $confidentiality,
        ]);

        $versionId = $this->repo->createDocumentVersion([
            'document_id' => $documentId,
            'version_no' => 1,
            'file_path' => $filePath,
            'checksum' => $checksum,
            'content_text' => $this->extractTextContent($filePath),
            'uploaded_by' => Auth::id(),
            'change_notes' => 'Initial upload.',
        ]);

        $this->repo->updateCurrentVersion($documentId, $versionId);
        $this->recordAttachment($fileInput, $filePath, $versionId, $uploadResult);

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
            'title' => $title,
            'document_number' => $docNumber,
            'category_id' => $categoryId,
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
        if (! $doc) {
            return ['success' => false, 'error' => 'Document not found.'];
        }

        $changeNotes = trim($input['change_notes'] ?? '');

        if (empty($fileInput['name']) || $fileInput['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => 'Please select a valid file to upload.'];
        }

        $uploadResult = $this->uploadFile($fileInput);
        if (! $uploadResult['success']) {
            return ['success' => false, 'error' => $uploadResult['error']];
        }

        $filePath = $uploadResult['filePath'];
        $checksum = $uploadResult['checksum'];

        $versions = $this->repo->getVersions($documentId);
        $nextVersionNo = empty($versions) ? 1 : ($versions[0]['version_no'] + 1);

        $versionId = $this->repo->createDocumentVersion([
            'document_id' => $documentId,
            'version_no' => $nextVersionNo,
            'file_path' => $filePath,
            'checksum' => $checksum,
            'content_text' => $this->extractTextContent($filePath),
            'uploaded_by' => Auth::id(),
            'change_notes' => $changeNotes ?: 'Version upload.',
        ]);

        $this->repo->updateCurrentVersion($documentId, $versionId);
        $this->recordAttachment($fileInput, $filePath, $versionId, $uploadResult);

        AuditLogger::log('documents', 'version', 'document', $documentId, null, [
            'version_no' => $nextVersionNo,
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
        if (! $doc) {
            return ['success' => false, 'error' => 'Document not found.'];
        }

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

    public function softDelete(int $documentId): array
    {
        $doc = $this->repo->find($documentId);
        if (! $doc) {
            return ['success' => false, 'error' => 'Document not found.'];
        }
        if ((int) $doc['owner_id'] !== (int) Auth::id() && ! Auth::user()?->canPermission('document.manage')) {
            return ['success' => false, 'error' => 'You do not have permission to delete this document.'];
        }
        $this->repo->softDelete($documentId);
        AuditLogger::log('documents', 'soft_delete', 'document', $documentId);

        return ['success' => true];
    }

    public function search(string $q): array
    {
        $user = Auth::user();
        $canManageAll = $user?->canPermission('document.manage') || $user?->canPermission('document.upload');

        return $this->repo->searchContent(
            $q,
            (int) Auth::id(),
            (int) ($user?->role_id ?? 0),
            (bool) $canManageAll
        );
    }

    /**
     * @param  array{left_label?: string, right_label?: string}  $meta
     * @return array{success: bool, error?: string, diff?: array, meta?: array, text_comparable?: bool}
     */
    public function textDiffFromPaths(string $leftPath, string $rightPath, array $meta = []): array
    {
        $textExts = ['txt', 'md', 'csv', 'json', 'log', 'xml', 'html', 'htm', 'css', 'js', 'php'];
        $leftExt = strtolower(pathinfo($leftPath, PATHINFO_EXTENSION));
        $rightExt = strtolower(pathinfo($rightPath, PATHINFO_EXTENSION));
        $comparable = in_array($leftExt, $textExts, true) && in_array($rightExt, $textExts, true)
            && is_file($leftPath) && is_file($rightPath);

        if (! $comparable) {
            return [
                'success' => true,
                'text_comparable' => false,
                'diff' => null,
                'meta' => $meta + [
                    'left_path' => $leftPath,
                    'right_path' => $rightPath,
                    'message' => 'Files are not text-comparable.',
                ],
            ];
        }

        $leftLines = explode("\n", (string) file_get_contents($leftPath));
        $rightLines = explode("\n", (string) file_get_contents($rightPath));

        return [
            'success' => true,
            'text_comparable' => true,
            'diff' => $this->lineDiff($leftLines, $rightLines),
            'meta' => $meta,
        ];
    }

    private function extractTextContent(string $filePath): ?string
    {
        $fullPath = public_path(ltrim($filePath, '/'));
        if (! is_file($fullPath)) {
            return null;
        }
        $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
        $textExts = ['txt', 'md', 'csv', 'json', 'log', 'xml', 'html', 'htm', 'css', 'js', 'php'];
        if (! in_array($ext, $textExts, true)) {
            return null;
        }
        $raw = @file_get_contents($fullPath);
        if ($raw === false) {
            return null;
        }
        if (strlen($raw) > 500000) {
            $raw = substr($raw, 0, 500000);
        }

        return $raw;
    }

    public function preview(int $documentId): array
    {
        $doc = $this->repo->find($documentId);
        if (! $doc || empty($doc['file_path'])) {
            return ['success' => false, 'error' => 'Document or file not found.'];
        }

        $relative = ltrim((string) $doc['file_path'], '/');
        $fullPath = public_path($relative);
        if (! is_file($fullPath)) {
            return ['success' => false, 'error' => 'File is missing on disk.'];
        }

        $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
        $mime = function_exists('mime_content_type') ? (mime_content_type($fullPath) ?: null) : null;
        $filename = basename($fullPath);

        $inlineExts = ['pdf', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'];
        if (in_array($ext, $inlineExts, true) || ($mime && (str_starts_with($mime, 'image/') || $mime === 'application/pdf'))) {
            return [
                'success' => true,
                'mode' => 'file',
                'path' => $fullPath,
                'mime' => $mime ?: ($ext === 'pdf' ? 'application/pdf' : 'application/octet-stream'),
                'filename' => $filename,
                'title' => $doc['title'] ?? $filename,
            ];
        }

        $textExts = ['txt', 'md', 'csv', 'json', 'log', 'xml', 'html', 'htm', 'css', 'js', 'php'];
        if (in_array($ext, $textExts, true) || ($mime && str_starts_with($mime, 'text/'))) {
            return [
                'success' => true,
                'mode' => 'text',
                'doc' => $doc,
                'content' => file_get_contents($fullPath),
                'mime' => $mime,
                'title' => $doc['title'] ?? $filename,
                'download_url' => $doc['file_path'],
            ];
        }

        return [
            'success' => true,
            'mode' => 'download',
            'doc' => $doc,
            'mime' => $mime,
            'title' => $doc['title'] ?? $filename,
            'download_url' => $doc['file_path'],
        ];
    }

    public function compareVersions(int $documentId, int $v1, int $v2): array
    {
        $doc = $this->documentDetails($documentId);
        if (! $doc) {
            return ['success' => false, 'error' => 'Document not found.'];
        }
        if ($v1 < 1 || $v2 < 1 || $v1 === $v2) {
            return ['success' => false, 'error' => 'Select two different version numbers to compare.'];
        }

        $left = $this->repo->findVersion($documentId, $v1);
        $right = $this->repo->findVersion($documentId, $v2);
        if (! $left || ! $right) {
            return ['success' => false, 'error' => 'One or both versions were not found.'];
        }

        $leftPath = public_path(ltrim((string) $left['file_path'], '/'));
        $rightPath = public_path(ltrim((string) $right['file_path'], '/'));
        $leftExt = strtolower(pathinfo($leftPath, PATHINFO_EXTENSION));
        $rightExt = strtolower(pathinfo($rightPath, PATHINFO_EXTENSION));
        $textExts = ['txt', 'md', 'csv', 'json', 'log', 'xml', 'html', 'htm', 'css', 'js'];

        $metaDiff = [
            'checksum' => [
                'left' => $left['checksum'] ?? null,
                'right' => $right['checksum'] ?? null,
                'same' => ($left['checksum'] ?? null) === ($right['checksum'] ?? null),
            ],
            'size' => [
                'left' => is_file($leftPath) ? filesize($leftPath) : null,
                'right' => is_file($rightPath) ? filesize($rightPath) : null,
            ],
            'path' => [
                'left' => $left['file_path'] ?? null,
                'right' => $right['file_path'] ?? null,
            ],
            'uploaded_at' => [
                'left' => $left['uploaded_at'] ?? null,
                'right' => $right['uploaded_at'] ?? null,
            ],
            'change_notes' => [
                'left' => $left['change_notes'] ?? null,
                'right' => $right['change_notes'] ?? null,
            ],
        ];

        $textComparable = in_array($leftExt, $textExts, true) && in_array($rightExt, $textExts, true)
            && is_file($leftPath) && is_file($rightPath);

        $diff = null;
        if ($textComparable) {
            $leftLines = explode("\n", (string) file_get_contents($leftPath));
            $rightLines = explode("\n", (string) file_get_contents($rightPath));
            $diff = $this->lineDiff($leftLines, $rightLines);
        }

        return [
            'success' => true,
            'doc' => $doc,
            'left' => $left,
            'right' => $right,
            'meta_diff' => $metaDiff,
            'text_comparable' => $textComparable,
            'diff' => $diff,
        ];
    }

    /**
     * @param  list<string>  $a
     * @param  list<string>  $b
     * @return list<array{type: string, left?: string, right?: string, text?: string}>
     */
    private function lineDiff(array $a, array $b): array
    {
        $max = max(count($a), count($b));
        $out = [];
        for ($i = 0; $i < $max; $i++) {
            $la = $a[$i] ?? null;
            $lb = $b[$i] ?? null;
            if ($la === $lb) {
                $out[] = ['type' => 'same', 'text' => (string) $la];
            } elseif ($la === null) {
                $out[] = ['type' => 'add', 'right' => (string) $lb];
            } elseif ($lb === null) {
                $out[] = ['type' => 'del', 'left' => (string) $la];
            } else {
                $out[] = ['type' => 'change', 'left' => (string) $la, 'right' => (string) $lb];
            }
        }

        return $out;
    }

    private function recordAttachment(array $fileInput, string $filePath, int $versionId, array $uploadResult): void
    {
        try {
            $fullPath = public_path(ltrim($filePath, '/'));
            \App\Models\Attachment::create([
                'module' => 'documents',
                'entity_type' => 'document_version',
                'entity_id' => $versionId,
                'file_name' => $fileInput['name'] ?? basename($filePath),
                'file_path' => $filePath,
                'mime_type' => is_file($fullPath) && function_exists('mime_content_type')
                    ? (mime_content_type($fullPath) ?: null)
                    : null,
                'file_size' => is_file($fullPath) ? filesize($fullPath) : ($fileInput['size'] ?? null),
                'uploaded_by' => Auth::id(),
                'uploaded_at' => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function uploadFile(array $fileInput): array
    {
        $uploadDir = public_path('uploads') . DIRECTORY_SEPARATOR;
        if (! is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $extension = strtolower(pathinfo($fileInput['name'], PATHINFO_EXTENSION));
        $uniqueName = uniqid('fams_doc_', true) . '.' . $extension;
        $destination = $uploadDir . $uniqueName;

        if (! @move_uploaded_file($fileInput['tmp_name'], $destination) && ! @rename($fileInput['tmp_name'], $destination) && ! @copy($fileInput['tmp_name'], $destination)) {
            return ['success' => false, 'error' => 'Failed to save the uploaded file.'];
        }

        $dbPath = '/uploads/' . $uniqueName;
        $checksum = hash_file('sha256', $destination);

        return [
            'success' => true,
            'filePath' => $dbPath,
            'checksum' => $checksum,
        ];
    }

    public function activeCount(): int
    {
        return $this->repo->countActive();
    }
}
