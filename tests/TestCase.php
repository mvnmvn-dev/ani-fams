<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $sql = $this->sqliteCompatibleSchema(file_get_contents(base_path('database/schema.sql')));
        DB::unprepared($sql);
    }

    protected function sqliteCompatibleSchema(string $sql): string
    {
        // Strip database create/use
        $sql = preg_replace('/CREATE\s+DATABASE\s+IF\s+NOT\s+EXISTS\s+\w+\s*;/i', '', $sql);
        $sql = preg_replace('/USE\s+\w+\s*;/i', '', $sql);

        // Skip circular FK alter for documents.current_version
        $sql = preg_replace(
            '/ALTER\s+TABLE\s+documents\s+ADD\s+CONSTRAINT\s+fk_documents_current_version[\s\S]*?;/i',
            '',
            $sql
        );

        // Remove ON DUPLICATE KEY UPDATE clauses (keep INSERT)
        $sql = preg_replace('/\s+ON\s+DUPLICATE\s+KEY\s+UPDATE[\s\S]*?(?=;)/i', '', $sql);

        // ENUM(...) -> TEXT
        $sql = preg_replace('/ENUM\s*\([^)]+\)/i', 'TEXT', $sql);

        // Remove UNSIGNED
        $sql = preg_replace('/\bUNSIGNED\b/i', '', $sql);

        // AUTO_INCREMENT -> remove (we'll add SQLite AUTOINCREMENT below)
        $sql = preg_replace('/\bAUTO_INCREMENT\b/i', '', $sql);

        // LONGTEXT / TINYINT / JSON / BOOLEAN
        $sql = preg_replace('/\bLONGTEXT\b/i', 'TEXT', $sql);
        $sql = preg_replace('/\bTINYINT\b/i', 'INTEGER', $sql);
        $sql = preg_replace('/\bBOOLEAN\b/i', 'INTEGER', $sql);
        $sql = preg_replace('/\bJSON\b/i', 'TEXT', $sql);

        // Remove inline INDEX definitions inside CREATE TABLE
        $sql = preg_replace('/,\s*INDEX\s+\w+\s*\([^)]+\)/i', '', $sql);

        // CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP -> CURRENT_TIMESTAMP
        $sql = preg_replace('/DEFAULT\s+CURRENT_TIMESTAMP\s+ON\s+UPDATE\s+CURRENT_TIMESTAMP/i', 'DEFAULT CURRENT_TIMESTAMP', $sql);

        // SQLite only autoincrements INTEGER PRIMARY KEY (not INT/BIGINT)
        $sql = preg_replace(
            '/\bid\s+(?:INT|INTEGER|BIGINT)\s+PRIMARY\s+KEY\b/i',
            'id INTEGER PRIMARY KEY AUTOINCREMENT',
            $sql
        );

        // ON DELETE RESTRICT is fine in SQLite; keep as-is

        return $sql;
    }
}
