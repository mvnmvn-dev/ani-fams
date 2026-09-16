<?php

namespace App\Services;

use App\Repositories\UserRepository;
use App\Support\AuditLogger;
use App\Support\PasswordPolicy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;

class UserService
{
    public function __construct(private UserRepository $repo) {}

    public function list(): array
    {
        return $this->repo->all();
    }

    public function roles(): array
    {
        return $this->repo->roles();
    }

    /** @return array{success: bool, error?: string} */
    public function createUser(array $input): array
    {
        $fullName = trim($input['full_name'] ?? '');
        $email = trim($input['email'] ?? '');
        $password = $input['password'] ?? '';
        $roleId = (int) ($input['role_id'] ?? 0);
        $departmentId = ! empty($input['department_id']) ? (int) $input['department_id'] : null;

        if ($fullName === '' || $email === '' || $password === '' || ! $roleId) {
            return ['success' => false, 'error' => 'Please fill in all fields.'];
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'Please enter a valid email address.'];
        }

        $policy = PasswordPolicy::validate($password);
        if (! ($policy['valid'] ?? false)) {
            return ['success' => false, 'error' => $policy['error'] ?? 'Password does not meet policy requirements.'];
        }

        if ($this->repo->emailExists($email)) {
            return ['success' => false, 'error' => 'A user with that email already exists.'];
        }

        $otpEmail = trim($input['otp_email'] ?? '');
        if ($otpEmail !== '' && ! filter_var($otpEmail, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'Please enter a valid OTP Gmail address.'];
        }

        $userId = $this->repo->create([
            'full_name' => $fullName,
            'email' => $email,
            'otp_email' => $otpEmail !== '' ? $otpEmail : null,
            'password' => $password,
            'role_id' => $roleId,
            'department_id' => $departmentId,
        ]);

        AuditLogger::log('users', 'create', 'user', $userId, null, [
            'full_name' => $fullName,
            'email' => $email,
            'role_id' => $roleId,
        ]);

        return ['success' => true];
    }

    /**
     * Import users from CSV with columns: full_name,email,password,role_code,department_id(optional)
     *
     * @return array{success: bool, error?: string, imported?: int, skipped?: int, errors?: list<string>}
     */
    public function importCsv(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');
        if (! $handle) {
            return ['success' => false, 'error' => 'Unable to read uploaded CSV.'];
        }

        $header = fgetcsv($handle);
        if (! $header) {
            fclose($handle);

            return ['success' => false, 'error' => 'CSV is empty.'];
        }

        $header = array_map(static fn ($h) => strtolower(trim((string) $h)), $header);
        $required = ['full_name', 'email', 'password', 'role_code'];
        foreach ($required as $col) {
            if (! in_array($col, $header, true)) {
                fclose($handle);

                return ['success' => false, 'error' => "CSV must include column: {$col}"];
            }
        }

        $imported = 0;
        $skipped = 0;
        $errors = [];
        $line = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $line++;
            if (count(array_filter($row, static fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }
            $data = [];
            foreach ($header as $i => $key) {
                $data[$key] = trim((string) ($row[$i] ?? ''));
            }

            $roleId = $this->repo->findRoleIdByCode($data['role_code'] ?? '');
            if (! $roleId) {
                $skipped++;
                $errors[] = "Line {$line}: unknown role_code '{$data['role_code']}'";

                continue;
            }

            $result = $this->createUser([
                'full_name' => $data['full_name'] ?? '',
                'email' => $data['email'] ?? '',
                'password' => $data['password'] ?? '',
                'role_id' => $roleId,
                'department_id' => $data['department_id'] ?? null,
            ]);

            if ($result['success'] ?? false) {
                $imported++;
            } else {
                $skipped++;
                $errors[] = "Line {$line}: " . ($result['error'] ?? 'failed');
            }
        }

        fclose($handle);

        AuditLogger::log('users', 'import', 'user', Auth::id(), null, [
            'imported' => $imported,
            'skipped' => $skipped,
        ]);

        return [
            'success' => true,
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => array_slice($errors, 0, 20),
        ];
    }

    public function softDelete(int $id): array
    {
        if ((int) Auth::id() === $id) {
            return ['success' => false, 'error' => 'You cannot delete your own account.'];
        }
        $this->repo->softDelete($id);
        AuditLogger::log('users', 'soft_delete', 'user', $id);

        return ['success' => true];
    }

    public function toggleStatus(int $id, string $currentStatus): array
    {
        $newStatus = $currentStatus === 'active' ? 'suspended' : 'active';
        $this->repo->updateStatus($id, $newStatus);

        AuditLogger::log(
            'users',
            $newStatus === 'suspended' ? 'suspend' : 'reactivate',
            'user',
            $id,
            ['status' => $currentStatus],
            ['status' => $newStatus]
        );

        return ['success' => true, 'status' => $newStatus, 'id' => $id];
    }

    /**
     * Bulk reassign role for selected users.
     *
     * @param  list<int|string>  $userIds
     * @return array{success: bool, error?: string, updated?: int}
     */
    public function bulkReassignRole(array $userIds, int $roleId): array
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        if ($userIds === [] || ! $roleId) {
            return ['success' => false, 'error' => 'Select at least one user and a target role.'];
        }

        if (! $this->repo->roleExists($roleId)) {
            return ['success' => false, 'error' => 'Invalid role selected.'];
        }

        $selfId = (int) Auth::id();
        $userIds = array_values(array_filter($userIds, static fn ($id) => $id !== $selfId));
        if ($userIds === []) {
            return ['success' => false, 'error' => 'You cannot change your own role via bulk reassignment.'];
        }

        // Prevent demoting the last active admin
        $adminRoleId = $this->repo->findRoleIdByCode('admin');
        if ($adminRoleId && $roleId !== $adminRoleId) {
            $remainingAdmins = $this->repo->countActiveUsersWithRole($adminRoleId, $userIds);
            if ($remainingAdmins < 1) {
                return ['success' => false, 'error' => 'Cannot reassign the last active admin. Keep at least one admin account.'];
            }
        }

        $updated = $this->repo->bulkUpdateRole($userIds, $roleId);

        AuditLogger::log('users', 'bulk_role_reassign', 'user', $selfId, null, [
            'user_ids' => $userIds,
            'role_id' => $roleId,
            'updated' => $updated,
        ]);

        return ['success' => true, 'updated' => $updated];
    }

    /** @return array{success: bool, error?: string} */
    public function updateOtpEmail(int $id, ?string $otpEmail): array
    {
        $otpEmail = $otpEmail !== null ? trim($otpEmail) : null;

        if ($otpEmail === '') {
            $otpEmail = null;
        }

        if ($otpEmail !== null && ! filter_var($otpEmail, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'Please enter a valid Gmail address.'];
        }

        $this->repo->updateOtpEmail($id, $otpEmail);

        AuditLogger::log('users', 'update_otp_email', 'user', $id, null, [
            'otp_email' => $otpEmail,
        ]);

        return ['success' => true];
    }
}
