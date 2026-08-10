<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('retention_policy_templates')) {
            Schema::create('retention_policy_templates', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name', 150);
                $table->string('regulation_code', 50);
                $table->text('description')->nullable();
                $table->unsignedInteger('retention_period_months');
                $table->enum('trigger_event', ['creation_date', 'last_activity', 'contract_end', 'fiscal_year_end'])
                    ->default('creation_date');
                $table->enum('disposal_action', ['review', 'archive', 'destroy'])->default('review');
                $table->boolean('is_active')->default(true);
                $table->timestamp('created_at')->useCurrent();
            });

            DB::table('retention_policy_templates')->insert([
                [
                    'name' => 'BIR Tax Records - 7 Years',
                    'regulation_code' => 'BIR',
                    'description' => 'BIR Revenue Regulations No. 18-2012 - 7 year retention for tax documents',
                    'retention_period_months' => 84,
                    'trigger_event' => 'fiscal_year_end',
                    'disposal_action' => 'review',
                    'is_active' => 1,
                ],
                [
                    'name' => 'SEC Corporate Records - 10 Years',
                    'regulation_code' => 'SEC',
                    'description' => 'SEC requirements for corporate record retention',
                    'retention_period_months' => 120,
                    'trigger_event' => 'creation_date',
                    'disposal_action' => 'archive',
                    'is_active' => 1,
                ],
                [
                    'name' => 'Data Privacy Act - 3 Years',
                    'regulation_code' => 'DPA',
                    'description' => 'National Privacy Commission requirements for personal data',
                    'retention_period_months' => 36,
                    'trigger_event' => 'last_activity',
                    'disposal_action' => 'destroy',
                    'is_active' => 1,
                ],
                [
                    'name' => 'Labor Records - 3 Years',
                    'regulation_code' => 'DOLE',
                    'description' => 'Department of Labor and Employment requirements',
                    'retention_period_months' => 36,
                    'trigger_event' => 'creation_date',
                    'disposal_action' => 'review',
                    'is_active' => 1,
                ],
            ]);
        }

        if (Schema::hasTable('disposal_logs')) {
            Schema::table('disposal_logs', function (Blueprint $table) {
                if (! Schema::hasColumn('disposal_logs', 'certificate_generated')) {
                    $table->boolean('certificate_generated')->default(false);
                }
                if (! Schema::hasColumn('disposal_logs', 'certificate_generated_at')) {
                    $table->dateTime('certificate_generated_at')->nullable();
                }
                if (! Schema::hasColumn('disposal_logs', 'certificate_generated_by')) {
                    $table->unsignedInteger('certificate_generated_by')->nullable();
                }
            });
        }

        if (! Schema::hasTable('legal_hold_notifications')) {
            Schema::create('legal_hold_notifications', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('legal_hold_id');
                $table->unsignedInteger('user_id');
                $table->dateTime('notified_at')->useCurrent();
                $table->enum('notification_type', ['placed', 'released'])->default('placed');
                $table->dateTime('read_at')->nullable();
                $table->index('legal_hold_id');
                $table->index('user_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_hold_notifications');
        Schema::dropIfExists('retention_policy_templates');
    }
};
