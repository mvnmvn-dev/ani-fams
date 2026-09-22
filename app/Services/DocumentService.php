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

        $duplicate = $this->repo->findDocumentByChecksum($checksum);
        if ($duplicate) {
            @unlink(public_path(ltrim($filePath, '/')));
            return ['success' => false, 'error' => 'Possible duplicate detected: this file matches "' . ($duplicate['title'] ?? 'an existing document') . '" (' . ($duplicate['document_number'] ?? 'existing record') . ').'];
        }

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
        $analysis = app(ArnelAiService::class)->analyzeDocument(
            (string) ($this->repo->findVersion($documentId, 1)['content_text'] ?? ''),
            (string) ($fileInput['name'] ?? ''),
            $title,
            $description
        );
        if ($analysis) {
            $this->repo->updateAiAnalysis($documentId, $analysis);
            foreach (array_slice($analysis['keywords'] ?? [], 0, 10) as $tagName) {
                $tagId = $this->repo->findOrCreateTag((string) $tagName);
                $this->repo->addTagToDocument($documentId, $tagId);
            }
        }
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
        $analysis = app(ArnelAiService::class)->analyzeDocument(
            (string) ($this->repo->findVersion($documentId, $nextVersionNo)['content_text'] ?? ''),
            (string) ($fileInput['name'] ?? ''),
            (string) ($doc['title'] ?? ''),
            (string) ($doc['description'] ?? '')
        );
        if ($analysis) {
            $this->repo->updateAiAnalysis($documentId, $analysis);
        }
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
        if (! is_file($fullPath)) return null;
        $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
        $textExts = ['txt', 'md', 'csv', 'json', 'log', 'xml', 'html', 'htm', 'css', 'js', 'php'];
        if (in_array($ext, $textExts, true)) {
            $raw = @file_get_contents($fullPath);
            return $raw === false ? null : substr($raw, 0, 500000);
        }

        if ($ext === 'docx' && class_exists(\PhpOffice\PhpWord\IOFactory::class)) {
            try {
                $document = \PhpOffice\PhpWord\IOFactory::load($fullPath);
                $parts = [];
                foreach ($document->getSections() as $section) foreach ($section->getElements() as $element) {
                    if (method_exists($element, 'getText')) $parts[] = $element->getText();
                    elseif (method_exists($element, 'getElements')) foreach ($element->getElements() as $child) if (method_exists($child, 'getText')) $parts[] = $child->getText();
                }
                return trim(substr(implode("\n", $parts), 0, 500000)) ?: null;
            } catch (\Throwable $e) { report($e); }
        }
        if ($ext === 'xlsx' && class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) {
            try {
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($fullPath);
                $parts = [];
                foreach ($spreadsheet->getAllSheets() as $sheet) foreach ($sheet->toArray(null, true, true, true) as $row) $parts[] = implode(' | ', array_map('strval', $row));
                return trim(substr(implode("\n", $parts), 0, 500000)) ?: null;
            } catch (\Throwable $e) { report($e); }
        }
        if ($ext === 'pptx' && class_exists(\ZipArchive::class)) {
            try {
                $zip = new \ZipArchive();
                if ($zip->open($fullPath) === true) {
                    $parts = [];
                    for ($i = 1; $i <= $zip->numFiles; $i++) {
                        $name = $zip->getNameIndex($i - 1);
                        if (is_string($name) && preg_match('#^ppt/slides/slide\d+\.xml$#', $name)) $parts[] = strip_tags(str_replace(['</a:p>', '</a:r>'], "\n", $zip->getFromName($name)));
                    }
                    $zip->close();
                    return trim(substr(implode("\n", $parts), 0, 500000)) ?: null;
                }
            } catch (\Throwable $e) { report($e); }
        }
        if ($ext === 'pdf' && class_exists(\Smalot\PdfParser\Parser::class)) {
            try { return trim(substr((new \Smalot\PdfParser\Parser())->parseFile($fullPath)->getText(), 0, 500000)) ?: null; } catch (\Throwable $e) { report($e); }
        }
        $quoted = escapeshellarg($fullPath);
        if ($ext === 'pdf' && function_exists('shell_exec')) {
            $raw = @shell_exec('pdftotext -layout ' . $quoted . ' - 2>/dev/null');
            return is_string($raw) && trim($raw) !== '' ? substr($raw, 0, 500000) : null;
        }
        if (in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'tif', 'tiff'], true) && function_exists('shell_exec')) {
            $raw = @shell_exec('tesseract ' . $quoted . ' stdout 2>/dev/null');
            return is_string($raw) && trim($raw) !== '' ? substr($raw, 0, 500000) : null;
        }
        return null;
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

    // ===================== DOCUMENT REVIEW/EXPIRATION REMINDERS =====================

    public function getDocumentsNeedingReview(): array
    {
        if (! Auth::user()?->canPermission('document.manage')) {
            return [];
        }

        return $this->repo->getDocumentsNeedingReview();
    }

    /**
     * @return array{success: bool, sent_count: int}
     */
    public function sendReviewReminders(): array
    {
        $documents = $this->repo->getDocumentsNeedingReview();
        $sentCount = 0;

        foreach ($documents as $doc) {
            $notificationService = app(NotificationService::class);
            $notificationService->send(
                (int) $doc['owner_id'],
                'documents',
                'Document Review Required',
                "Document \"{$doc['title']}\" is due for review by {$doc['review_by_date']}.",
                '/documents',
                true
            );

            $this->repo->markReviewReminderSent((int) $doc['id']);
            $sentCount++;
        }

        return ['success' => true, 'sent_count' => $sentCount];
    }

    /**
     * @return array{success: bool, error?: string}
     */
    public function markDocumentReviewed(int $documentId): array
    {
        $doc = $this->repo->find($documentId);
        if (! $doc) {
            return ['success' => false, 'error' => 'Document not found.'];
        }

        if ((int) $doc['owner_id'] !== (int) Auth::id() && ! Auth::user()?->canPermission('document.manage')) {
            return ['success' => false, 'error' => 'You do not have permission to review this document.'];
        }

        $this->repo->markDocumentReviewed($documentId, Auth::id());

        AuditLogger::log('documents', 'review', 'document', $documentId, null, [
            'reviewed_by' => Auth::id(),
        ]);

        return ['success' => true];
    }

    /**
     * @return array{success: bool, error?: string}
     */
    public function updateReviewDate(int $documentId, string $reviewDate): array
    {
        if (! Auth::user()?->canPermission('document.manage')) {
            return ['success' => false, 'error' => 'You do not have permission to update review dates.'];
        }

        $this->repo->updateReviewDate($documentId, $reviewDate);

        AuditLogger::log('documents', 'review_date_update', 'document', $documentId, null, [
            'review_by_date' => $reviewDate,
        ]);

        return ['success' => true];
    }

    // ===================== CHECK-OUT/LOCK FUNCTIONALITY =====================

    /**
     * @return array{success: bool, error?: string}
     */
    public function checkOutDocument(int $documentId, ?string $notes = null): array
    {
        $doc = $this->repo->find($documentId);
        if (! $doc) {
            return ['success' => false, 'error' => 'Document not found.'];
        }

        if ($doc['checked_out_by'] ?? null) {
            return ['success' => false, 'error' => 'Document is already checked out.'];
        }

        $this->repo->checkOutDocument($documentId, Auth::id(), $notes);

        AuditLogger::log('documents', 'checkout', 'document', $documentId, null, [
            'checked_out_by' => Auth::id(),
            'notes' => $notes,
        ]);

        return ['success' => true];
    }

    /**
     * @return array{success: bool, error?: string}
     */
    public function checkInDocument(int $documentId): array
    {
        $doc = $this->repo->find($documentId);
        if (! $doc) {
            return ['success' => false, 'error' => 'Document not found.'];
        }

        if (! ($doc['checked_out_by'] ?? null)) {
            return ['success' => false, 'error' => 'Document is not checked out.'];
        }

        if ((int) $doc['checked_out_by'] !== (int) Auth::id() && ! Auth::user()?->canPermission('document.manage')) {
            return ['success' => false, 'error' => 'You can only check in documents you have checked out.'];
        }

        $this->repo->checkInDocument($documentId);

        AuditLogger::log('documents', 'checkin', 'document', $documentId, null, [
            'checked_in_by' => Auth::id(),
        ]);

        return ['success' => true];
    }

    public function getCheckedOutDocuments(): array
    {
        if (! Auth::user()?->canPermission('document.manage')) {
            return [];
        }

        return $this->repo->getCheckedOutDocuments();
    }

    // ===================== OCR PROCESSING =====================

    /**
     * @return array{success: bool, processed_count: int}
     */
    public function processOcrForPendingVersions(): array
    {
        $versions = $this->repo->getVersionsNeedingOcr();
        $processedCount = 0;

        foreach ($versions as $version) {
            $text = $this->extractTextContent($version['file_path'] ?? '');
            if ($text) {
                // Store the extracted text back to the version
                $stmt = $this->repo->pdo()->prepare(
                    'UPDATE document_versions SET content_text = :content_text WHERE id = :id'
                );
                $stmt->execute(['content_text' => $text, 'id' => $version['id']]);

                // Mark as processed with a confidence score (simplified)
                $confidence = strlen($text) > 100 ? 85.0 : 50.0;
                $this->repo->markOcrProcessed((int) $version['id'], $confidence);
                $processedCount++;
            }
        }

        return ['success' => true, 'processed_count' => $processedCount];
    }

    // ===================== BULK UPLOAD TRACKING =====================

    /**
     * @return array{success: bool, error?: string, upload_id?: int}
     */
    public function startBulkUpload(int $fileCount): array
    {
        $uploadId = $this->repo->createBulkUpload([
            'uploaded_by' => Auth::id(),
            'module' => 'documents',
            'file_count' => $fileCount,
            'status' => 'processing',
        ]);

        return ['success' => true, 'upload_id' => $uploadId];
    }

    /**
     * @return array{success: bool, error?: string}
     */
    public function completeBulkUpload(int $uploadId, string $status, ?string $errorMessage = null): array
    {
        $this->repo->updateBulkUploadStatus($uploadId, $status, $errorMessage);

        return ['success' => true];
    }

    public function getBulkUploads(): array
    {
        return $this->repo->getBulkUploads(Auth::id());
    }

    // ===================== E-SIGNATURE REQUESTS =====================

    /**
     * @return array{success: bool, error?: string, request_id?: int, token?: string}
     */
    public function requestSignature(int $documentId, string $signerEmail, string $signerName, ?string $message = null): array
    {
        $doc = $this->repo->find($documentId);
        if (! $doc) {
            return ['success' => false, 'error' => 'Document not found.'];
        }

        if ((int) $doc['owner_id'] !== (int) Auth::id() && ! Auth::user()?->canPermission('document.manage')) {
            return ['success' => false, 'error' => 'You do not have permission to request signatures for this document.'];
        }

        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+7 days'));

        $requestId = $this->repo->createSignatureRequest([
            'document_id' => $documentId,
            'requested_by' => Auth::id(),
            'signer_email' => $signerEmail,
            'signer_name' => $signerName,
            'message' => $message,
            'token' => $token,
            'expires_at' => $expiresAt,
        ]);

        // Send email notification
        $notificationService = app(NotificationService::class);
        $notificationService->send(
            $signerEmail, // For external signers, we'd need to handle this differently
            'documents',
            'Signature Request',
            "You have been requested to sign document \"{$doc['title']}\".",
            "/documents/sign/{$token}",
            true
        );

        AuditLogger::log('documents', 'signature_request', 'document_signature_request', $requestId, null, [
            'document_id' => $documentId,
            'signer_email' => $signerEmail,
        ]);

        return ['success' => true, 'request_id' => $requestId, 'token' => $token];
    }

    /**
     * @return array{success: bool, error?: string, request?: array}
     */
    public function getSignatureRequestByToken(string $token): array
    {
        $request = $this->repo->findSignatureRequestByToken($token);
        if (! $request) {
            return ['success' => false, 'error' => 'Signature request not found.'];
        }

        if (($request['status'] ?? '') !== 'pending') {
            return ['success' => false, 'error' => 'This signature request has already been processed.'];
        }

        if (strtotime($request['expires_at'] ?? '') < time()) {
            return ['success' => false, 'error' => 'This signature request has expired.'];
        }

        return ['success' => true, 'request' => $request];
    }

    /**
     * @return array{success: bool, error?: string}
     */
    public function signDocument(int $requestId, string $signatureData, string $ipAddress): array
    {
        $request = $this->repo->findSignatureRequestByToken('');
        // Get the request by ID instead
        $stmt = $this->repo->pdo()->prepare('SELECT * FROM document_signature_requests WHERE id = :id');
        $stmt->execute(['id' => $requestId]);
        $request = $stmt->fetch();

        if (! $request) {
            return ['success' => false, 'error' => 'Signature request not found.'];
        }

        if (($request['status'] ?? '') !== 'pending') {
            return ['success' => false, 'error' => 'This signature request has already been processed.'];
        }

        // Store signature
        $signaturePath = $this->storeSignature($signatureData);
        if (! $signaturePath) {
            return ['success' => false, 'error' => 'Failed to store signature.'];
        }

        $this->repo->markSignatureSigned($requestId, $signaturePath, $ipAddress);

        AuditLogger::log('documents', 'document_signed', 'document_signature_request', $requestId, ['status' => 'pending'], [
            'status' => 'signed',
            'signature_path' => $signaturePath,
        ]);

        return ['success' => true];
    }

    /**
     * @return array{success: bool, error?: string}
     */
    public function declineSignature(int $requestId): array
    {
        $this->repo->markSignatureDeclined($requestId);

        AuditLogger::log('documents', 'signature_declined', 'document_signature_request', $requestId, ['status' => 'pending'], [
            'status' => 'declined',
        ]);

        return ['success' => true];
    }

    public function getDocumentSignatureRequests(int $documentId): array
    {
        return $this->repo->getDocumentSignatureRequests($documentId);
    }

    private function storeSignature(string $signatureData): ?string
    {
        $dir = public_path('uploads/signatures');
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        
        // Remove data URL prefix if present
        if (strpos($signatureData, 'data:') === 0) {
            $signatureData = substr($signatureData, strpos($signatureData, ',') + 1);
        }
        
        $signatureData = base64_decode($signatureData);
        if ($signatureData === false) {
            return null;
        }
        
        $name = uniqid('signature_', true) . '.png';
        $path = $dir . '/' . $name;
        
        if (file_put_contents($path, $signatureData) === false) {
            return null;
        }

        return '/uploads/signatures/' . $name;
    }
}
