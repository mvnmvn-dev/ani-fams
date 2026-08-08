<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;

class DepartmentFilter
{
    /**
     * Whether the current user should be department-scoped.
     */
    public static function applies(): bool
    {
        $user = Auth::user();

        if (! $user || ! $user->department_id) {
            return false;
        }

        return ! $user->hasRole('admin', 'superadmin');
    }

    public static function departmentId(): ?int
    {
        $user = Auth::user();

        return $user?->department_id ? (int) $user->department_id : null;
    }

    /**
     * SQL fragment + bindings to restrict rows by a user.department_id column
     * (e.g. owner users join alias "u").
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    public static function sqlOnUserAlias(string $alias = 'u'): array
    {
        if (! self::applies()) {
            return ['', []];
        }

        return [
            " AND {$alias}.department_id = :dept_scope_id",
            ['dept_scope_id' => self::departmentId()],
        ];
    }

    /**
     * SQL fragment for tables that store department_id directly (e.g. employees).
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    public static function sqlOnColumn(string $column = 'department_id'): array
    {
        if (! self::applies()) {
            return ['', []];
        }

        return [
            " AND {$column} = :dept_scope_id",
            ['dept_scope_id' => self::departmentId()],
        ];
    }
}
