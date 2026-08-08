<?php

namespace App\Repositories;

use App\Support\DepartmentFilter;
use PDO;

class ReservationRepository
{
    use UsesPdo;

    public function allFacilities(): array
    {
        $stmt = $this->pdo()->query(
            "SELECT id, name, type, location, capacity, status FROM facilities WHERE status = 'available' ORDER BY name"
        );

        return $stmt->fetchAll();
    }

    public function allFacilitiesIncludingMaintenance(): array
    {
        return $this->pdo()->query(
            "SELECT id, name, type, location, capacity, status FROM facilities WHERE status != 'retired' ORDER BY name"
        )->fetchAll();
    }

    public function setFacilityStatus(int $id, string $status): void
    {
        $stmt = $this->pdo()->prepare('UPDATE facilities SET status = :status WHERE id = :id');
        $stmt->execute(['status' => $status, 'id' => $id]);
    }

    public function all(): array
    {
        [$deptSql, $deptBindings] = DepartmentFilter::sqlOnUserAlias('u');
        $sql = "SELECT r.*, f.name AS facility_name, u.full_name AS requester_name
                FROM reservations r
                JOIN facilities f ON f.id = r.facility_id
                JOIN users u ON u.id = r.requested_by
                WHERE r.deleted_at IS NULL
                  AND u.deleted_at IS NULL
                  {$deptSql}
                ORDER BY r.start_datetime DESC";
        if ($deptBindings === []) {
            return $this->pdo()->query($sql)->fetchAll();
        }
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($deptBindings);

        return $stmt->fetchAll();
    }

