<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('integration_clients')) {
            Schema::create('integration_clients', function (Blueprint $table): void {
                $table->increments('id');
                $table->string('system_code', 20)->unique();
                $table->string('name', 150);
                $table->string('key_prefix', 12)->unique();
                $table->string('key_hash');
                $table->text('signing_secret')->nullable();
                $table->json('scopes');
                $table->string('allowed_ips', 500)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamp('last_used_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('external_refs')) {
            Schema::create('external_refs', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->string('source_system', 20);
                $table->string('resource', 50);
                $table->string('external_id', 100);
                $table->string('local_entity', 50);
                $table->unsignedBigInteger('local_id');
                $table->char('payload_hash', 64)->nullable();
                $table->timestamp('last_synced_at')->nullable();
                $table->unique(['source_system', 'resource', 'external_id']);
                $table->index(['local_entity', 'local_id']);
            });
        }

        if (! Schema::hasTable('integration_log')) {
            Schema::create('integration_log', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->string('source_system', 20);
                $table->string('direction', 3);
                $table->string('resource', 50);
                $table->string('external_id', 100)->nullable();
                $table->string('status', 20);
                $table->text('error')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }

        $this->addColumn('employees', 'source_system', fn (Blueprint $t) => $t->string('source_system', 20)->nullable());
        $this->addColumn('employees', 'employee_no', fn (Blueprint $t) => $t->string('employee_no', 50)->nullable());
        $this->addIndexIfPossible('employees', ['source_system', 'employee_no'], 'employees_source_employee_unique', true);

        $this->addColumn('visitors', 'visitor_type', fn (Blueprint $t) => $t->string('visitor_type', 20)->default('guest'));
        $this->addColumn('visitors', 'source_system', fn (Blueprint $t) => $t->string('source_system', 20)->default('manual'));
        $this->addColumn('visitors', 'source_ref', fn (Blueprint $t) => $t->string('source_ref', 100)->nullable());
        $this->addColumn('visitors', 'id_verified_at', fn (Blueprint $t) => $t->timestamp('id_verified_at')->nullable());
        $this->addColumn('visitors', 'id_verified_by', fn (Blueprint $t) => $t->unsignedInteger('id_verified_by')->nullable());
        $this->addIndexIfPossible('visitors', ['source_system', 'source_ref'], 'visitors_source_ref_index');

        $this->addColumn('visits', 'visit_category', fn (Blueprint $t) => $t->string('visit_category', 30)->default('general'));
        $this->addColumn('visits', 'source_system', fn (Blueprint $t) => $t->string('source_system', 20)->default('manual'));
        $this->addColumn('visits', 'source_ref', fn (Blueprint $t) => $t->string('source_ref', 100)->nullable());
        $this->addIndexIfPossible('visits', ['source_system', 'source_ref'], 'visits_source_ref_index');

        $this->addColumn('documents', 'source_system', fn (Blueprint $t) => $t->string('source_system', 20)->default('manual'));
        $this->addColumn('documents', 'source_ref', fn (Blueprint $t) => $t->string('source_ref', 100)->nullable());
        $this->addColumn('documents', 'source_doc_type', fn (Blueprint $t) => $t->string('source_doc_type', 50)->nullable());
        $this->addColumn('documents', 'employee_no', fn (Blueprint $t) => $t->string('employee_no', 50)->nullable());
        $this->addColumn('documents', 'period_start', fn (Blueprint $t) => $t->date('period_start')->nullable());
        $this->addColumn('documents', 'period_end', fn (Blueprint $t) => $t->date('period_end')->nullable());
        $this->addIndexIfPossible('documents', ['source_system', 'source_ref'], 'documents_source_ref_unique', true);
        $this->addIndexIfPossible('documents', ['employee_no'], 'documents_employee_no_index');

        if (Schema::hasTable('retention_source_rules') === false) {
            Schema::create('retention_source_rules', function (Blueprint $table): void {
                $table->increments('id');
                $table->string('source_system', 20);
                $table->string('doc_type', 50);
                $table->unsignedInteger('category_id')->nullable();
                $table->unsignedInteger('policy_id');
                $table->string('confidentiality', 20)->default('restricted');
                $table->boolean('is_active')->default(true);
                $table->unique(['source_system', 'doc_type']);
            });
        }

        if (Schema::hasTable('retention_source_rules') && Schema::hasTable('document_categories') && Schema::hasTable('retention_policies')) {
            $rules = [
                ['hr1', 'employment_contract', 'HR Documents', 'Standard HR Records - 5 Years', 'restricted'],
                ['hr1', 'employee_201', 'HR Documents', 'Standard HR Records - 5 Years', 'restricted'],
                ['hr1', 'applicant_resume', 'HR Documents', 'Data Privacy Act - 3 Years', 'restricted'],
                ['hr1', 'interview_notes', 'HR Documents', 'Data Privacy Act - 3 Years', 'restricted'],
                ['hr2', 'attendance_dtr', 'HR Documents', 'Labor Records - 3 Years', 'restricted'],
                ['hr2', 'leave_record', 'HR Documents', 'Labor Records - 3 Years', 'restricted'],
                ['hr3', 'appraisal', 'HR Documents', 'Labor Records - 3 Years', 'restricted'],
                ['hr3', 'training_certificate', 'HR Documents', 'Labor Records - 3 Years', 'restricted'],
                ['hr4', 'payroll_register', 'HR Documents', 'Labor Records - 3 Years', 'restricted'],
                ['hr4', 'payslip_batch', 'HR Documents', 'Labor Records - 3 Years', 'restricted'],
                ['hr4', 'remittance_report', 'HR Documents', 'BIR Tax Records - 7 Years', 'restricted'],
                ['hr4', 'bir_2316', 'HR Documents', 'BIR Tax Records - 7 Years', 'restricted'],
            ];
            foreach ($rules as [$source, $docType, $categoryName, $policyName, $confidentiality]) {
                $categoryId = DB::table('document_categories')->where('name', $categoryName)->value('id');
                $policyId = DB::table('retention_policies')->where('name', $policyName)->value('id');
                if (! $policyId && in_array($policyName, ['Data Privacy Act - 3 Years', 'Labor Records - 3 Years'], true)) {
                    $policyId = DB::table('retention_policies')->where('name', 'Standard HR Records - 5 Years')->value('id');
                }
                if ($categoryId && $policyId) {
                    DB::table('retention_source_rules')->updateOrInsert(
                        ['source_system' => $source, 'doc_type' => $docType],
                        ['category_id' => $categoryId, 'policy_id' => $policyId, 'confidentiality' => $confidentiality, 'is_active' => true]
                    );
                }
            }
        }

        // PostgreSQL and SQLite already use text for this column. Keeping it
        // as a string also permits separation_date on MySQL installations.
        if (Schema::hasColumn('retention_policies', 'trigger_event')) {
            // The application validates allowed values in service requests.
        }

        $roleId = DB::table('roles')->where('code', 'staff')->value('id');
        if ($roleId && ! DB::table('users')->where('email', config('integration.system_user_email'))->exists()) {
            DB::table('users')->insert([
                'full_name' => 'FAMS Integration Service',
                'email' => config('integration.system_user_email'),
                'password_hash' => Hash::make(bin2hex(random_bytes(24))),
                'role_id' => $roleId,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function addColumn(string $table, string $column, \Closure $definition): void
    {
        if (Schema::hasTable($table) && ! Schema::hasColumn($table, $column)) {
            Schema::table($table, $definition);
        }
    }

    private function addIndexIfPossible(string $table, array $columns, string $name, bool $unique = false): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }
        try {
            Schema::table($table, function (Blueprint $blueprint) use ($columns, $name, $unique): void {
                $unique ? $blueprint->unique($columns, $name) : $blueprint->index($columns, $name);
            });
        } catch (\Throwable) {
            // Existing installations may already have the equivalent index.
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('retention_source_rules');
        Schema::dropIfExists('integration_log');
        Schema::dropIfExists('external_refs');
        Schema::dropIfExists('integration_clients');
    }
};
