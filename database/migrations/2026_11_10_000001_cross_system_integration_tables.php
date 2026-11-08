<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('contracts')) {
            Schema::table('contracts', function (Blueprint $table): void {
                if (! Schema::hasColumn('contracts', 'external_contract_type')) $table->string('external_contract_type', 50)->nullable();
                if (! Schema::hasColumn('contracts', 'purchase_order_ref')) $table->string('purchase_order_ref', 100)->nullable();
            });
        }
        if (! Schema::hasTable('integration_resource_availability')) {
            Schema::create('integration_resource_availability', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->string('source_system', 20);
                $table->string('external_id', 100);
                $table->string('resource_type', 50);
                $table->string('resource_name', 150);
                $table->string('employee_no', 50)->nullable();
                $table->string('reason_code', 50)->nullable();
                $table->dateTime('starts_at');
                $table->dateTime('ends_at');
                $table->boolean('is_available')->default(true);
                $table->json('payload')->nullable();
                $table->timestamps();
                $table->unique(['source_system', 'external_id'], 'int_availability_source_external_unique');
                $table->index(['resource_type', 'starts_at', 'ends_at'], 'int_availability_window_index');
            });
        }
        if (! Schema::hasTable('integration_outbound_events')) {
            Schema::create('integration_outbound_events', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->string('system_code', 20);
                $table->string('event_id', 150)->unique();
                $table->string('event_type', 80);
                $table->json('payload');
                $table->string('status', 20)->default('pending');
                $table->unsignedInteger('attempts')->default(0);
                $table->text('last_error')->nullable();
                $table->timestamp('last_attempt_at')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();
                $table->index(['system_code', 'status'], 'integration_outbox_status_index');
            });
        }
        if (! Schema::hasTable('document_tracking_refs')) {
            Schema::create('document_tracking_refs', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->string('source_system', 20);
                $table->string('tracking_external_id', 100);
                $table->unsignedBigInteger('document_id');
                $table->string('status', 20);
                $table->timestamp('source_updated_at')->nullable();
                $table->timestamps();
                $table->unique(['source_system', 'tracking_external_id'], 'doc_tracking_source_external_unique');
                $table->index('document_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('contracts')) {
            Schema::table('contracts', function (Blueprint $table): void {
                $columns = [];
                if (Schema::hasColumn('contracts', 'external_contract_type')) $columns[] = 'external_contract_type';
                if (Schema::hasColumn('contracts', 'purchase_order_ref')) $columns[] = 'purchase_order_ref';
                if ($columns !== []) $table->dropColumn($columns);
            });
        }
        Schema::dropIfExists('document_tracking_refs');
        Schema::dropIfExists('integration_outbound_events');
        Schema::dropIfExists('integration_resource_availability');
    }
};