    public function allForUser(int $userId): array
    {
        $stmt = $this->pdo()->prepare(
            "SELECT r.*, f.name AS facility_name, u.full_name AS requester_name
             FROM reservations r
             JOIN facilities f ON f.id = r.facility_id
             JOIN users u ON u.id = r.requested_by
             WHERE r.requested_by = :uid
               AND r.deleted_at IS NULL
             ORDER BY r.start_datetime DESC"
        );
        $stmt->execute(['uid' => $userId]);

        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM reservations WHERE id = :id AND deleted_at IS NULL');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function hasConflict(int $facilityId, string $start, string $end, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM reservations
             WHERE facility_id = :facility_id
               AND deleted_at IS NULL
               AND status IN ('pending','approved')
               AND start_datetime < :end
               AND end_datetime > :start";
        $params = ['facility_id' => $facilityId, 'start' => $start, 'end' => $end];
        if ($excludeId) {
            $sql .= ' AND id != :exclude';
            $params['exclude'] = $excludeId;
        }
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo()->prepare(
            "INSERT INTO reservations (
                facility_id, requested_by, purpose, start_datetime, end_datetime, status,
                series_id, recurrence_rule, recurrence_until, is_maintenance, approved_by, approved_at
             ) VALUES (
                :facility_id, :requested_by, :purpose, :start, :end, :status,
                :series_id, :recurrence_rule, :recurrence_until, :is_maintenance, :approved_by, :approved_at
             )"
        );
        $status = $data['status'] ?? 'pending';
        $isMaintenance = ! empty($data['is_maintenance']) ? 1 : 0;
        $stmt->execute([
            'facility_id' => $data['facility_id'],
            'requested_by' => $data['requested_by'],
            'purpose' => $data['purpose'],
            'start' => $data['start_datetime'],
            'end' => $data['end_datetime'],
            'status' => $status,
            'series_id' => $data['series_id'] ?? null,
            'recurrence_rule' => $data['recurrence_rule'] ?? 'none',
            'recurrence_until' => $data['recurrence_until'] ?? null,
            'is_maintenance' => $isMaintenance,
            'approved_by' => $data['approved_by'] ?? null,
            'approved_at' => $data['approved_at'] ?? null,
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    public function updateStatus(int $id, string $status, int $approverId): void
    {
        $stmt = $this->pdo()->prepare(
            'UPDATE reservations SET status = :status, approved_by = :approver, approved_at = CURRENT_TIMESTAMP WHERE id = :id'
        );
        $stmt->execute(['status' => $status, 'approver' => $approverId, 'id' => $id]);
    }

    public function markReminderSent(int $id): void
    {
        $stmt = $this->pdo()->prepare(
            'UPDATE reservations SET reminder_sent_at = CURRENT_TIMESTAMP WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
    }

    public function allEquipment(): array
    {
        return $this->pdo()->query(
            "SELECT * FROM equipment_items WHERE status = 'available' ORDER BY name"
        )->fetchAll();
    }

    public function attachEquipment(int $reservationId, int $equipmentId, int $qty = 1): void
    {
        $driver = $this->pdo()->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $stmt = $this->pdo()->prepare(
                'INSERT OR REPLACE INTO reservation_equipment (reservation_id, equipment_id, quantity)
                 VALUES (:rid, :eid, :qty)'
            );
        } else {
            $stmt = $this->pdo()->prepare(
                'INSERT INTO reservation_equipment (reservation_id, equipment_id, quantity)
                 VALUES (:rid, :eid, :qty)
                 ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)'
            );
        }
        $stmt->execute(['rid' => $reservationId, 'eid' => $equipmentId, 'qty' => max(1, $qty)]);
    }

    public function getEquipmentForReservation(int $reservationId): array
    {
        $stmt = $this->pdo()->prepare(
            "SELECT e.*, re.quantity
             FROM reservation_equipment re
             JOIN equipment_items e ON e.id = re.equipment_id
             WHERE re.reservation_id = :rid"
        );
        $stmt->execute(['rid' => $reservationId]);

        return $stmt->fetchAll();
    }

    public function utilizationAnalytics(): array
    {
        return $this->pdo()->query(
            "SELECT f.id, f.name,
                    COUNT(r.id) AS booking_count,
                    COALESCE(SUM(
                        (JULIANDAY(r.end_datetime) - JULIANDAY(r.start_datetime)) * 24
                    ), 0) AS hours_booked
             FROM facilities f
             LEFT JOIN reservations r ON r.facility_id = f.id
               AND r.deleted_at IS NULL
               AND r.status = 'approved'
               AND COALESCE(r.is_maintenance, 0) = 0
             WHERE f.status != 'retired'
             GROUP BY f.id, f.name
             ORDER BY booking_count DESC, f.name"
        )->fetchAll();
    }

    /** MySQL-friendly hours calculation (also works roughly on SQLite via fallback). */
    public function utilizationAnalyticsSafe(): array
    {
        $driver = $this->pdo()->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            return $this->utilizationAnalytics();
        }

        return $this->pdo()->query(
            "SELECT f.id, f.name,
                    COUNT(r.id) AS booking_count,
                    COALESCE(SUM(TIMESTAMPDIFF(MINUTE, r.start_datetime, r.end_datetime)) / 60, 0) AS hours_booked
             FROM facilities f
             LEFT JOIN reservations r ON r.facility_id = f.id
               AND r.deleted_at IS NULL
               AND r.status = 'approved'
               AND COALESCE(r.is_maintenance, 0) = 0
             WHERE f.status != 'retired'
             GROUP BY f.id, f.name
             ORDER BY booking_count DESC, f.name"
        )->fetchAll();
    }

    public function countPending(): int
    {
        return (int) $this->pdo()
            ->query("SELECT COUNT(*) FROM reservations WHERE status = 'pending' AND deleted_at IS NULL")
            ->fetchColumn();
    }

    public function countPendingForUser(int $userId): int
    {
        $stmt = $this->pdo()->prepare(
            "SELECT COUNT(*) FROM reservations WHERE status = 'pending' AND requested_by = :uid AND deleted_at IS NULL"
        );
        $stmt->execute(['uid' => $userId]);

        return (int) $stmt->fetchColumn();
    }

    /** User IDs that hold reservation.approve permission. */
    public function approverUserIds(): array
    {
        $stmt = $this->pdo()->query(
            "SELECT DISTINCT u.id
             FROM users u
             JOIN role_permissions rp ON rp.role_id = u.role_id
             JOIN permissions p ON p.id = rp.permission_id
             WHERE p.code = 'reservation.approve'
               AND u.status = 'active'
               AND u.deleted_at IS NULL"
        );

        return array_map('intval', array_column($stmt->fetchAll(), 'id'));
    }
}
