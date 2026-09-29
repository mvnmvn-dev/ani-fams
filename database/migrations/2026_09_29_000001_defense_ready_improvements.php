<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Defense Ready v1.0 — additive tables/columns within existing modules.
 * Safe for PostgreSQL (TEXT statuses) and MySQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('reservations')) {
            Schema::table('reservations', function (Blueprint $table) {
                if (! Schema::hasColumn('reservations', 'check_in_code')) {
                    $table->string('check_in_code', 64)->nullable()->after('ai_notes');
                }
                if (! Schema::hasColumn('reservations', 'checked_in_at')) {
                    $table->dateTime('checked_in_at')->nullable();
                }
                if (! Schema::hasColumn('reservations', 'checked_in_by')) {
                    $table->unsignedInteger('checked_in_by')->nullable();
                }
                if (! Schema::hasColumn('reservations', 'completed_at')) {
                    $table->dateTime('completed_at')->nullable();
                }
                if (! Schema::hasColumn('reservations', 'attendee_count')) {
                    $table->unsignedInteger('attendee_count')->nullable();
                }
            });
        }

        if (! Schema::hasTable('reservation_logs')) {
            Schema::create('reservation_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('reservation_id');
                $table->unsignedInteger('actor_id')->nullable();
                $table->string('action', 80);
                $table->string('from_status', 50)->nullable();
                $table->string('to_status', 50)->nullable();
                $table->text('remarks')->nullable();
                $table->json('meta')->nullable();
                $table->dateTime('created_at')->useCurrent();
                $table->index(['reservation_id', 'created_at']);
            });
        }

        if (Schema::hasTable('visits')) {
            Schema::table('visits', function (Blueprint $table) {
                if (! Schema::hasColumn('visits', 'host_approved_at')) {
                    $table->dateTime('host_approved_at')->nullable();
                }
                if (! Schema::hasColumn('visits', 'host_approved_by')) {
                    $table->unsignedInteger('host_approved_by')->nullable();
                }
                if (! Schema::hasColumn('visits', 'badge_expires_at')) {
                    $table->dateTime('badge_expires_at')->nullable();
                }
                if (! Schema::hasColumn('visits', 'expected_duration_hours')) {
                    $table->unsignedInteger('expected_duration_hours')->nullable()->default(8);
                }
                if (! Schema::hasColumn('visits', 'registration_type')) {
                    $table->string('registration_type', 30)->nullable()->default('pre_register');
                }
            });
        }

        if (! Schema::hasTable('visitor_badges')) {
            Schema::create('visitor_badges', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('visit_id');
                $table->unsignedInteger('visitor_id');
                $table->string('badge_code', 50);
                $table->string('qr_payload', 255)->nullable();
                $table->string('visitor_name', 150);
                $table->string('company', 150)->nullable();
                $table->string('host_name', 150)->nullable();
                $table->string('photo_path', 500)->nullable();
                $table->dateTime('issued_at');
                $table->dateTime('expires_at');
                $table->string('status', 30)->default('active'); // active|expired|revoked
                $table->dateTime('created_at')->useCurrent();
                $table->unique('badge_code');
                $table->index(['visit_id', 'status']);
            });
        }

        if (! Schema::hasTable('document_metadata')) {
            Schema::create('document_metadata', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('document_id');
                $table->string('extracted_title', 255)->nullable();
                $table->string('reference_number', 150)->nullable();
                $table->date('date_issued')->nullable();
                $table->date('expiration_date')->nullable();
                $table->string('department', 150)->nullable();
                $table->string('category_detected', 100)->nullable();
                $table->decimal('ocr_confidence', 5, 2)->nullable();
                $table->json('raw_fields')->nullable();
                $table->dateTime('extracted_at')->nullable();
                $table->dateTime('created_at')->useCurrent();
                $table->dateTime('updated_at')->nullable();
                $table->unique('document_id');
            });
        }

        if (! Schema::hasTable('ai_document_summaries')) {
            Schema::create('ai_document_summaries', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('document_id');
                $table->text('summary')->nullable();
                $table->text('important_deadlines')->nullable();
                $table->text('required_actions')->nullable();
                $table->text('key_information')->nullable();
                $table->string('classification', 100)->nullable();
                $table->decimal('confidence', 5, 2)->nullable();
                $table->unsignedInteger('generated_by')->nullable();
                $table->dateTime('generated_at')->useCurrent();
                $table->index('document_id');
            });
        }

        if (! Schema::hasTable('document_activity_logs')) {
            Schema::create('document_activity_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('document_id');
                $table->unsignedInteger('user_id')->nullable();
                $table->string('action', 50); // uploaded|viewed|downloaded|edited|archived|restored
                $table->text('details')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->dateTime('created_at')->useCurrent();
                $table->index(['document_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('compliance_alerts')) {
            Schema::create('compliance_alerts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('retention_assignment_id')->nullable();
                $table->unsignedBigInteger('document_id')->nullable();
                $table->string('alert_type', 50); // 90_days|60_days|30_days|expiration|overdue|missing_signature|missing_attachment
                $table->string('compliance_status', 50)->nullable(); // compliant|expiring_soon|overdue|archived
                $table->string('title', 255);
                $table->text('message')->nullable();
                $table->unsignedInteger('days_remaining')->nullable();
                $table->string('status', 30)->default('open'); // open|acknowledged|resolved
                $table->dateTime('notified_at')->nullable();
                $table->dateTime('resolved_at')->nullable();
                $table->dateTime('created_at')->useCurrent();
                $table->index(['status', 'alert_type']);
                $table->index('retention_assignment_id');
            });
        }

        if (Schema::hasTable('retention_assignments')) {
            Schema::table('retention_assignments', function (Blueprint $table) {
                if (! Schema::hasColumn('retention_assignments', 'compliance_status')) {
                    $table->string('compliance_status', 50)->nullable()->default('compliant');
                }
                if (! Schema::hasColumn('retention_assignments', 'disposal_workflow_status')) {
                    $table->string('disposal_workflow_status', 50)->nullable();
                }
                if (! Schema::hasColumn('retention_assignments', 'archive_date')) {
                    $table->date('archive_date')->nullable();
                }
            });
        }

        if (! Schema::hasTable('legal_activity_logs')) {
            Schema::create('legal_activity_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('case_id');
                $table->unsignedInteger('actor_id')->nullable();
                $table->string('action', 80);
                $table->string('from_status', 50)->nullable();
                $table->string('to_status', 50)->nullable();
                $table->text('remarks')->nullable();
                $table->json('meta')->nullable();
                $table->dateTime('created_at')->useCurrent();
                $table->index(['case_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('contract_versions')) {
            Schema::create('contract_versions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('contract_id');
                $table->unsignedInteger('version_no');
                $table->string('label', 150)->nullable();
                $table->text('change_summary')->nullable();
                $table->unsignedBigInteger('document_id')->nullable();
                $table->text('ai_clauses')->nullable(); // JSON blob of extracted clauses
                $table->unsignedInteger('created_by')->nullable();
                $table->dateTime('created_at')->useCurrent();
                $table->unique(['contract_id', 'version_no']);
            });
        }

        if (Schema::hasTable('contracts')) {
            Schema::table('contracts', function (Blueprint $table) {
                if (! Schema::hasColumn('contracts', 'ai_clauses')) {
                    $table->text('ai_clauses')->nullable();
                }
                if (! Schema::hasColumn('contracts', 'renewal_status')) {
                    $table->string('renewal_status', 50)->nullable();
                }
            });
        }

        if (! Schema::hasTable('automation_history')) {
            Schema::create('automation_history', function (Blueprint $table) {
                $table->id();
                $table->string('module', 50);
                $table->string('action', 100);
                $table->string('entity_type', 80)->nullable();
                $table->unsignedBigInteger('entity_id')->nullable();
                $table->text('payload')->nullable();
                $table->string('triggered_by', 50)->default('system'); // system|arnel|scheduler
                $table->dateTime('created_at')->useCurrent();
                $table->index(['module', 'created_at']);
            });
        }

        // Seed defense-oriented role labels if missing (codes stay compatible).
        $this->seedDefenseRoles();
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_history');
        Schema::dropIfExists('contract_versions');
        Schema::dropIfExists('legal_activity_logs');
        Schema::dropIfExists('compliance_alerts');
        Schema::dropIfExists('document_activity_logs');
        Schema::dropIfExists('ai_document_summaries');
        Schema::dropIfExists('document_metadata');
        Schema::dropIfExists('visitor_badges');
        Schema::dropIfExists('reservation_logs');

        if (Schema::hasTable('reservations')) {
            Schema::table('reservations', function (Blueprint $table) {
                foreach (['check_in_code', 'checked_in_at', 'checked_in_by', 'completed_at', 'attendee_count'] as $col) {
                    if (Schema::hasColumn('reservations', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('visits')) {
            Schema::table('visits', function (Blueprint $table) {
                foreach (['host_approved_at', 'host_approved_by', 'badge_expires_at', 'expected_duration_hours', 'registration_type'] as $col) {
                    if (Schema::hasColumn('visits', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('retention_assignments')) {
            Schema::table('retention_assignments', function (Blueprint $table) {
                foreach (['compliance_status', 'disposal_workflow_status', 'archive_date'] as $col) {
                    if (Schema::hasColumn('retention_assignments', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('contracts')) {
            Schema::table('contracts', function (Blueprint $table) {
                foreach (['ai_clauses', 'renewal_status'] as $col) {
                    if (Schema::hasColumn('contracts', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }

    private function seedDefenseRoles(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        $roles = [
            ['code' => 'facilities_admin', 'name' => 'Facilities Administrator', 'description' => 'Facilities reservation and visitor operations'],
            ['code' => 'legal_officer', 'name' => 'Legal Officer', 'description' => 'Legal case and contract oversight'],
            ['code' => 'compliance_officer', 'name' => 'Compliance Officer', 'description' => 'Retention, compliance, and disposal workflow'],
        ];

        foreach ($roles as $role) {
            $exists = DB::table('roles')->where('code', $role['code'])->exists();
            if (! $exists) {
                DB::table('roles')->insert($role);
            }
        }
    }
};
