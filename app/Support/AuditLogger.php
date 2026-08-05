<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Throwable;

class AuditLogger
{
    public static function log(
        string $module,
        string $action,
        string $entityType,
        string|int $entityId,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?int $userId = null
    ): void {
        try {
            AuditLog::create([
                'user_id' => $userId ?? Auth::id(),
                'module' => $module,
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => (string) $entityId,
                'old_values' => self::redact($oldValues),
                'new_values' => self::redact($newValues),
                'ip_address' => substr((string) (request()->ip() ?? ''), 0, 45) ?: null,
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    private static function redact(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        foreach (['password', 'password_hash', 'tmp_password', 'token'] as $key) {
            if (array_key_exists($key, $values)) {
                $values[$key] = '[redacted]';
            }
        }

        return $values;
    }
}
