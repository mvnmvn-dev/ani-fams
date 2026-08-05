<?php

namespace App\Repositories;

use PDO;

class DocumentRepository
{
    use UsesPdo;

    public function all(int $userId, string $roleCode, int $roleId, bool $canManageAll): array
    {
        if ($canManageAll) {
            $sql = "SELECT d.*, dc.name AS category_name, u.full_name AS owner_name, dv.file_path, dv.version_no
                    FROM documents d
                    LEFT JOIN document_categories dc ON dc.id = d.category_id
                    JOIN users u ON u.id = d.owner_id
                    LEFT JOIN document_versions dv ON dv.id = d.current_version_id
                    WHERE d.status != 'disposed'
                    ORDER BY d.created_at DESC";
            return $this->pdo()->query($sql)->fetchAll();
        } else {
            // Filtered by access logic
            $sql = "SELECT DISTINCT d.*, dc.name AS category_name, u.full_name AS owner_name, dv.file_path, dv.version_no
                    FROM documents d
                    LEFT JOIN document_categories dc ON dc.id = d.category_id
                    JOIN users u ON u.id = d.owner_id
                    LEFT JOIN document_versions dv ON dv.id = d.current_version_id
                    LEFT JOIN document_access da ON da.document_id = d.id
                    WHERE d.status != 'disposed'
                      AND (d.owner_id = :owner_id
                           OR d.confidentiality = 'public'
                           OR da.user_id = :access_user_id
                           OR da.role_id = :role_id)
                    ORDER BY d.created_at DESC";
            $stmt = $this->pdo()->prepare($sql);
            $stmt->execute([
                'owner_id'       => $userId,
                'access_user_id' => $userId,
                'role_id'        => $roleId,
            ]);
            return $stmt->fetchAll();
        }
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo()->prepare(
            "SELECT d.*, dc.name AS category_name, u.full_name AS owner_name, dv.file_path, dv.version_no
             FROM documents d
             LEFT JOIN document_categories dc ON dc.id = d.category_id
             JOIN users u ON u.id = d.owner_id
             LEFT JOIN document_versions dv ON dv.id = d.current_version_id
             WHERE d.id = :id"
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function allCategories(): array
    {
        return $this->pdo()->query("SELECT * FROM document_categories ORDER BY name")->fetchAll();
    }

    public function allTags(): array
    {
        return $this->pdo()->query("SELECT * FROM document_tags ORDER BY name")->fetchAll();
    }

    public function createDocument(array $data): int
    {
        $stmt = $this->pdo()->prepare(
            "INSERT INTO documents (category_id, title, description, document_number, owner_id, confidentiality, status)
             VALUES (:category_id, :title, :description, :document_number, :owner_id, :confidentiality, 'active')"
        );
        $stmt->execute([
            'category_id'     => $data['category_id'] ? (int) $data['category_id'] : null,
            'title'           => $data['title'],
            'description'     => $data['description'] ?? null,
            'document_number' => $data['document_number'] ?? null,
            'owner_id'        => $data['owner_id'],
            'confidentiality' => $data['confidentiality'] ?? 'internal',
        ]);
        return (int) $this->pdo()->lastInsertId();
    }

    public function createDocumentVersion(array $data): int
    {
        $stmt = $this->pdo()->prepare(
            "INSERT INTO document_versions (document_id, version_no, file_path, checksum, uploaded_by, change_notes)
             VALUES (:document_id, :version_no, :file_path, :checksum, :uploaded_by, :change_notes)"
        );
        $stmt->execute([
            'document_id' => $data['document_id'],
            'version_no'  => $data['version_no'],
            'file_path'   => $data['file_path'],
            'checksum'    => $data['checksum'] ?? null,
            'uploaded_by' => $data['uploaded_by'],
            'change_notes'=> $data['change_notes'] ?? null,
        ]);
        return (int) $this->pdo()->lastInsertId();
    }

    public function updateCurrentVersion(int $documentId, int $versionId): void
    {
        $stmt = $this->pdo()->prepare(
            "UPDATE documents SET current_version_id = :version_id WHERE id = :id"
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
        $stmt = $this->pdo()->prepare("SELECT id FROM document_tags WHERE name = :name");
        $stmt->execute(['name' => $name]);
        $id = $stmt->fetchColumn();
        if ($id) {
            return (int) $id;
        }

        $stmt = $this->pdo()->prepare("INSERT INTO document_tags (name) VALUES (:name)");
        $stmt->execute(['name' => $name]);
        return (int) $this->pdo()->lastInsertId();
    }

    public function addTagToDocument(int $documentId, int $tagId): void
    {
        $stmt = $this->pdo()->prepare(
            "INSERT INTO document_tag_map (document_id, tag_id) VALUES (:docId, :tagId)
             ON DUPLICATE KEY UPDATE tag_id = tag_id"
        );
        $stmt->execute(['docId' => $documentId, 'tagId' => $tagId]);
    }

    public function clearDocumentTags(int $documentId): void
    {
        $stmt = $this->pdo()->prepare("DELETE FROM document_tag_map WHERE document_id = :docId");
        $stmt->execute(['docId' => $documentId]);
    }

    public function addDocumentAccess(int $documentId, ?int $userId, ?int $roleId, string $accessLevel): void
    {
        // PK columns cannot be NULL in MySQL — use 0 for the unused side (user vs role).
        $stmt = $this->pdo()->prepare(
            "INSERT INTO document_access (document_id, user_id, role_id, access_level)
             VALUES (:docId, :userId, :roleId, :level)
             ON DUPLICATE KEY UPDATE access_level = VALUES(access_level)"
        );
        $stmt->execute([
            'docId' => $documentId,
            'userId' => $userId ?: 0,
            'roleId' => $roleId ?: 0,
            'level' => $accessLevel,
        ]);
    }

    public function clearDocumentAccess(int $documentId): void
    {
        $stmt = $this->pdo()->prepare("DELETE FROM document_access WHERE document_id = :docId");
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
            ->query("SELECT COUNT(*) FROM documents WHERE status = 'active'")
            ->fetchColumn();
    }
}
