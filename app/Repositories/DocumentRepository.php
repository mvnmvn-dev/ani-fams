<?php

namespace App\Repositories;

use App\Support\DepartmentFilter;
use PDO;

class DocumentRepository
{
    use UsesPdo;

    public function all(int $userId, string $roleCode, int $roleId, bool $canManageAll): array
    {
        [$deptSql, $deptBindings] = DepartmentFilter::sqlOnUserAlias('u');

        if ($canManageAll) {
            $sql = "SELECT d.*, dc.name AS category_name, u.full_name AS owner_name, dv.file_path, dv.version_no
                    FROM documents d
                    LEFT JOIN document_categories dc ON dc.id = d.category_id
                    JOIN users u ON u.id = d.owner_id
                    LEFT JOIN document_versions dv ON dv.id = d.current_version_id
                    WHERE d.status != 'disposed'
                      AND d.deleted_at IS NULL
                      AND u.deleted_at IS NULL
                      {$deptSql}
                    ORDER BY d.created_at DESC";
            if ($deptBindings === []) {
                return $this->pdo()->query($sql)->fetchAll();
            }
            $stmt = $this->pdo()->prepare($sql);
            $stmt->execute($deptBindings);

            return $stmt->fetchAll();
        }

        $sql = "SELECT DISTINCT d.*, dc.name AS category_name, u.full_name AS owner_name, dv.file_path, dv.version_no
                FROM documents d
                LEFT JOIN document_categories dc ON dc.id = d.category_id
                JOIN users u ON u.id = d.owner_id
                LEFT JOIN document_versions dv ON dv.id = d.current_version_id
                LEFT JOIN document_access da ON da.document_id = d.id
                WHERE d.status != 'disposed'
                  AND d.deleted_at IS NULL
                  AND u.deleted_at IS NULL
                  AND (d.owner_id = :owner_id
                       OR d.confidentiality = 'public'
                       OR da.user_id = :access_user_id
                       OR da.role_id = :role_id)
                  {$deptSql}
                ORDER BY d.created_at DESC";
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute(array_merge([
            'owner_id' => $userId,
            'access_user_id' => $userId,
            'role_id' => $roleId,
        ], $deptBindings));

        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo()->prepare(
            "SELECT d.*, dc.name AS category_name, u.full_name AS owner_name, dv.file_path, dv.version_no
             FROM documents d
             LEFT JOIN document_categories dc ON dc.id = d.category_id
             JOIN users u ON u.id = d.owner_id
             LEFT JOIN document_versions dv ON dv.id = d.current_version_id
             WHERE d.id = :id AND d.deleted_at IS NULL"
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function updateAiAnalysis(int $documentId, array $analysis): void
    {
        $stmt = $this->pdo()->prepare(
            'UPDATE documents SET ai_category = :classification, ai_classification = :classification, ai_summary = :summary, ai_keywords = :keywords, ai_metadata = :metadata, ai_confidence = :confidence, ai_processed_at = CURRENT_TIMESTAMP WHERE id = :id'
        );
        $stmt->execute([
            'classification' => $analysis['classification'] ?? null,
            'summary' => $analysis['summary'] ?? null,
            'keywords' => json_encode(array_values($analysis['keywords'] ?? []), JSON_UNESCAPED_UNICODE),
            'metadata' => json_encode($analysis['metadata'] ?? [], JSON_UNESCAPED_UNICODE),
            'confidence' => $analysis['confidence'] ?? null,
            'id' => $documentId,
        ]);
    }

    public function softDelete(int $id): void
    {
        $stmt = $this->pdo()->prepare('UPDATE documents SET deleted_at = CURRENT_TIMESTAMP WHERE id = :id AND deleted_at IS NULL');
        $stmt->execute(['id' => $id]);
    }

    public function findVersion(int $documentId, int $versionNo): ?array
    {
        $stmt = $this->pdo()->prepare(
            "SELECT dv.*, u.full_name AS uploaded_by_name
             FROM document_versions dv
             JOIN users u ON u.id = dv.uploaded_by
             WHERE dv.document_id = :docId AND dv.version_no = :vno"
        );
        $stmt->execute(['docId' => $documentId, 'vno' => $versionNo]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function allCategories(): array
    {
        return $this->pdo()->query('SELECT * FROM document_categories ORDER BY name')->fetchAll();
    }

    public function allTags(): array
    {
        return $this->pdo()->query('SELECT * FROM document_tags ORDER BY name')->fetchAll();
    }

    public function createDocument(array $data): int
    {
        $stmt = $this->pdo()->prepare(
            "INSERT INTO documents (category_id, title, description, document_number, owner_id, confidentiality, status)
             VALUES (:category_id, :title, :description, :document_number, :owner_id, :confidentiality, 'active')"
        );
        $stmt->execute([
            'category_id' => $data['category_id'] ? (int) $data['category_id'] : null,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'document_number' => $data['document_number'] ?? null,
            'owner_id' => $data['owner_id'],
            'confidentiality' => $data['confidentiality'] ?? 'internal',
        ]);

        return $this->insertId('documents');
    }

    public function findDocumentByChecksum(string $checksum): ?array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT d.id, d.title, d.document_number FROM document_versions dv JOIN documents d ON d.id = dv.document_id WHERE dv.checksum = :checksum AND d.deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute(['checksum' => $checksum]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function createDocumentVersion(array $data): int
    {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO document_versions (document_id, version_no, file_path, checksum, content_text, uploaded_by, change_notes)
             VALUES (:document_id, :version_no, :file_path, :checksum, :content_text, :uploaded_by, :change_notes)'
        );
        $stmt->execute([
            'document_id' => $data['document_id'],
            'version_no' => $data['version_no'],
            'file_path' => $data['file_path'],
            'checksum' => $data['checksum'] ?? null,
            'content_text' => $data['content_text'] ?? null,
            'uploaded_by' => $data['uploaded_by'],
            'change_notes' => $data['change_notes'] ?? null,
        ]);

        return $this->insertId('document_versions');
    }

    public function searchContent(string $query, int $userId, int $roleId, bool $canManageAll): array
    {
        $q = trim($query);
        if ($q === '') {
            return [];
        }
        $like = '%'.$q.'%';
        [$deptSql, $deptBindings] = DepartmentFilter::sqlOnUserAlias('u');

        if ($canManageAll) {
            $sql = "SELECT DISTINCT d.*, dc.name AS category_name, u.full_name AS owner_name, dv.file_path, dv.version_no,
                           SUBSTR(dv.content_text, 1, 240) AS content_snippet
                    FROM documents d
                    LEFT JOIN document_categories dc ON dc.id = d.category_id
                    JOIN users u ON u.id = d.owner_id
                    LEFT JOIN document_versions dv ON dv.id = d.current_version_id
                    WHERE d.status != 'disposed'
                      AND d.deleted_at IS NULL
                      AND u.deleted_at IS NULL
                      AND (
                        d.title LIKE :q1 OR d.description LIKE :q2 OR d.document_number LIKE :q3
                        OR dv.content_text LIKE :q4
                      )
                      {$deptSql}
                    ORDER BY d.created_at DESC
                    LIMIT 50";
            $stmt = $this->pdo()->prepare($sql);
            $stmt->execute(array_merge([
                'q1' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like,
            ], $deptBindings));

            return $stmt->fetchAll();
        }

        $sql = "SELECT DISTINCT d.*, dc.name AS category_name, u.full_name AS owner_name, dv.file_path, dv.version_no,
                       SUBSTR(dv.content_text, 1, 240) AS content_snippet
                FROM documents d
                LEFT JOIN document_categories dc ON dc.id = d.category_id
                JOIN users u ON u.id = d.owner_id
                LEFT JOIN document_versions dv ON dv.id = d.current_version_id
                LEFT JOIN document_access da ON da.document_id = d.id
                WHERE d.status != 'disposed'
                  AND d.deleted_at IS NULL
                  AND u.deleted_at IS NULL
                  AND (d.owner_id = :owner_id
                       OR d.confidentiality = 'public'
                       OR da.user_id = :access_user_id
                       OR da.role_id = :role_id)
                  AND (
                    d.title LIKE :q1 OR d.description LIKE :q2 OR d.document_number LIKE :q3
                    OR dv.content_text LIKE :q4
                  )
                  {$deptSql}
                ORDER BY d.created_at DESC
                LIMIT 50";
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute(array_merge([
            'owner_id' => $userId,
            'access_user_id' => $userId,
            'role_id' => $roleId,
            'q1' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like,
        ], $deptBindings));

        return $stmt->fetchAll();
    }

    public function updateCurrentVersion(int $documentId, int $versionId): void
    {
        $stmt = $this->pdo()->prepare(
            'UPDATE documents SET current_version_id = :version_id WHERE id = :id'
        );
        $stmt->execute(['version_id' => $versionId, 'id' => $documentId]);
    }

    public function getVersions(int $documentId): array
    {
        $stmt = $this->pdo()->prepare(
            "SELECT dv.*, u.full_name AS uploaded_by_name
             FROM document_versions dv
             JOIN users u ON u.id = dv.uploaded_by
             WHERE dv.document_id = :docId
             ORDER BY dv.version_no DESC"
        );
        $stmt->execute(['docId' => $documentId]);

        return $stmt->fetchAll();
    }

    public function findOrCreateTag(string $name): int
    {
        $name = trim(strtolower($name));
        $stmt = $this->pdo()->prepare('SELECT id FROM document_tags WHERE name = :name');
        $stmt->execute(['name' => $name]);
        $id = $stmt->fetchColumn();
        if ($id) {
            return (int) $id;
        }

        $stmt = $this->pdo()->prepare('INSERT INTO document_tags (name) VALUES (:name)');
        $stmt->execute(['name' => $name]);

        return $this->insertId('document_tags');
    }

    public function addTagToDocument(int $documentId, int $tagId): void
    {
        $driver = $this->pdo()->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $stmt = $this->pdo()->prepare(
                'INSERT OR IGNORE INTO document_tag_map (document_id, tag_id) VALUES (:docId, :tagId)'
            );
        } elseif ($driver === 'pgsql') {
            $stmt = $this->pdo()->prepare(
                'INSERT INTO document_tag_map (document_id, tag_id)
                 VALUES (:docId, :tagId)
                 ON CONFLICT (document_id, tag_id) DO NOTHING'
            );
        } else {
            $stmt = $this->pdo()->prepare(
                'INSERT INTO document_tag_map (document_id, tag_id) VALUES (:docId, :tagId)
                 ON DUPLICATE KEY UPDATE tag_id = tag_id'
            );
        }
        $stmt->execute(['docId' => $documentId, 'tagId' => $tagId]);
    }

    public function clearDocumentTags(int $documentId): void
    {
        $stmt = $this->pdo()->prepare('DELETE FROM document_tag_map WHERE document_id = :docId');
        $stmt->execute(['docId' => $documentId]);
    }

    public function addDocumentAccess(int $documentId, ?int $userId, ?int $roleId, string $accessLevel): void
    {
        $driver = $this->pdo()->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $stmt = $this->pdo()->prepare(
                'INSERT INTO document_access (document_id, user_id, role_id, access_level)
                 VALUES (:docId, :userId, :roleId, :level)
                 ON CONFLICT(document_id, user_id, role_id) DO UPDATE SET access_level = excluded.access_level'
            );
            try {
                $stmt->execute([
                    'docId' => $documentId,
                    'userId' => $userId ?: 0,
                    'roleId' => $roleId ?: 0,
                    'level' => $accessLevel,
                ]);
            } catch (\Throwable $e) {
                $stmt = $this->pdo()->prepare(
                    'INSERT OR REPLACE INTO document_access (document_id, user_id, role_id, access_level)
                     VALUES (:docId, :userId, :roleId, :level)'
                );
                $stmt->execute([
                    'docId' => $documentId,
                    'userId' => $userId ?: 0,
                    'roleId' => $roleId ?: 0,
                    'level' => $accessLevel,
                ]);
            }
        } elseif ($driver === 'pgsql') {
            $stmt = $this->pdo()->prepare(
                'INSERT INTO document_access (document_id, user_id, role_id, access_level)
                 VALUES (:docId, :userId, :roleId, :level)
                 ON CONFLICT (document_id, user_id, role_id) DO UPDATE
                 SET access_level = EXCLUDED.access_level'
            );
            $stmt->execute([
                'docId' => $documentId,
                'userId' => $userId ?: 0,
                'roleId' => $roleId ?: 0,
                'level' => $accessLevel,
            ]);
        } else {
            $stmt = $this->pdo()->prepare(
                'INSERT INTO document_access (document_id, user_id, role_id, access_level)
                 VALUES (:docId, :userId, :roleId, :level)
                 ON DUPLICATE KEY UPDATE access_level = VALUES(access_level)'
            );
            $stmt->execute([
                'docId' => $documentId,
                'userId' => $userId ?: 0,
                'roleId' => $roleId ?: 0,
                'level' => $accessLevel,
            ]);
        }
    }

    public function clearDocumentAccess(int $documentId): void
    {
        $stmt = $this->pdo()->prepare('DELETE FROM document_access WHERE document_id = :docId');
        $stmt->execute(['docId' => $documentId]);
    }

    public function getDocumentAccess(int $documentId): array
    {
        $stmt = $this->pdo()->prepare(
            "SELECT da.*, u.full_name AS user_name, r.name AS role_name
             FROM document_access da
             LEFT JOIN users u ON u.id = da.user_id
             LEFT JOIN roles r ON r.id = da.role_id
             WHERE da.document_id = :docId"
        );
        $stmt->execute(['docId' => $documentId]);

        return $stmt->fetchAll();
    }

    public function countActive(): int
    {
        return (int) $this->pdo()
            ->query("SELECT COUNT(*) FROM documents WHERE status = 'active' AND deleted_at IS NULL")
            ->fetchColumn();
    }

    // ===================== DOCUMENT REVIEW/EXPIRATION REMINDERS =====================

    public function getDocumentsNeedingReview(): array
    {
        return $this->pdo()->query(
            "SELECT d.*, dc.name AS category_name, u.full_name AS owner_name
             FROM documents d
             LEFT JOIN document_categories dc ON dc.id = d.category_id
             JOIN users u ON u.id = d.owner_id
             WHERE d.review_by_date <= CURRENT_DATE
               AND d.review_reminder_sent_at IS NULL
               AND d.status = 'active'
               AND d.deleted_at IS NULL
             ORDER BY d.review_by_date ASC"
        )->fetchAll();
    }

    public function markReviewReminderSent(int $documentId): void
    {
        $stmt = $this->pdo()->prepare(
            'UPDATE documents SET review_reminder_sent_at = CURRENT_TIMESTAMP WHERE id = :id'
        );
        $stmt->execute(['id' => $documentId]);
    }

    public function markDocumentReviewed(int $documentId, int $reviewedBy): void
    {
        $stmt = $this->pdo()->prepare(
            'UPDATE documents 
             SET last_reviewed_at = CURRENT_TIMESTAMP, 
                 last_reviewed_by = :reviewed_by,
                 review_reminder_sent_at = NULL
             WHERE id = :id'
        );
        $stmt->execute(['reviewed_by' => $reviewedBy, 'id' => $documentId]);
    }

    public function updateReviewDate(int $documentId, string $reviewDate): void
    {
        $stmt = $this->pdo()->prepare(
            'UPDATE documents SET review_by_date = :review_date, review_reminder_sent_at = NULL WHERE id = :id'
        );
        $stmt->execute(['review_date' => $reviewDate, 'id' => $documentId]);
    }

    // ===================== CHECK-OUT/LOCK FUNCTIONALITY =====================

    public function checkOutDocument(int $documentId, int $userId, ?string $notes = null): void
    {
        $stmt = $this->pdo()->prepare(
            'UPDATE documents 
             SET checked_out_by = :user_id, 
                 checked_out_at = CURRENT_TIMESTAMP,
                 checked_out_notes = :notes
             WHERE id = :id'
        );
        $stmt->execute(['user_id' => $userId, 'notes' => $notes, 'id' => $documentId]);
    }

    public function checkInDocument(int $documentId): void
    {
        $stmt = $this->pdo()->prepare(
            'UPDATE documents 
             SET checked_out_by = NULL, 
                 checked_out_at = NULL,
                 checked_out_notes = NULL
             WHERE id = :id'
        );
        $stmt->execute(['id' => $documentId]);
    }

    public function getCheckedOutDocuments(): array
    {
        return $this->pdo()->query(
            "SELECT d.*, u.full_name AS checked_out_by_name, 
                    d.checked_out_at, d.checked_out_notes
             FROM documents d
             JOIN users u ON u.id = d.checked_out_by
             WHERE d.checked_out_by IS NOT NULL
               AND d.deleted_at IS NULL
             ORDER BY d.checked_out_at ASC"
        )->fetchAll();
    }

    // ===================== OCR PROCESSING =====================

    public function markOcrProcessed(int $versionId, float $confidence): void
    {
        $stmt = $this->pdo()->prepare(
            'UPDATE document_versions 
             SET ocr_processed = '.$this->boolLiteral(true).', 
                 ocr_processed_at = CURRENT_TIMESTAMP,
                 ocr_confidence = :confidence
             WHERE id = :id'
        );
        $stmt->execute(['confidence' => $confidence, 'id' => $versionId]);
    }

    public function getVersionsNeedingOcr(): array
    {
        return $this->pdo()->query(
            "SELECT dv.*, d.title AS document_title
             FROM document_versions dv
             JOIN documents d ON d.id = dv.document_id
             WHERE {$this->isFalseCondition('dv.ocr_processed')}
               AND d.deleted_at IS NULL
             ORDER BY dv.uploaded_at ASC
             LIMIT 100"
        )->fetchAll();
    }

    // ===================== BULK UPLOAD TRACKING =====================

    public function createBulkUpload(array $data): int
    {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO bulk_uploads (uploaded_by, module, file_count, status)
             VALUES (:uploaded_by, :module, :file_count, :status)'
        );
        $stmt->execute([
            'uploaded_by' => $data['uploaded_by'],
            'module' => $data['module'],
            'file_count' => $data['file_count'],
            'status' => $data['status'] ?? 'processing',
        ]);

        return $this->insertId('bulk_uploads');
    }

    public function updateBulkUploadStatus(int $uploadId, string $status, ?string $errorMessage = null): void
    {
        $sql = 'UPDATE bulk_uploads SET status = :status';
        $params = ['status' => $status, 'id' => $uploadId];

        if ($status === 'completed' || $status === 'failed') {
            $sql .= ', completed_at = CURRENT_TIMESTAMP';
        }

        if ($errorMessage) {
            $sql .= ', error_message = :error_message';
            $params['error_message'] = $errorMessage;
        }

        $sql .= ' WHERE id = :id';
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);
    }

