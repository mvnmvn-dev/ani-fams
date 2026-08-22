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

    /** Bound value for boolean columns (PostgreSQL expects native bool). */
    protected function boolParam(bool $value): bool|int
    {
        return $this->isPgsql() ? $value : ($value ? 1 : 0);
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
            return "CASE WHEN {$param} THEN {$then} ELSE {$else} END";
        }

        return "CASE WHEN {$param} = 1 THEN {$then} ELSE {$else} END";
    }
}