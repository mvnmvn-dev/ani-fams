<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('visitors')) {
            Schema::table('visitors', function (Blueprint $table): void {
                if (! Schema::hasColumn('visitors', 'visitor_type')) {
                    $table->string('visitor_type', 20)->default('guest');
                }
                if (! Schema::hasColumn('visitors', 'source_system')) {
                    $table->string('source_system', 20)->default('manual');
                }
                if (! Schema::hasColumn('visitors', 'source_ref')) {
                    $table->string('source_ref', 100)->nullable();
                }
                if (! Schema::hasColumn('visitors', 'id_verified_at')) {
                    $table->timestamp('id_verified_at')->nullable();
                }
                if (! Schema::hasColumn('visitors', 'id_verified_by')) {
                    $table->unsignedInteger('id_verified_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('visits')) {
            Schema::table('visits', function (Blueprint $table): void {
                if (! Schema::hasColumn('visits', 'visit_category')) {
                    $table->string('visit_category', 30)->default('general');
                }
                if (! Schema::hasColumn('visits', 'source_system')) {
                    $table->string('source_system', 20)->default('manual');
                }
                if (! Schema::hasColumn('visits', 'source_ref')) {
                    $table->string('source_ref', 100)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        // Intentionally non-destructive: these fields may have existed before this repair.
    }
};
