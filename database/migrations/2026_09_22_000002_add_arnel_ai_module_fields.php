<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private array $fields = [
        'reservations' => [
            'ai_suggestion' => ['string', 500], 'conflict_status' => ['string', 50], 'ai_notes' => ['text', null],
        ],
        'contracts' => [
            'ai_summary' => ['text', null], 'expiration_alert' => ['string', 50],
        ],
        'legal_cases' => [
            'ai_summary' => ['text', null], 'next_deadline' => ['date', null],
        ],
        'retention_assignments' => [
            'ai_category' => ['string', 150], 'archive_status' => ['string', 50],
        ],
        'documents' => [
            'ai_category' => ['string', 150], 'ai_tags' => ['text', null],
        ],
    ];

    public function up(): void
    {
        foreach ($this->fields as $tableName => $fields) {
            if (! Schema::hasTable($tableName)) continue;
            Schema::table($tableName, function (Blueprint $table) use ($tableName, $fields): void {
                foreach ($fields as $name => [$type, $length]) {
                    if (Schema::hasColumn($tableName, $name)) continue;
                    if ($type === 'text') $table->text($name)->nullable();
                    elseif ($type === 'date') $table->date($name)->nullable();
                    else $table->string($name, $length)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->fields as $tableName => $fields) {
            if (! Schema::hasTable($tableName)) continue;
            Schema::table($tableName, function (Blueprint $table) use ($tableName, $fields): void {
                foreach (array_keys($fields) as $name) if (Schema::hasColumn($tableName, $name)) $table->dropColumn($name);
            });
        }
    }
};
