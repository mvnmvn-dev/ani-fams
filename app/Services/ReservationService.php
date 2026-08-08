<?php

namespace App\Services;

use App\Repositories\ReservationRepository;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ReservationService
{
    public function __construct(private ReservationRepository $repo) {}

    public function listForCurrentUser(): array
    {
        if (Auth::user()?->canPermission('reservation.approve')) {
            return $this->repo->all();
        }

        return $this->repo->allForUser(Auth::id());
    }

    public function facilities(): array
    {
        return $this->repo->allFacilities();
    }

    public function facilitiesForManage(): array
    {
        return $this->repo->allFacilitiesIncludingMaintenance();
    }

    public function listEquipment(): array
    {
        return $this->repo->allEquipment();
    }

    public function utilizationStats(): array
    {
        $rows = $this->repo->utilizationAnalyticsSafe();
        foreach ($rows as &$row) {
            $row['hours_booked'] = round((float) ($row['hours_booked'] ?? 0), 1);
            $row['booking_count'] = (int) ($row['booking_count'] ?? 0);
        }

        return $rows;
    }

    public function pendingCountForCurrentUser(): int
    {
        return Auth::user()?->canPermission('reservation.approve')
            ? $this->repo->countPending()
            : $this->repo->countPendingForUser(Auth::id());
    }

    /**
     * @return array{success: bool, error?: string, id?: int, created?: int}
     */
    public function submitRequest(array $input): array
    {
        $facilityId = (int) ($input['facility_id'] ?? 0);
        $purpose = trim($input['purpose'] ?? '');
        $start = $input['start_datetime'] ?? '';
        $end = $input['end_datetime'] ?? '';
        $rule = $input['recurrence_rule'] ?? 'none';
        if (! in_array($rule, ['none', 'daily', 'weekly', 'monthly'], true)) {
            $rule = 'none';
        }
        $until = $input['recurrence_until'] ?? null;

        if (! $facilityId || $purpose === '' || ! $start || ! $end) {
            return ['success' => false, 'error' => 'Please fill in all fields.'];
        }

        if (strtotime($end) <= strtotime($start)) {
            return ['success' => false, 'error' => 'End time must be after the start time.'];
        }

        if (strtotime($start) < strtotime('-1 minute')) {
            return ['success' => false, 'error' => 'You cannot reserve a time slot in the past.'];
        }

        $occurrences = $this->buildOccurrences($start, $end, $rule, $until);
        if ($occurrences === []) {
            return ['success' => false, 'error' => 'No valid occurrences to create.'];
        }
        if (count($occurrences) > 52) {
            return ['success' => false, 'error' => 'Recurring series is limited to 52 occurrences.'];
        }

        foreach ($occurrences as [$occStart, $occEnd]) {
            if ($this->repo->hasConflict($facilityId, $occStart, $occEnd)) {
                return ['success' => false, 'error' => "That facility is already booked around {$occStart}."];
            }
        }

        $seriesId = count($occurrences) > 1 ? (string) Str::uuid() : null;
        $firstId = null;
        $equipmentIds = array_map('intval', (array) ($input['equipment_ids'] ?? []));
        $equipmentQty = (array) ($input['equipment_qty'] ?? []);

        foreach ($occurrences as [$occStart, $occEnd]) {
            $id = $this->repo->create([
                'facility_id' => $facilityId,
                'requested_by' => Auth::id(),
                'purpose' => $purpose,
                'start_datetime' => $occStart,
                'end_datetime' => $occEnd,
                'status' => 'pending',
                'series_id' => $seriesId,
                'recurrence_rule' => $rule,
                'recurrence_until' => $until ?: null,
                'is_maintenance' => 0,
            ]);
            $firstId ??= $id;

            foreach ($equipmentIds as $eid) {
                if ($eid > 0) {
                    $qty = (int) ($equipmentQty[$eid] ?? $equipmentQty[(string) $eid] ?? 1);
                    $this->repo->attachEquipment($id, $eid, $qty);
                }
            }
        }

        $this->notifyApprovers(
            "New reservation request",
            "A reservation for \"{$purpose}\" was submitted and needs approval.",
            '/reservations'
        );

        AuditLogger::log('reservations', 'create', 'reservation', $firstId, null, [
            'facility_id' => $facilityId,
            'purpose' => $purpose,
            'occurrences' => count($occurrences),
            'series_id' => $seriesId,
        ]);

        return ['success' => true, 'id' => $firstId, 'created' => count($occurrences)];
    }

    /**
     * @return array{success: bool, error?: string, id?: int}
     */
    public function scheduleMaintenanceBlock(array $input): array
    {
        if (! Auth::user()?->canPermission('reservation.manage') && ! Auth::user()?->canPermission('reservation.approve')) {
            return ['success' => false, 'error' => 'You do not have permission to schedule maintenance.'];
        }

        $facilityId = (int) ($input['facility_id'] ?? 0);
        $purpose = trim($input['purpose'] ?? 'Maintenance');
        $start = $input['start_datetime'] ?? '';
        $end = $input['end_datetime'] ?? '';

        if (! $facilityId || ! $start || ! $end) {
            return ['success' => false, 'error' => 'Facility, start, and end are required.'];
        }
        if (strtotime($end) <= strtotime($start)) {
            return ['success' => false, 'error' => 'End time must be after the start time.'];
        }
        if ($this->repo->hasConflict($facilityId, $start, $end)) {
            return ['success' => false, 'error' => 'That facility already has a booking overlapping this window.'];
        }

        $id = $this->repo->create([
            'facility_id' => $facilityId,
            'requested_by' => Auth::id(),
            'purpose' => $purpose !== '' ? $purpose : 'Maintenance',
            'start_datetime' => $start,
            'end_datetime' => $end,
            'status' => 'approved',
            'is_maintenance' => 1,
            'recurrence_rule' => 'none',
            'approved_by' => Auth::id(),
            'approved_at' => date('Y-m-d H:i:s'),
        ]);

        $now = time();
        if ($now >= strtotime($start) && $now <= strtotime($end)) {
            $this->repo->setFacilityStatus($facilityId, 'maintenance');
        }

        AuditLogger::log('reservations', 'maintenance_block', 'reservation', $id, null, [
            'facility_id' => $facilityId,
            'start_datetime' => $start,
            'end_datetime' => $end,
        ]);

        return ['success' => true, 'id' => $id];
    }

    /**
     * @return array{success: bool, error?: string}
     */
    public function decide(int $reservationId, string $decision): array
    {
        if (! Auth::user()?->canPermission('reservation.approve')) {
            return ['success' => false, 'error' => 'You do not have permission to approve reservations.'];
        }

        if (! in_array($decision, ['approved', 'rejected'], true)) {
            return ['success' => false, 'error' => 'Invalid decision.'];
        }

        $reservation = $this->repo->find($reservationId);
        if (! $reservation) {
            return ['success' => false, 'error' => 'Reservation not found.'];
        }

        if ($reservation['status'] !== 'pending') {
            return ['success' => false, 'error' => 'This reservation has already been decided.'];
        }

        $this->repo->updateStatus($reservationId, $decision, Auth::id());

        $notificationService = app(NotificationService::class);
        $resModel = \App\Models\Reservation::with('facility')->find($reservationId);
        if ($resModel) {
            $notificationService->send(
                $resModel->requested_by,
                'reservations',
                "Reservation {$decision}",
                "Your reservation request for {$resModel->facility->name} on {$resModel->start_datetime->format('Y-m-d H:i')} has been {$decision}.",
                '/reservations',
                true
            );
        }

        AuditLogger::log(
            'reservations',
            $decision === 'approved' ? 'approve' : 'reject',
            'reservation',
            $reservationId,
            ['status' => 'pending'],
            ['status' => $decision]
        );

        return ['success' => true];
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private function buildOccurrences(string $start, string $end, string $rule, ?string $until): array
    {
        $startTs = strtotime($start);
        $endTs = strtotime($end);
        $duration = $endTs - $startTs;
        $out = [[date('Y-m-d H:i:s', $startTs), date('Y-m-d H:i:s', $endTs)]];

        if ($rule === 'none' || ! $until) {
            return $out;
        }

        $untilTs = strtotime($until.' 23:59:59');
        $cursor = $startTs;
        $modifier = match ($rule) {
            'daily' => '+1 day',
            'weekly' => '+1 week',
            'monthly' => '+1 month',
            default => null,
        };
        if (! $modifier) {
            return $out;
        }

        while (count($out) < 52) {
            $cursor = strtotime($modifier, $cursor);
            if ($cursor === false || $cursor > $untilTs) {
                break;
            }
            $out[] = [
                date('Y-m-d H:i:s', $cursor),
                date('Y-m-d H:i:s', $cursor + $duration),
            ];
        }

        return $out;
    }

    private function notifyApprovers(string $title, string $body, string $link): void
    {
        $notificationService = app(NotificationService::class);
        foreach ($this->repo->approverUserIds() as $uid) {
            if ($uid === (int) Auth::id()) {
                continue;
            }
            $notificationService->send($uid, 'reservations', $title, $body, $link, true);
        }
    }
}
