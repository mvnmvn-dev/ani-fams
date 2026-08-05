<?php

namespace App\Repositories;

use PDO;

class AuditLogRepository
{
    use UsesPdo;

    public function recent(int $limit = 100, array $filters = []): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['module'])) {
            $where[] = 'a.module = :module';
            $params['module'] = $filters['module'];
        }
        if (!empty($filters['action'])) {
            $where[] = 'a.action = :action';
            $params['action'] = $filters['action'];
        }
        if (!empty($filters['q'])) {
            $where[] = '(u.full_name LIKE :q OR a.entity_type LIKE :q OR a.entity_id LIKE :q OR a.action LIKE :q OR a.module LIKE :q)';
            $params['q'] = '%' . $filters['q'] . '%';
        }

        $sql = 'SELECT a.*, u.full_name AS actor_name, u.email AS actor_email
                FROM audit_logs a
                LEFT JOIN users u ON u.id = a.user_id';

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY a.created_at DESC, a.id DESC LIMIT :limit';

        $stmt = $this->pdo()->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function modules(): array
    {
        return $this->pdo()
            ->query('SELECT DISTINCT module FROM audit_logs ORDER BY module')
            ->fetchAll(PDO::FETCH_COLUMN);
    }

    public function actions(): array
    {
        return $this->pdo()
            ->query('SELECT DISTINCT action FROM audit_logs ORDER BY action')
            ->fetchAll(PDO::FETCH_COLUMN);
    }

    public function count(): int
    {
        return (int) $this->pdo()->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn();
    }
}
