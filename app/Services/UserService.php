<?php

namespace App\Services;

use App\Repositories\UserRepository;

use App\Support\AuditLogger;
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
        $email    = trim($input['email'] ?? '');
        $password = $input['password'] ?? '';
        $roleId   = (int) ($input['role_id'] ?? 0);

        if ($fullName === '' || $email === '' || $password === '' || !$roleId) {
            return ['success' => false, 'error' => 'Please fill in all fields.'];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'Please enter a valid email address.'];
        }

        if (strlen($password) < 8) {
            return ['success' => false, 'error' => 'Password must be at least 8 characters.'];
        }

        if ($this->repo->emailExists($email)) {
            return ['success' => false, 'error' => 'A user with that email already exists.'];
        }

        $userId = $this->repo->create([
            'full_name' => $fullName,
            'email'     => $email,
            'password'  => $password,
            'role_id'   => $roleId,
        ]);

        AuditLogger::log('users', 'create', 'user', $userId, null, [
            'full_name' => $fullName,
            'email'     => $email,
            'role_id'   => $roleId,
        ]);

        return ['success' => true];
    }

    public function toggleStatus(int $id, string $currentStatus): void
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
    }
}
