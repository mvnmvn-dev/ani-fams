<?php

namespace App\Services;

use App\Repositories\VisitorRepository;

use App\Support\AuditLogger;
class VisitorService
{
    public function __construct(private VisitorRepository $repo) {}

    public function listVisits(): array
    {
        return $this->repo->allVisits();
    }

    public function visitors(): array
    {
        return $this->repo->allVisitors();
    }

    public function employees(): array
    {
        return $this->repo->allEmployees();
    }

    public function areas(): array
    {
        return $this->repo->allAreas();
    }

    /**
     * @return array{success: bool, error?: string, id?: int}
     */
    public function preRegister(array $input): array
    {
        $visitorId = (int) ($input['visitor_id'] ?? 0);
        $fullName  = trim($input['full_name'] ?? '');
        $hostId    = (int) ($input['host_employee_id'] ?? 0);
        $purpose   = trim($input['purpose'] ?? '');
        $scheduled = $input['scheduled_at'] ?? '';
        $notes     = trim($input['notes'] ?? '');
        $areaIds   = $input['areas'] ?? [];

        if (!$hostId || !$scheduled) {
            return ['success' => false, 'error' => 'Host employee and scheduled time are required.'];
        }

        if (!$visitorId && $fullName === '') {
            return ['success' => false, 'error' => 'Please select an existing visitor or enter details for a new visitor.'];
        }

        // If new visitor, insert them first
        if (!$visitorId) {
            $visitorId = $this->repo->createVisitor([
                'full_name'      => $fullName,
                'id_number'      => trim($input['id_number'] ?? ''),
                'company'        => trim($input['company'] ?? ''),
                'email'          => trim($input['email'] ?? ''),
                'phone'          => trim($input['phone'] ?? ''),
                'is_watchlisted' => !empty($input['is_watchlisted']),
            ]);
        } else {
            // Check if visitor is watchlisted
            $visitor = $this->repo->findVisitor($visitorId);
            if ($visitor && $visitor['is_watchlisted']) {
                // If they are watchlisted, we might still register them but flag it
                // Handled by UI or status check
            }
        }

        $visitId = $this->repo->createVisit([
            'visitor_id'       => $visitorId,
            'host_employee_id' => $hostId,
            'purpose'          => $purpose,
            'scheduled_at'     => $scheduled,
            'notes'            => $notes
        ]);

        // Add access areas
        foreach ($areaIds as $areaId) {
            $this->repo->addVisitAreaAccess($visitId, (int) $areaId);
        }

        AuditLogger::log('visitors', 'create', 'visit', $visitId, null, [
            'visitor_id'       => $visitorId,
            'host_employee_id' => $hostId,
            'purpose'          => $purpose,
            'scheduled_at'     => $scheduled,
        ]);

        return ['success' => true, 'id' => $visitId];
    }

    public function checkIn(int $visitId): array
    {
        $visit = $this->repo->findVisit($visitId);
        if (!$visit) {
            return ['success' => false, 'error' => 'Visit not found.'];
        }

        $visitor = $this->repo->findVisitor($visit['visitor_id']);
        if ($visitor && $visitor['is_watchlisted']) {
            // Log warning or block. Let's allow check-in but set status or show warning
        }

        $checkedInAt = now()->format('Y-m-d H:i:s');
        $this->repo->updateVisitStatus($visitId, 'checked_in', $checkedInAt, null);
        AuditLogger::log('visitors', 'check_in', 'visit', $visitId, ['status' => $visit['status']], ['status' => 'checked_in']);
        return [
            'success' => true,
            'id' => $visitId,
            'status' => 'checked_in',
            'check_in_time' => $checkedInAt,
            'visitor_id' => (int) $visit['visitor_id'],
        ];
    }

    public function checkOut(int $visitId): array
    {
        $visit = $this->repo->findVisit($visitId);
        if (!$visit) {
            return ['success' => false, 'error' => 'Visit not found.'];
        }

        $checkedOutAt = now()->format('Y-m-d H:i:s');
        $this->repo->updateVisitStatus($visitId, 'checked_out', null, $checkedOutAt);
        AuditLogger::log('visitors', 'check_out', 'visit', $visitId, ['status' => $visit['status']], ['status' => 'checked_out']);
        return [
            'success' => true,
            'id' => $visitId,
            'status' => 'checked_out',
            'check_out_time' => $checkedOutAt,
            'visitor_id' => (int) $visit['visitor_id'],
        ];
    }

    public function toggleWatchlist(int $visitorId, bool $watchlist): array
    {
        $this->repo->toggleWatchlist($visitorId, $watchlist ? 1 : 0);
        AuditLogger::log(
            'visitors',
            'watchlist',
            'visitor',
            $visitorId,
            null,
            ['is_watchlisted' => $watchlist]
        );
        return [
            'success' => true,
            'visitor_id' => $visitorId,
            'is_watchlisted' => $watchlist,
        ];
    }

    public function onSiteCount(): int
    {
        return $this->repo->countOnSite();
    }
}
