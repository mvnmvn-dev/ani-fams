<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('legal_case_outcomes', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('case_id')->index();
            $table->enum('outcome_type', ['won', 'lost', 'settled', 'dismissed', 'appealed', 'other'])->default('other');
            $table->date('outcome_date')->nullable();
            $table->text('description')->nullable();
            
            // Settlement tracking
            $table->decimal('settlement_amount', 14, 2)->nullable();
            $table->text('settlement_terms')->nullable();
            $table->date('settlement_date')->nullable();
            
            // Judgment info
            $table->text('judgment_summary')->nullable();
            $table->decimal('awarded_amount', 14, 2)->nullable();
            
            // Appeal tracking
            $table->boolean('is_appealed')->default(false);
            $table->text('appeal_reason')->nullable();
            $table->date('appeal_deadline')->nullable();
            
            // Record keeping
            $table->unsignedInteger('recorded_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('case_id')->references('id')->on('legal_cases')->onDelete('cascade');
            $table->foreign('recorded_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('legal_case_outcomes');
    }
};