    public function getBulkUploads(int $userId): array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT * FROM bulk_uploads WHERE uploaded_by = :user_id ORDER BY created_at DESC LIMIT 50'
        );
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll();
    }

    // ===================== E-SIGNATURE REQUESTS =====================

    public function createSignatureRequest(array $data): int
    {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO document_signature_requests 
             (document_id, requested_by, signer_email, signer_name, message, token, expires_at)
             VALUES (:document_id, :requested_by, :signer_email, :signer_name, :message, :token, :expires_at)'
        );
        $stmt->execute([
            'document_id' => $data['document_id'],
            'requested_by' => $data['requested_by'],
            'signer_email' => $data['signer_email'],
            'signer_name' => $data['signer_name'],
            'message' => $data['message'] ?? null,
            'token' => $data['token'],
            'expires_at' => $data['expires_at'],
        ]);

        return $this->insertId('document_signature_requests');
    }

    public function findSignatureRequestByToken(string $token): ?array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT dsr.*, d.title AS document_title 
             FROM document_signature_requests dsr
             JOIN documents d ON d.id = dsr.document_id
             WHERE dsr.token = :token'
        );
        $stmt->execute(['token' => $token]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function markSignatureSigned(int $requestId, string $signaturePath, string $ipAddress): void
    {
        $stmt = $this->pdo()->prepare(
            'UPDATE document_signature_requests 
             SET status = :status,
                 signed_at = CURRENT_TIMESTAMP,
                 signature_path = :signature_path,
                 signature_ip = :ip_address
             WHERE id = :id'
        );
        $stmt->execute([
            'status' => 'signed',
            'signature_path' => $signaturePath,
            'ip_address' => $ipAddress,
            'id' => $requestId,
        ]);
    }

    public function markSignatureDeclined(int $requestId): void
    {
        $stmt = $this->pdo()->prepare(
            'UPDATE document_signature_requests SET status = :status WHERE id = :id'
        );
        $stmt->execute(['status' => 'declined', 'id' => $requestId]);
    }

    public function getDocumentSignatureRequests(int $documentId): array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT * FROM document_signature_requests WHERE document_id = :document_id ORDER BY created_at DESC'
        );
        $stmt->execute(['document_id' => $documentId]);

        return $stmt->fetchAll();
    }

    public function expirePendingSignatureRequests(): int
    {
        $stmt = $this->pdo()->prepare(
            "UPDATE document_signature_requests 
             SET status = 'expired' 
             WHERE status = 'pending' AND expires_at < CURRENT_TIMESTAMP"
        );
        $stmt->execute();

        return $stmt->rowCount();
    }
}
