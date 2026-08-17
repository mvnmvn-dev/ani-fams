<?php

/**
 * Best-effort converter:
 * - Takes `database/schema.sql` (MySQL/MariaDB syntax)
 * - Produces `database/schema.postgres.sql` (PostgreSQL-compatible syntax)
 *
 * Notes:
 * - This is intended for "clean install" + importing into a freshly created Postgres DB.
 * - It intentionally removes MySQL-only constructs (INLINE INDEX, ON DUPLICATE KEY, ON UPDATE CURRENT_TIMESTAMP, etc.).
 */

$inputPath = __DIR__ . DIRECTORY_SEPARATOR . 'schema.sql';
$outputPath = __DIR__ . DIRECTORY_SEPARATOR . 'schema.postgres.sql';

if (! file_exists($inputPath)) {
    fwrite(STDERR, "Input not found: {$inputPath}\n");
    exit(1);
}

$sql = file_get_contents($inputPath);
if ($sql === false) {
    fwrite(STDERR, "Failed reading: {$inputPath}\n");
    exit(1);
}

// Remove CREATE DATABASE/USE wrappers (Postgres will import into a DB already).
$sql = preg_replace('/^\s*CREATE\s+DATABASE\s+IF\s+NOT\s+EXISTS\s+\w+\s*;\s*$/mi', '', $sql);
$sql = preg_replace('/^\s*USE\s+\w+\s*;\s*$/mi', '', $sql);

// Drop MySQL upsert clause; schema re-import is expected to be "clean".
$sql = preg_replace('/\s+ON\s+DUPLICATE\s+KEY\s+UPDATE[\s\S]*?;/i', ';', $sql);

// Remove MySQL inline index lines (we don't recreate indexes here).
$sql = preg_replace('/^\s*INDEX\s+.*$/mi', '', $sql);

// Remove trailing comma before closing parens after index removal.
$sql = preg_replace('/,\s*\n(\s*\))/m', "\n$1", $sql);

// Remove MySQL ON UPDATE CURRENT_TIMESTAMP (Postgres needs triggers / app-side updates).
$sql = preg_replace('/\s+ON\s+UPDATE\s+CURRENT_TIMESTAMP/i', '', $sql);

// Strip MySQL backticks.
$sql = str_replace('`', '', $sql);

// Quote PostgreSQL reserved column name used by Laravel cache/session tables.
$sql = preg_replace(
    '/(\s+)key(\s+VARCHAR\(255\)\s+NOT\s+NULL\s+PRIMARY\s+KEY)/i',
    '$1"key"$2',
    $sql
);

// Replace MySQL enums with TEXT.
$sql = preg_replace('/\bENUM\s*\([^)]+\)/i', 'TEXT', $sql);

// JSON -> JSONB.
$sql = preg_replace('/\bJSON\b/i', 'JSONB', $sql);

// Text blobs.
$sql = preg_replace('/\b(LONGTEXT|MEDIUMTEXT)\b/i', 'TEXT', $sql);

// Datetimes.
$sql = preg_replace('/\bDATETIME\b/i', 'TIMESTAMP', $sql);

// Auto increment -> serial/identity.
$sql = preg_replace('/\bINT\s+UNSIGNED\s+AUTO_INCREMENT\s+PRIMARY\s+KEY\b/i', 'SERIAL PRIMARY KEY', $sql);
$sql = preg_replace('/\bBIGINT\s+UNSIGNED\s+AUTO_INCREMENT\s+PRIMARY\s+KEY\b/i', 'BIGSERIAL PRIMARY KEY', $sql);

// Remove leftover AUTO_INCREMENT.
$sql = preg_replace('/\bAUTO_INCREMENT\b/i', '', $sql);

// Replace unsigned integers.
$sql = preg_replace('/\bBIGINT\s+UNSIGNED\b/i', 'BIGINT', $sql);
$sql = preg_replace('/\bINT\s+UNSIGNED\b/i', 'INTEGER', $sql);

// Replace remaining UNSIGNED.
$sql = preg_replace('/\bUNSIGNED\b/i', '', $sql);

// TINYINT mapping:
// - TINYINT(1) -> BOOLEAN
// - Other TINYINT -> INTEGER (for attempts/scoring).
$sql = preg_replace('/\bTINYINT\s*\(\s*1\s*\)/i', 'BOOLEAN', $sql);
$sql = preg_replace('/\bTINYINT\s+UNSIGNED\b/i', 'INTEGER', $sql);
$sql = preg_replace('/\bTINYINT\b/i', 'INTEGER', $sql);

// Clean up display-width leftovers like INTEGER(1).
$sql = preg_replace('/\bINTEGER\s*\(\s*1\s*\)\b/i', 'INTEGER', $sql);

// BOOLEAN default normalization (MySQL uses 0/1).
$sql = preg_replace('/\bBOOLEAN\s+DEFAULT\s+0\b/i', 'BOOLEAN DEFAULT FALSE', $sql);
$sql = preg_replace('/\bBOOLEAN\s+DEFAULT\s+1\b/i', 'BOOLEAN DEFAULT TRUE', $sql);

// Timestamps: allow CURRENT_TIMESTAMP defaults; keep as-is.
// (If you see errors around `TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP`, adjust manually.)

// UNIQUE KEY -> CONSTRAINT ... UNIQUE.
$sql = preg_replace(
    '/^\s*UNIQUE\s+KEY\s+(\w+)\s*\(([^)]+)\)\s*,?\s*$/mi',
    '    CONSTRAINT $1 UNIQUE ($2),',
    $sql
);

// Clean up any "CONSTRAINT ... UNIQUE (...)," that ends up with an extra comma
// right before the closing paren.
$sql = preg_replace(
    '/CONSTRAINT\s+(\w+)\s+UNIQUE\s*\(([^)]+)\),\s*\n\s*\)/m',
    'CONSTRAINT $1 UNIQUE ($2)\n)',
    $sql
);

// A very light cleanup: multiple semicolons can happen after clause stripping.
$sql = preg_replace('/;{2,}/', ';', $sql);

// Replace header comment block so generated file documents PostgreSQL usage.
$sql = preg_replace(
    '/^-- Engine: MySQL \/ MariaDB.*?(?=-- ===================== CORE)/ms',
    <<<'HDR'
-- Engine: PostgreSQL (generated from database/schema.sql)
--
-- Regenerate after editing schema.sql:
--   php database/convert_schema_to_postgres.php
--
-- Clean reimport:
--   1. dropdb facilities_admin_db && createdb facilities_admin_db
--   2. psql -U postgres -d facilities_admin_db -f database/schema.postgres.sql
--   3. Default logins (password for all: Password123!):
--        admin@facilitiesadmin.local
--        staff@facilitiesadmin.local
--        employee@facilitiesadmin.local
--

HDR,
    $sql,
    1
);

// Write output.
if (file_put_contents($outputPath, $sql) === false) {
    fwrite(STDERR, "Failed writing: {$outputPath}\n");
    exit(1);
}

$size = filesize($outputPath);
echo "Generated: {$outputPath} (" . ($size !== false ? $size : '?') . " bytes)\n";

