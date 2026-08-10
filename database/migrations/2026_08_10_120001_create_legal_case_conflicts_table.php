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
        Schema::create('legal_case_party_conflicts', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('case_id')->index();
            $table->unsignedInteger('party_a_id');
            $table->unsignedInteger('party_b_id');
            $table->enum('conflict_type', ['direct_conflict', 'prior_representation', 'family_relation', 'financial_interest', 'other'])->default('other');
            $table->text('description')->nullable();
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->boolean('conflict_flagged')->default(true);
            $table->unsignedInteger('flagged_by')->nullable();
            $table->timestamp('flagged_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->enum('resolution_status', ['pending', 'reviewed', 'waived', 'mitigated', 'unresolved'])->default('pending');
            $table->timestamps();
            
            $table->foreign('case_id')->references('id')->on('legal_cases')->onDelete('cascade');
            $table->foreign('party_a_id')->references('id')->on('legal_case_parties')->onDelete('cascade');
            $table->foreign('party_b_id')->references('id')->on('legal_case_parties')->onDelete('cascade');
            $table->foreign('flagged_by')->references('id')->on('users')->onDelete('set null');
            
            $table->unique(['case_id', 'party_a_id', 'party_b_id']);
        });

        // Counsel conflicts with vendors/external parties
        Schema::create('legal_case_counsel_conflicts', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('case_id')->index();
            $table->unsignedInteger('counsel_id')->nullable();
            $table->string('external_party')->nullable();
            $table->enum('conflict_type', ['prior_representation', 'financial_interest', 'family_relation', 'business_relation', 'other'])->default('other');
            $table->text('description')->nullable();
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->boolean('conflict_verified')->default(false);
            $table->unsignedInteger('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->text('mitigation_plan')->nullable();
            $table->enum('status', ['under_review', 'confirmed', 'waived', 'resolved', 'dismissed'])->default('under_review');
            $table->timestamps();
            
            $table->foreign('case_id')->references('id')->on('legal_cases')->onDelete('cascade');
            $table->foreign('counsel_id')->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('verified_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('legal_case_counsel_conflicts');
        Schema::dropIfExists('legal_case_party_conflicts');
    }
};
