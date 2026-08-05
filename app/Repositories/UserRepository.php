<?php

namespace App\Repositories;

use PDO;

class UserRepository
{
    use UsesPdo;

    public function all(): array
    {
        $sql = "SELECT u.id, u.full_name, u.email, u.status, u.created_at,
                       r.code AS role_code, r.name AS role_name,
                       d.name AS department_name
                FROM users u
                JOIN roles r ON r.id = u.role_id
                LEFT JOIN departments d ON d.id = u.department_id
                ORDER BY u.created_at DESC";
        return $this->pdo()->query($sql)->fetchAll();
    }

    public function roles(): array
    {
        return $this->pdo()
            ->query('SELECT id, code, name FROM roles ORDER BY id')
            ->fetchAll();
    }

    public function emailExists(string $email): bool
    {
        $stmt = $this->pdo()->prepare('SELECT COUNT(*) FROM users WHERE email = :email');
        $stmt->execute(['email' => $email]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo()->prepare(
            "INSERT INTO users (full_name, email, password_hash, role_id, status)
             VALUES (:full_name, :email, :password_hash, :role_id, 'active')"
        );
        $stmt->execute([
            'full_name'     => $data['full_name'],
            'email'         => $data['email'],
            'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
            'role_id'       => $data['role_id'],
        ]);
        return (int) $this->pdo()->lastInsertId();
    }

    public function updateStatus(int $id, string $status): void
    {
        $stmt = $this->pdo()->prepare('UPDATE users SET status = :status WHERE id = :id');
        $stmt->execute(['status' => $status, 'id' => $id]);
    }

    public function emailExistsForOther(string $email, int $excludeUserId): bool
    {
        $stmt = $this->pdo()->prepare(
            'SELECT COUNT(*) FROM users WHERE email = :email AND id != :id'
        );
        $stmt->execute(['email' => $email, 'id' => $excludeUserId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function passwordHash(int $id): ?string
    {
        $stmt = $this->pdo()->prepare('SELECT password_hash FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $hash = $stmt->fetchColumn();
        return $hash === false ? null : (string) $hash;
    }

    public function updateProfile(int $id, string $fullName, string $email, ?string $photoPath = null): void
    {
        $stmt = $this->pdo()->prepare(
            'UPDATE users SET full_name = :full_name, email = :email, photo_path = :photo_path WHERE id = :id'
        );
        $stmt->execute([
            'full_name'  => $fullName,
            'email'      => $email,
            'photo_path' => $photoPath,
            'id'         => $id,
        ]);
    }

    public function updatePassword(int $id, string $password): void
    {
        $stmt = $this->pdo()->prepare(
            'UPDATE users SET password_hash = :password_hash WHERE id = :id'
        );
        $stmt->execute([
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'id'            => $id,
        ]);
    }
}
