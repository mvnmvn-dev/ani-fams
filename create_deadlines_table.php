<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

DB::statement('
    CREATE TABLE IF NOT EXISTS legal_case_deadlines (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        case_id INT UNSIGNED NOT NULL,
        title VARCHAR(255) NOT NULL,
        due_date DATE NOT NULL,
        description TEXT NULL,
        is_completed TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP NULL,
        updated_at TIMESTAMP NULL,
        FOREIGN KEY (case_id) REFERENCES legal_cases(id) ON DELETE CASCADE
    )
');

echo "Table created successfully.\n";
