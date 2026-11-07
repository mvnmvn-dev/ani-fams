<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('facilities')) {
            Schema::table('facilities', function (Blueprint $table): void {
                if (! Schema::hasColumn('facilities', 'building_name')) {
                    $table->string('building_name', 150)->nullable();
                }
                if (! Schema::hasColumn('facilities', 'floor_designation')) {
                    $table->string('floor_designation', 100)->nullable();
                }
            });
        }

        if (Schema::hasTable('reservations')) {
            Schema::table('reservations', function (Blueprint $table): void {
                if (! Schema::hasColumn('reservations', 'department_id')) {
                    $table->unsignedInteger('department_id')->nullable();
                    $table->index('department_id');
                }
            });
        }

        if (Schema::hasTable('visits')) {
            Schema::table('visits', function (Blueprint $table): void {
                if (! Schema::hasColumn('visits', 'building_name')) {
                    $table->string('building_name', 150)->nullable();
                }
                if (! Schema::hasColumn('visits', 'floor_designation')) {
                    $table->string('floor_designation', 100)->nullable();
                }
            });
        }

        if (Schema::hasTable('visitor_badges')) {
            Schema::table('visitor_badges', function (Blueprint $table): void {
                if (! Schema::hasColumn('visitor_badges', 'id_number')) {
                    $table->string('id_number', 100)->nullable();
                }
                if (! Schema::hasColumn('visitor_badges', 'purpose')) {
                    $table->string('purpose', 255)->nullable();
                }
                if (! Schema::hasColumn('visitor_badges', 'building_name')) {
                    $table->string('building_name', 150)->nullable();
                }
                if (! Schema::hasColumn('visitor_badges', 'floor_designation')) {
                    $table->string('floor_designation', 100)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['facilities' => ['building_name', 'floor_designation'], 'reservations' => ['department_id'], 'visits' => ['building_name', 'floor_designation'], 'visitor_badges' => ['id_number', 'purpose', 'building_name', 'floor_designation']] as $tableName => $columns) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }
            Schema::table($tableName, function (Blueprint $table) use ($tableName, $columns): void {
                foreach ($columns as $column) {
                    if (Schema::hasColumn($tableName, $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
