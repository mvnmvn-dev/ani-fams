<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('documents')) return;
        Schema::table('documents', function (Blueprint $table) {
            if (! Schema::hasColumn('documents', 'ai_classification')) $table->string('ai_classification', 150)->nullable();
            if (! Schema::hasColumn('documents', 'ai_summary')) $table->text('ai_summary')->nullable();
            if (! Schema::hasColumn('documents', 'ai_keywords')) $table->text('ai_keywords')->nullable();
            if (! Schema::hasColumn('documents', 'ai_metadata')) $table->text('ai_metadata')->nullable();
            if (! Schema::hasColumn('documents', 'ai_confidence')) $table->decimal('ai_confidence', 5, 2)->nullable();
            if (! Schema::hasColumn('documents', 'ai_processed_at')) $table->dateTime('ai_processed_at')->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('documents')) return;
        Schema::table('documents', function (Blueprint $table) {
            foreach (['ai_classification', 'ai_summary', 'ai_keywords', 'ai_metadata', 'ai_confidence', 'ai_processed_at'] as $column) {
                if (Schema::hasColumn('documents', $column)) $table->dropColumn($column);
            }
        });
    }
};
