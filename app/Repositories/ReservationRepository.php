<?php

namespace App\Repositories;

use PDO;

class ReservationRepository
{
    use UsesPdo;

    public function allFacilities(): array
    {
        $stmt = $this->pdo()->query(
            "SELECT id, name, type, location, capacity FROM facilities WHERE status = 'available' ORDER BY name"
        );
        return $stmt->fetchAll();
    }

    /** All reservations, joined with facility + requester name (used by staff/admin). */
    public function all(): array
    {
        $sql = "SELECT r.*, f.name AS facility_name, u.full_name AS requester_name
                FROM reservations r
                JOIN facilities f ON f.id = r.facility_id
                JOIN users u ON u.id = r.requested_by
                ORDER BY r.start_datetime DESC";
        return $this->pdo()->query($sql)->fetchAll();
    }

    /** Only reservations belonging to one user (used by employees — self-service view). */
    public function allForUser(int $userId): array
    {
        $stmt = $this->pdo()->prepare(
            "SELECT r.*, f.name AS facility_name, u.full_name AS requester_name
             FROM reservations r
             JOIN facilities f ON f.id = r.facility_id
             JOIN users u ON u.id = r.requested_by
             WHERE r.requested_by = :uid
             ORDER BY r.start_datetime DESC"
        );
        $stmt->execute(['uid' => $userId]);
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM reservations WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** True if the facility already has an overlapping approved/pending reservation. */
    public function hasConflict(int $facilityId, string $start, string $end): bool
    {
        $stmt = $this->pdo()->prepare(
            "SELECT COUNT(*) FROM reservations
             WHERE facility_id = :facility_id
               AND status IN ('pending','approved')
               AND start_datetime < :end
               AND end_datetime > :start"
        );
        $stmt->execute(['facility_id' => $facilityId, 'start' => $start, 'end' => $end]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo()->prepare(
            "INSERT INTO reservations (facility_id, requested_by, purpose, start_datetime, end_datetime, status)
             VALUES (:facility_id, :requested_by, :purpose, :start, :end, 'pending')"
        );
        $stmt->execute([
            'facility_id'  => $data['facility_id'],
            'requested_by' => $data['requested_by'],
            'purpose'      => $data['purpose'],
            'start'        => $data['start_datetime'],
            'end'          => $data['end_datetime'],
        ]);
        return (int) $this->pdo()->lastInsertId();
    }

    public function updateStatus(int $id, string $status, int $approverId): void
    {
        $stmt = $this->pdo()->prepare(
            "UPDATE reservations SET status = :status, approved_by = :approver, approved_at = NOW() WHERE id = :id"
        );
        $stmt->execute(['status' => $status, 'approver' => $approverId, 'id' => $id]);
    }

    public function countPending(): int
    {
        return (int) $this->pdo()
            ->query("SELECT COUNT(*) FROM reservations WHERE status = 'pending'")
            ->fetchColumn();
    }

    public function countPendingForUser(int $userId): int
    {
        $stmt = $this->pdo()->prepare(
            "SELECT COUNT(*) FROM reservations WHERE status = 'pending' AND requested_by = :uid"
        );
        $stmt->execute(['uid' => $userId]);
        return (int) $stmt->fetchColumn();
    }
}
