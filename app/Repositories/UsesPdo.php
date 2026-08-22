<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use PDO;

trait UsesPdo
{
    protected function pdo(): PDO
    {
        $pdo = DB::connection()->getPdo();
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    }

    /** Cross-database string aggregation for subqueries (MySQL / PostgreSQL / SQLite). */
    protected function groupConcatSql(string $column, string $separator = ', '): string
    {
        $driver = $this->pdo()->getAttribute(PDO::ATTR_DRIVER_NAME);
        $escapedSep = str_replace("'", "''", $separator);

        if ($driver === 'pgsql') {
            return "string_agg({$column}, '{$escapedSep}')";
        }

        if ($driver === 'sqlite') {
            return "GROUP_CONCAT({$column}, '{$escapedSep}')";
        }

        return "GROUP_CONCAT({$column} SEPARATOR '{$escapedSep}')";
    }

    protected function isPgsql(): bool
    {
        return $this->pdo()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql';
    }

    /** SQL literal for TRUE/FALSE (PostgreSQL) or 1/0 (MySQL / SQLite). */
    protected function boolLiteral(bool $value): string
    {
        if ($this->isPgsql()) {
            return $value ? 'TRUE' : 'FALSE';
        }

        return $value ? '1' : '0';
    }

    /** Bound value for boolean columns (PostgreSQL rejects PHP false as ""). */
    protected function boolParam(bool $value): bool|int|string
    {
        if ($this->isPgsql()) {
            return $value ? 'true' : 'false';
        }

        return $value ? 1 : 0;
    }

    /** Normalize form/API empty strings to NULL for nullable columns. */
    protected function nullIfEmpty(mixed $value): mixed
    {
        return ($value === '' || $value === null) ? null : $value;
    }

    /** Known boolean column names used in dynamic UPDATE/INSERT binds. */
    private const BOOLEAN_COLUMNS = [
        'is_active', 'is_default', 'is_completed', 'is_maintenance', 'is_watchlisted',
        'auto_renew', 'no_show', 'ocr_processed', 'certificate_generated',
        'nda_signed', 'nda_required', 'is_appealed', 'conflict_flagged', 'conflict_verified',
        'two_factor_enabled', 'is_read',
    ];

    /** Normalize binds for PostgreSQL (bool empty-string bug, nullable empties). */
    protected function normalizeBindings(array $bindings): array
    {
        $normalized = [];

        foreach ($bindings as $key => $value) {
            if (is_bool($value) || in_array($key, self::BOOLEAN_COLUMNS, true)) {
                $normalized[$key] = $this->boolParam((bool) $value);
                continue;
            }

            $normalized[$key] = $this->nullIfEmpty($value);
        }

        return $normalized;
    }

    protected function isTrueCondition(string $column): string
    {
        return "{$column} = {$this->boolLiteral(true)}";
    }

    protected function isFalseCondition(string $column): string
    {
        return "{$column} = {$this->boolLiteral(false)}";
    }

    protected function coalesceFalseCondition(string $column): string
    {
        $false = $this->boolLiteral(false);

        return "COALESCE({$column}, {$false}) = {$false}";
    }

    protected function caseWhenTrue(string $column, string $then, string $else = '0'): string
    {
        if ($this->isPgsql()) {
            return "CASE WHEN {$column} IS TRUE THEN {$then} ELSE {$else} END";
        }

        return "CASE WHEN {$column} = 1 THEN {$then} ELSE {$else} END";
    }

    protected function caseWhenParamTrue(string $param, string $then, string $else = 'NULL'): string
    {
        if ($this->isPgsql()) {
            return "CASE WHEN ({$param})::boolean IS TRUE THEN {$then} ELSE {$else} END";
        }

        return "CASE WHEN {$param} = 1 THEN {$then} ELSE {$else} END";
    }

    /** PostgreSQL requires a sequence name; MySQL/SQLite use default lastInsertId(). */
    protected function insertId(string $table): int
    {
        $pdo = $this->pdo();

        if ($this->isPgsql()) {
            return (int) $pdo->lastInsertId($table.'_id_seq');
        }

        return (int) $pdo->lastInsertId();
    }
}