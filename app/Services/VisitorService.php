<?php

namespace App\Services;

use App\Repositories\VisitorRepository;
use App\Support\AuditLogger;
use App\Support\AutomationLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

        $visitStatus = $denied ? 'denied' : 'pending_approval';
        $registrationType = ! empty($input['walk_in']) ? 'walk_in' : 'pre_register';
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

        try {
            $hours = max(1, (int) ($input['expected_duration_hours'] ?? 8));
            $this->repo->updateVisitDefenseFields($visitId, [
                'registration_type' => $registrationType,
                'expected_duration_hours' => $hours,
            ]);
        } catch (\Throwable) {
        }

        foreach ($areaIds as $areaId) {
            $this->repo->addVisitAreaAccess($visitId, (int) $areaId);
        }

        if ($denied || $flagWatchlist) {
            $this->notifyWatchlistAdmins($fullName !== '' ? $fullName : ($visitor['full_name'] ?? 'Visitor'), $visitId);
        }

        if (! $denied) {
            $this->notifyHostApproval($hostId, $visitId, $fullName !== '' ? $fullName : 'Visitor');
        }

        AuditLogger::log('visitors', 'create', 'visit', $visitId, null, [
            'visitor_id' => $visitorId,
            'host_employee_id' => $hostId,
            'purpose' => $purpose,
            'scheduled_at' => $scheduled,
            'status' => $visitStatus,
            'registration_type' => $registrationType,
            'watchlist_match' => $watchlistMatch['id'] ?? null,
        ]);
        AutomationLogger::log('visitors', 'pre_register', 'visit', $visitId, ['status' => $visitStatus]);

        if ($denied) {
            return [
                'success' => false,
                'error' => 'Visitor matched the security watchlist. Visit was registered as denied and check-in is blocked.',
                'id' => $visitId,
            ];
        }

        return ['success' => true, 'id' => $visitId, 'status' => $visitStatus];
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

        $status = (string) ($visit['status'] ?? '');
        if (! in_array($status, ['approved', 'scheduled', 'pending_approval'], true)) {
            return ['success' => false, 'error' => 'Visit is not eligible for check-in.'];
        }

        // Host approval required unless staff overrides walk-in / already approved.
        if ($status === 'pending_approval' && ! Auth::user()?->canPermission('visitor.manage') && ! Auth::user()?->canPermission('visitor.checkin')) {
            return ['success' => false, 'error' => 'Host approval is required before check-in.'];
        }

        $visitor = $this->repo->findVisitor((int) $visit['visitor_id']);
        if ($visitor && ! empty($visitor['is_watchlisted'])) {
            return ['success' => false, 'error' => 'Check-in blocked: visitor is on the security watchlist.'];
        }

        $badgeCode = 'BADGE-' . strtoupper(bin2hex(random_bytes(3)));
        $checkedInAt = now()->format('Y-m-d H:i:s');
        $hours = max(1, (int) ($visit['expected_duration_hours'] ?? 8));
        $expiresAt = now()->addHours($hours)->format('Y-m-d H:i:s');

        $this->repo->updateVisitStatus($visitId, 'checked_in', $checkedInAt, null, $badgeCode);
        try {
            $this->repo->updateVisitDefenseFields($visitId, [
                'badge_expires_at' => $expiresAt,
            ]);
        } catch (\Throwable) {
        }

        $this->issueVisitorBadge($visitId, $visitor ?? [], $visit, $badgeCode, $checkedInAt, $expiresAt);

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
            'badge_expires_at' => $expiresAt,
        ]);
        AutomationLogger::log('visitors', 'check_in', 'visit', $visitId, ['badge_code' => $badgeCode]);

        return [
            'success' => true,
            'id' => $visitId,
            'status' => 'checked_in',
            'check_in_time' => $checkedInAt,
            'badge_code' => $badgeCode,
            'badge_expires_at' => $expiresAt,
            'visitor_id' => (int) $visit['visitor_id'],
        ];
    }

    /**
     * Host approval before visit (Registered → Approved).
     * @return array{success: bool, error?: string}
     */
    public function approveVisit(int $visitId): array
    {
        $visit = $this->repo->findVisit($visitId);
        if (! $visit) {
            return ['success' => false, 'error' => 'Visit not found.'];
        }

        if (! in_array($visit['status'] ?? '', ['pending_approval', 'scheduled'], true)) {
            return ['success' => false, 'error' => 'Visit cannot be approved in its current status.'];
        }

        $canManage = Auth::user()?->canPermission('visitor.manage') || Auth::user()?->canPermission('visitor.checkin');
        // Host employee linked to current user may approve.
        $isHost = false;
        try {
            $emp = DB::table('employees')->where('user_id', Auth::id())->value('id');
            $isHost = $emp && (int) $emp === (int) ($visit['host_employee_id'] ?? 0);
        } catch (\Throwable) {
        }

        if (! $canManage && ! $isHost) {
            return ['success' => false, 'error' => 'Only the host or visitor administrators can approve this visit.'];
        }

        $this->repo->updateVisitStatus($visitId, 'approved', null, null);
        try {
            $this->repo->updateVisitDefenseFields($visitId, [
                'host_approved_at' => now()->format('Y-m-d H:i:s'),
                'host_approved_by' => Auth::id(),
            ]);
        } catch (\Throwable) {
        }

        AuditLogger::log('visitors', 'host_approve', 'visit', $visitId, ['status' => $visit['status']], ['status' => 'approved']);
        AutomationLogger::log('visitors', 'host_approve', 'visit', $visitId);

        return ['success' => true, 'status' => 'approved'];
    }

    public function dashboardWidgets(): array
    {
        $visits = $this->repo->allVisits();
        $today = date('Y-m-d');
        $inside = 0;
        $todayCount = 0;
        $pending = 0;
        $expiredPasses = 0;
        $overstaying = 0;

        foreach ($visits as $v) {
            $status = $v['status'] ?? '';
            if ($status === 'checked_in') {
                $inside++;
                $expires = $v['badge_expires_at'] ?? null;
                $hours = (int) ($v['expected_duration_hours'] ?? 8);
                $checkIn = $v['check_in_time'] ?? null;
                if ($expires && strtotime($expires) < time()) {
                    $overstaying++;
                } elseif ($checkIn && strtotime($checkIn) < strtotime("-{$hours} hours")) {
                    $overstaying++;
                }
            }
            if (str_starts_with((string) ($v['scheduled_at'] ?? $v['check_in_time'] ?? ''), $today)) {
                $todayCount++;
            }
            if (in_array($status, ['pending_approval', 'scheduled'], true)) {
                $pending++;
            }
            if (! empty($v['badge_expires_at']) && strtotime($v['badge_expires_at']) < time() && $status === 'checked_in') {
                $expiredPasses++;
            }
        }

        return [
            'visitors_inside' => $inside,
            'todays_visitors' => $todayCount,
            'pending_approvals' => $pending,
            'expired_passes' => $expiredPasses,
            'overstaying' => $overstaying,
        ];
    }

    public function peakVisitorHours(): array
    {
        return $this->repo->peakVisitorHours();
    }

    private function issueVisitorBadge(int $visitId, array $visitor, array $visit, string $badgeCode, string $issuedAt, string $expiresAt): void
    {
        if (! Schema::hasTable('visitor_badges')) {
            return;
        }

        try {
            DB::table('visitor_badges')->where('visit_id', $visitId)->update(['status' => 'revoked']);
            DB::table('visitor_badges')->insert([
                'visit_id' => $visitId,
                'visitor_id' => (int) ($visit['visitor_id'] ?? $visitor['id'] ?? 0),
                'badge_code' => $badgeCode,
                'qr_payload' => $badgeCode,
                'visitor_name' => $visitor['full_name'] ?? 'Visitor',
                'company' => $visitor['company'] ?? null,
                'host_name' => $visit['host_name'] ?? null,
                'photo_path' => $visitor['photo_path'] ?? null,
                'issued_at' => $issuedAt,
                'expires_at' => $expiresAt,
                'status' => 'active',
                'created_at' => now()->format('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
        }
    }

    private function notifyWatchlistAdmins(string $visitorName, int $visitId): void
    {
        try {
            $notificationService = app(NotificationService::class);
            $adminIds = DB::table('users')
                ->join('role_permissions', 'role_permissions.role_id', '=', 'users.role_id')
                ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
                ->where('permissions.code', 'visitor.manage')
                ->where('users.status', 'active')
                ->whereNull('users.deleted_at')
                ->distinct()
                ->pluck('users.id');
            foreach ($adminIds as $uid) {
                $notificationService->send(
                    (int) $uid,
                    'visitors',
                    'Watchlist alert',
                    "Visitor \"{$visitorName}\" matched the security watchlist (visit #{$visitId}).",
                    '/visitors',
                    true
                );
            }
            AutomationLogger::log('visitors', 'watchlist_alert', 'visit', $visitId);
        } catch (\Throwable) {
        }
    }

    private function notifyHostApproval(int $hostEmployeeId, int $visitId, string $visitorName): void
    {
        try {
            $userId = DB::table('employees')->where('id', $hostEmployeeId)->value('user_id');
            if (! $userId) {
                return;
            }
            app(NotificationService::class)->send(
                (int) $userId,
                'visitors',
                'Visitor approval needed',
                "Please approve the visit for {$visitorName} (visit #{$visitId}).",
                '/visitors',
                true
            );
        } catch (\Throwable) {
        }
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
            'host_name' => $visit['host_name'] ?? null,
            'badge_expires_at' => $visit['badge_expires_at'] ?? null,
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

    // ===================== WATCHLIST ENHANCEMENTS =====================

    /**
     * @return array{success: bool, error?: string}
     */
    public function updateWatchlistWithDetails(int $visitorId, bool $watchlist, ?string $reason, ?string $expiryDate): array
    {
        if (! Auth::user()?->canPermission('visitor.manage')) {
            return ['success' => false, 'error' => 'You do not have permission to update watchlist details.'];
        }

        $this->repo->updateWatchlistWithDetails($visitorId, $watchlist, $reason, $expiryDate, Auth::id());

        AuditLogger::log('visitors', 'watchlist_update', 'visitor', $visitorId, null, [
            'is_watchlisted' => $watchlist,
            'watchlist_reason' => $reason,
            'watchlist_expiry_date' => $expiryDate,
        ]);

        return ['success' => true];
    }

    public function getExpiredWatchlistEntries(): array
    {
        return $this->repo->getExpiredWatchlistEntries();
    }

    public function autoRemoveExpiredWatchlist(): int
    {
        $count = $this->repo->autoRemoveExpiredWatchlist();
        
        if ($count > 0) {
            AuditLogger::log('visitors', 'watchlist_auto_expire', 'visitor', null, null, [
                'expired_count' => $count,
            ]);
        }

        return $count;
    }

    // ===================== VEHICLE LOGGING =====================

    /**
     * @return array{success: bool, error?: string, id?: int}
     */
    public function logVehicle(array $input): array
    {
        $visitorId = (int) ($input['visitor_id'] ?? 0);
        $plateNumber = trim($input['plate_number'] ?? '');

        if (! $visitorId || $plateNumber === '') {
            return ['success' => false, 'error' => 'Visitor and plate number are required.'];
        }

        $vehicleId = $this->repo->createVehicle([
            'visitor_id' => $visitorId,
            'visit_id' => $input['visit_id'] ?? null,
            'plate_number' => $plateNumber,
            'vehicle_make' => $input['vehicle_make'] ?? null,
            'vehicle_model' => $input['vehicle_model'] ?? null,
            'vehicle_color' => $input['vehicle_color'] ?? null,
            'notes' => $input['notes'] ?? null,
        ]);

        AuditLogger::log('visitors', 'vehicle_log', 'visitor_vehicle', $vehicleId, null, [
            'visitor_id' => $visitorId,
            'plate_number' => $plateNumber,
        ]);

        return ['success' => true, 'id' => $vehicleId];
    }

    public function getVehiclesForVisitor(int $visitorId): array
    {
        return $this->repo->getVehiclesForVisitor($visitorId);
    }

    public function getVehiclesForVisit(int $visitId): array
    {
        return $this->repo->getVehiclesForVisit($visitId);
    }

    // ===================== NDA SIGNATURE =====================

    /**
     * @return array{success: bool, error?: string}
     */
    public function setNdaRequired(int $visitId, bool $required): array
    {
        if (! Auth::user()?->canPermission('visitor.manage')) {
            return ['success' => false, 'error' => 'You do not have permission to require NDA.'];
        }

        $this->repo->setNdaRequired($visitId, $required);

        AuditLogger::log('visitors', 'nda_require', 'visit', $visitId, null, [
            'nda_required' => $required,
        ]);

        return ['success' => true];
    }

    /**
     * @return array{success: bool, error?: string}
     */
    public function signNda(int $visitId, string $signatureData, string $ipAddress): array
    {
        $visit = $this->repo->findVisit($visitId);
        if (! $visit) {
            return ['success' => false, 'error' => 'Visit not found.'];
        }

        if (! ($visit['nda_required'] ?? false)) {
            return ['success' => false, 'error' => 'NDA is not required for this visit.'];
        }

        if (($visit['nda_signed'] ?? false)) {
            return ['success' => false, 'error' => 'NDA has already been signed.'];
        }

        // Store signature
        $signaturePath = $this->storeSignature($signatureData);
        if (! $signaturePath) {
            return ['success' => false, 'error' => 'Failed to store signature.'];
        }

        $this->repo->updateNdaSignature($visitId, $signaturePath, $ipAddress);

        AuditLogger::log('visitors', 'nda_sign', 'visit', $visitId, ['nda_signed' => false], [
            'nda_signed' => true,
            'signature_path' => $signaturePath,
        ]);

        return ['success' => true];
    }

    private function storeSignature(string $signatureData): ?string
    {
        $dir = public_path('uploads/signatures');
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        
        // Remove data URL prefix if present
        if (strpos($signatureData, 'data:') === 0) {
            $signatureData = substr($signatureData, strpos($signatureData, ',') + 1);
        }
        
        $signatureData = base64_decode($signatureData);
        if ($signatureData === false) {
            return null;
        }
        
        $name = uniqid('signature_', true) . '.png';
        $path = $dir . '/' . $name;
        
        if (file_put_contents($path, $signatureData) === false) {
            return null;
        }

        return '/uploads/signatures/' . $name;
    }

    // ===================== EMERGENCY ROSTER =====================

    public function getEmergencyRoster(): array
    {
        if (! Auth::user()?->canPermission('visitor.manage')) {
            return [];
        }

        return $this->repo->getEmergencyRoster();
    }
}
