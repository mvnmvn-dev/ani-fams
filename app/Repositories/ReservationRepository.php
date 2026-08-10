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

    // ===================== WAITLIST METHODS =====================

    public function createWaitlistEntry(array $data): int
    {
        $stmt = $this->pdo()->prepare(
            "INSERT INTO reservation_waitlist (
                facility_id, requested_by, purpose, preferred_start_datetime, 
                preferred_end_datetime, status, notes
             ) VALUES (
                :facility_id, :requested_by, :purpose, :preferred_start, 
                :preferred_end, :status, :notes
             )"
        );
        $stmt->execute([
            'facility_id' => $data['facility_id'],
            'requested_by' => $data['requested_by'],
            'purpose' => $data['purpose'],
            'preferred_start' => $data['preferred_start_datetime'],
            'preferred_end' => $data['preferred_end_datetime'],
            'status' => $data['status'] ?? 'pending',
            'notes' => $data['notes'] ?? null,
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    public function getWaitlistForFacility(int $facilityId): array
    {
        $stmt = $this->pdo()->prepare(
            "SELECT w.*, u.full_name AS requester_name, f.name AS facility_name
             FROM reservation_waitlist w
             JOIN users u ON u.id = w.requested_by
             JOIN facilities f ON f.id = w.facility_id
             WHERE w.facility_id = :facility_id
             ORDER BY w.created_at ASC"
        );
        $stmt->execute(['facility_id' => $facilityId]);

        return $stmt->fetchAll();
    }

    public function getWaitlistForUser(int $userId): array
    {
        $stmt = $this->pdo()->prepare(
            "SELECT w.*, f.name AS facility_name
             FROM reservation_waitlist w
             JOIN facilities f ON f.id = w.facility_id
             WHERE w.requested_by = :user_id
             ORDER BY w.created_at DESC"
        );
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll();
    }

    public function updateWaitlistStatus(int $waitlistId, string $status, ?int $offeredReservationId = null): void
    {
        $sql = "UPDATE reservation_waitlist SET status = :status";
        $params = ['status' => $status, 'id' => $waitlistId];
        
        if ($offeredReservationId) {
            $sql .= ", offered_reservation_id = :offered_id";
            $params['offered_id'] = $offeredReservationId;
        }
        
        $sql .= " WHERE id = :id";
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);
    }

    // ===================== COST TRACKING METHODS =====================

    public function updateCostInfo(int $reservationId, array $costData): void
    {
        $stmt = $this->pdo()->prepare(
            "UPDATE reservations 
             SET cost_amount = :cost_amount, 
                 cost_currency = :cost_currency,
                 chargeback_department_id = :chargeback_dept,
                 cost_note = :cost_note
             WHERE id = :id"
        );
        $stmt->execute([
            'cost_amount' => $costData['cost_amount'] ?? null,
            'cost_currency' => $costData['cost_currency'] ?? 'PHP',
            'chargeback_dept' => $costData['chargeback_department_id'] ?? null,
            'cost_note' => $costData['cost_note'] ?? null,
            'id' => $reservationId,
        ]);
    }

    public function getCostSummaryByDepartment(string $startDate, string $endDate): array
    {
        $stmt = $this->pdo()->prepare(
            "SELECT d.name AS department_name, 
                    COUNT(r.id) AS reservation_count,
                    COALESCE(SUM(r.cost_amount), 0) AS total_cost
             FROM reservations r
             JOIN departments d ON d.id = r.chargeback_department_id
             WHERE r.status = 'approved'
               AND r.cost_amount IS NOT NULL
               AND r.start_datetime >= :start_date
               AND r.start_datetime <= :end_date
             GROUP BY d.id, d.name
             ORDER BY total_cost DESC"
        );
        $stmt->execute(['start_date' => $startDate, 'end_date' => $endDate]);

        return $stmt->fetchAll();
    }

    // ===================== NO-SHOW TRACKING METHODS =====================

    public function markNoShow(int $reservationId, int $reportedBy): void
    {
        $stmt = $this->pdo()->prepare(
            "UPDATE reservations 
             SET no_show = 1, 
                 no_show_reported_by = :reported_by, 
                 no_show_reported_at = CURRENT_TIMESTAMP 
             WHERE id = :id"
        );
        $stmt->execute(['reported_by' => $reportedBy, 'id' => $reservationId]);
    }

    public function getNoShowStats(int $userId): array
    {
        $stmt = $this->pdo()->prepare(
            "SELECT COUNT(*) AS total_reservations,
                    SUM(CASE WHEN no_show = 1 THEN 1 ELSE 0 END) AS no_show_count,
                    ROUND(SUM(CASE WHEN no_show = 1 THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 2) AS no_show_percentage
             FROM reservations
             WHERE requested_by = :user_id
               AND deleted_at IS NULL"
        );
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetch();
    }

    // ===================== CANCELLATION METHODS =====================

    public function markCancelled(int $reservationId, int $cancelledBy, string $reason): void
    {
        $stmt = $this->pdo()->prepare(
            "UPDATE reservations 
             SET status = 'cancelled',
                 cancellation_reason = :reason,
                 cancelled_by = :cancelled_by,
                 cancelled_at = CURRENT_TIMESTAMP
             WHERE id = :id"
        );
        $stmt->execute([
            'reason' => $reason,
            'cancelled_by' => $cancelledBy,
            'id' => $reservationId,
        ]);
    }

    // ===================== FEEDBACK METHODS =====================

    public function submitFeedback(int $reservationId, int $rating, ?string $comment): void
    {
        $stmt = $this->pdo()->prepare(
            "UPDATE reservations 
             SET feedback_rating = :rating,
                 feedback_comment = :comment,
                 feedback_submitted_at = CURRENT_TIMESTAMP
             WHERE id = :id"
        );
        $stmt->execute([
            'rating' => $rating,
            'comment' => $comment,
            'id' => $reservationId,
        ]);
    }

    public function getFeedbackSummary(int $facilityId): array
    {
        $stmt = $this->pdo()->prepare(
            "SELECT COUNT(*) AS total_feedback,
                    AVG(feedback_rating) AS average_rating,
                    MIN(feedback_rating) AS min_rating,
                    MAX(feedback_rating) AS max_rating
             FROM reservations
             WHERE facility_id = :facility_id
               AND feedback_rating IS NOT NULL
               AND deleted_at IS NULL"
        );
        $stmt->execute(['facility_id' => $facilityId]);

        return $stmt->fetch();
    }
}
