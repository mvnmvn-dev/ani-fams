<?php

namespace App\Services;

use App\Repositories\VisitorRepository;
use App\Support\AuditLogger;
use Illuminate\Http\UploadedFile;

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
    public function preRegister(array $input, ?UploadedFile $photo = null): array
    {
        $visitorId = (int) ($input['visitor_id'] ?? 0);
        $fullName = trim($input['full_name'] ?? '');
        $hostId = (int) ($input['host_employee_id'] ?? 0);
        $purpose = trim($input['purpose'] ?? '');
        $scheduled = $input['scheduled_at'] ?? '';
        $notes = trim($input['notes'] ?? '');
        $areaIds = $input['areas'] ?? [];
        $idNumber = trim($input['id_number'] ?? '');
        $email = trim($input['email'] ?? '');
        $company = trim($input['company'] ?? '');
        $phone = trim($input['phone'] ?? '');
        $flagWatchlist = ! empty($input['is_watchlisted']);

        if (! $hostId || ! $scheduled) {
            return ['success' => false, 'error' => 'Host employee and scheduled time are required.'];
        }

        if (! $visitorId && $fullName === '') {
            return ['success' => false, 'error' => 'Please select an existing visitor or enter details for a new visitor.'];
        }

        $photoPath = null;
        if ($photo) {
            $photoPath = $this->storePhoto($photo);
            if ($photoPath === null) {
                return ['success' => false, 'error' => 'Failed to save visitor photo.'];
            }
        }

        $watchlistMatch = null;
        $denied = false;

        if (! $visitorId) {
            $watchlistMatch = $this->repo->findWatchlistedMatch($fullName, $idNumber !== '' ? $idNumber : null, $email !== '' ? $email : null);
            if ($watchlistMatch) {
                $flagWatchlist = true;
                $denied = true;
            }

            $visitorId = $this->repo->createVisitor([
                'full_name' => $fullName,
                'id_number' => $idNumber,
                'company' => $company,
                'email' => $email,
                'phone' => $phone,
                'photo_path' => $photoPath,
                'is_watchlisted' => $flagWatchlist,
            ]);
        } else {
            $visitor = $this->repo->findVisitor($visitorId);
            if (! $visitor) {
                return ['success' => false, 'error' => 'Selected visitor not found.'];
            }
            if ($photoPath) {
                $this->repo->updateVisitorPhoto($visitorId, $photoPath);
            }
            if (! empty($visitor['is_watchlisted'])) {
                $flagWatchlist = true;
                $denied = true;
                $watchlistMatch = $visitor;
            } else {
                $watchlistMatch = $this->repo->findWatchlistedMatch(
                    $visitor['full_name'] ?? null,
                    $visitor['id_number'] ?? null,
                    $visitor['email'] ?? null,
                    $visitorId
                );
                if ($watchlistMatch) {
                    $this->repo->toggleWatchlist($visitorId, 1);
                    $flagWatchlist = true;
                    $denied = true;
                }
            }
        }

        $visitStatus = $denied ? 'denied' : 'scheduled';
        if ($denied && $notes === '') {
            $notes = 'Auto-denied: matched security watchlist.';
        }

        $visitId = $this->repo->createVisit([
            'visitor_id' => $visitorId,
            'host_employee_id' => $hostId,
            'purpose' => $purpose,
            'scheduled_at' => $scheduled,
            'notes' => $notes,
            'status' => $visitStatus,
        ]);

        foreach ($areaIds as $areaId) {
            $this->repo->addVisitAreaAccess($visitId, (int) $areaId);
        }

        AuditLogger::log('visitors', 'create', 'visit', $visitId, null, [
            'visitor_id' => $visitorId,
            'host_employee_id' => $hostId,
            'purpose' => $purpose,
            'scheduled_at' => $scheduled,
            'status' => $visitStatus,
            'watchlist_match' => $watchlistMatch['id'] ?? null,
        ]);

        if ($denied) {
            return [
                'success' => false,
                'error' => 'Visitor matched the security watchlist. Visit was registered as denied and check-in is blocked.',
                'id' => $visitId,
            ];
        }

        return ['success' => true, 'id' => $visitId];
    }

    public function checkIn(int $visitId): array
    {
        $visit = $this->repo->findVisit($visitId);
        if (! $visit) {
            return ['success' => false, 'error' => 'Visit not found.'];
        }

        if (($visit['status'] ?? '') === 'denied') {
            return ['success' => false, 'error' => 'This visit was denied and cannot be checked in.'];
        }

        $visitor = $this->repo->findVisitor((int) $visit['visitor_id']);
        if ($visitor && ! empty($visitor['is_watchlisted'])) {
            return ['success' => false, 'error' => 'Check-in blocked: visitor is on the security watchlist.'];
        }

        $badgeCode = 'BADGE-' . strtoupper(bin2hex(random_bytes(3)));
        $checkedInAt = now()->format('Y-m-d H:i:s');
        $this->repo->updateVisitStatus($visitId, 'checked_in', $checkedInAt, null, $badgeCode);

        $notificationService = app(\App\Services\NotificationService::class);
        $visitModel = \App\Models\Visit::with(['host.user', 'visitor'])->find($visitId);
        if ($visitModel && $visitModel->host && $visitModel->host->user) {
            $notificationService->send(
                $visitModel->host->user,
                'visitors',
                "Visitor Arrived: {$visitModel->visitor->full_name}",
                "Your visitor {$visitModel->visitor->full_name} has checked in at the lobby.",
                '/visitors',
                true
            );
        }

        AuditLogger::log('visitors', 'check_in', 'visit', $visitId, ['status' => $visit['status']], [
            'status' => 'checked_in',
            'badge_code' => $badgeCode,
        ]);

        return [
            'success' => true,
            'id' => $visitId,
            'status' => 'checked_in',
            'check_in_time' => $checkedInAt,
            'badge_code' => $badgeCode,
            'visitor_id' => (int) $visit['visitor_id'],
        ];
    }

    public function checkOut(int $visitId): array
    {
        $visit = $this->repo->findVisit($visitId);
        if (! $visit) {
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

    public function badgeData(int $visitId): ?array
    {
        $visit = $this->repo->findVisit($visitId);
        if (! $visit) {
            return null;
        }
        $visitor = $this->repo->findVisitor((int) $visit['visitor_id']);
        if (! $visitor) {
            return null;
        }

        return array_merge($visit, [
            'visitor_name' => $visitor['full_name'] ?? '',
            'visitor_company' => $visitor['company'] ?? '',
            'visitor_photo' => $visitor['photo_path'] ?? null,
            'is_watchlisted' => $visitor['is_watchlisted'] ?? 0,
        ]);
    }

    private function storePhoto(UploadedFile $photo): ?string
    {
        $dir = public_path('uploads/visitors');
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $ext = strtolower($photo->getClientOriginalExtension() ?: 'jpg');
        $name = uniqid('visitor_', true) . '.' . $ext;
        if (! $photo->move($dir, $name)) {
            return null;
        }

        return '/uploads/visitors/' . $name;
    }

    public function onSiteCount(): int
    {
        return $this->repo->countOnSite();
    }
}
