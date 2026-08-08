<?php

namespace App\Repositories;

use App\Support\DepartmentFilter;
use PDO;

class VisitorRepository
{
    use UsesPdo;

    private function areasConcatSql(): string
    {
        $driver = $this->pdo()->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            return "GROUP_CONCAT(va.name, ', ')";
        }

        return "GROUP_CONCAT(va.name SEPARATOR ', ')";
    }

    public function allVisits(): array
    {
        [$deptSql, $deptBindings] = DepartmentFilter::sqlOnColumn('e.department_id');
        $areasExpr = $this->areasConcatSql();
        $sql = "SELECT v.*, vt.full_name AS visitor_name, vt.company AS visitor_company,
                       vt.is_watchlisted, vt.photo_path AS visitor_photo, e.full_name AS host_name,
                       (SELECT {$areasExpr}
                        FROM visit_area_access vaa
                        JOIN visit_areas va ON va.id = vaa.visit_area_id
                        WHERE vaa.visit_id = v.id) AS allowed_areas
                FROM visits v
                JOIN visitors vt ON vt.id = v.visitor_id
                JOIN employees e ON e.id = v.host_employee_id
                WHERE v.deleted_at IS NULL
                  AND vt.deleted_at IS NULL
                  {$deptSql}
                ORDER BY v.scheduled_at DESC, v.id DESC";
        if ($deptBindings === []) {
            return $this->pdo()->query($sql)->fetchAll();
        }
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($deptBindings);

        return $stmt->fetchAll();
    }

    public function allVisitors(): array
    {
        return $this->pdo()->query('SELECT * FROM visitors WHERE deleted_at IS NULL ORDER BY full_name')->fetchAll();
    }

    public function allEmployees(): array
    {
        return $this->pdo()->query('SELECT id, full_name, position FROM employees ORDER BY full_name')->fetchAll();
    }

    public function allAreas(): array
    {
        return $this->pdo()->query('SELECT * FROM visit_areas ORDER BY name')->fetchAll();
    }

    public function findVisit(int $id): ?array
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM visits WHERE id = :id AND deleted_at IS NULL');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findVisitor(int $id): ?array
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM visitors WHERE id = :id AND deleted_at IS NULL');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /** Find an existing watchlisted visitor matching name, id number, or email. */
    public function findWatchlistedMatch(?string $fullName, ?string $idNumber, ?string $email, ?int $excludeId = null): ?array
    {
        $clauses = ['is_watchlisted = 1', 'deleted_at IS NULL'];
        $params = [];

        $or = [];
        if ($fullName !== null && trim($fullName) !== '') {
            $or[] = 'LOWER(full_name) = LOWER(:full_name)';
            $params['full_name'] = trim($fullName);
        }
        if ($idNumber !== null && trim($idNumber) !== '') {
            $or[] = 'id_number = :id_number';
            $params['id_number'] = trim($idNumber);
        }
        if ($email !== null && trim($email) !== '') {
            $or[] = 'LOWER(email) = LOWER(:email)';
            $params['email'] = trim($email);
        }
        if ($or === []) {
            return null;
        }
        $clauses[] = '(' . implode(' OR ', $or) . ')';
        if ($excludeId) {
            $clauses[] = 'id != :exclude_id';
            $params['exclude_id'] = $excludeId;
        }

        $sql = 'SELECT * FROM visitors WHERE ' . implode(' AND ', $clauses) . ' LIMIT 1';
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function createVisitor(array $data): int
    {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO visitors (full_name, id_number, company, email, phone, photo_path, is_watchlisted)
             VALUES (:full_name, :id_number, :company, :email, :phone, :photo_path, :is_watchlisted)'
        );
        $stmt->execute([
            'full_name' => $data['full_name'],
            'id_number' => $data['id_number'] ?? null,
            'company' => $data['company'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'photo_path' => $data['photo_path'] ?? null,
            'is_watchlisted' => ! empty($data['is_watchlisted']) ? 1 : 0,
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    public function updateVisitorPhoto(int $visitorId, string $photoPath): void
    {
        $stmt = $this->pdo()->prepare('UPDATE visitors SET photo_path = :path WHERE id = :id');
        $stmt->execute(['path' => $photoPath, 'id' => $visitorId]);
    }

    public function createVisit(array $data): int
    {
        $status = $data['status'] ?? 'scheduled';
        $stmt = $this->pdo()->prepare(
            'INSERT INTO visits (visitor_id, host_employee_id, purpose, scheduled_at, status, notes)
             VALUES (:visitor_id, :host_employee_id, :purpose, :scheduled_at, :status, :notes)'
        );
        $stmt->execute([
            'visitor_id' => $data['visitor_id'],
            'host_employee_id' => $data['host_employee_id'],
            'purpose' => $data['purpose'] ?? null,
            'scheduled_at' => $data['scheduled_at'] ?? null,
            'status' => $status,
            'notes' => $data['notes'] ?? null,
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    public function addVisitAreaAccess(int $visitId, int $areaId): void
    {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO visit_area_access (visit_id, visit_area_id) VALUES (:visit_id, :area_id)'
        );
        $stmt->execute(['visit_id' => $visitId, 'area_id' => $areaId]);
    }

    public function clearVisitAreaAccess(int $visitId): void
    {
        $stmt = $this->pdo()->prepare('DELETE FROM visit_area_access WHERE visit_id = :visit_id');
        $stmt->execute(['visit_id' => $visitId]);
    }

    public function updateVisitStatus(int $visitId, string $status, ?string $checkIn, ?string $checkOut, ?string $badgeCode = null): void
    {
        $sql = 'UPDATE visits SET status = :status';
        $params = ['status' => $status, 'id' => $visitId];

        if ($checkIn !== null) {
            $sql .= ', check_in_time = :check_in';
            $params['check_in'] = $checkIn;
        }
        if ($checkOut !== null) {
            $sql .= ', check_out_time = :check_out';
            $params['check_out'] = $checkOut;
        }
        if ($badgeCode !== null) {
            $sql .= ', badge_code = :badge_code';
            $params['badge_code'] = $badgeCode;
        }
        $sql .= ' WHERE id = :id';

        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);
    }

    public function toggleWatchlist(int $visitorId, int $watchlist): void
    {
        $stmt = $this->pdo()->prepare('UPDATE visitors SET is_watchlisted = :wl WHERE id = :id');
        $stmt->execute(['wl' => $watchlist, 'id' => $visitorId]);
    }

    public function countOnSite(): int
    {
        return (int) $this->pdo()
            ->query("SELECT COUNT(*) FROM visits WHERE status = 'checked_in' AND deleted_at IS NULL")
            ->fetchColumn();
    }
}
