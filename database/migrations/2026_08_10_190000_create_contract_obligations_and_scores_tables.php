<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_obligations', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('contract_id')->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('responsible_party')->nullable();
            $table->date('due_date')->nullable();
            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('completed_by')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('contract_id')->references('id')->on('contracts')->onDelete('cascade');
            $table->foreign('completed_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
        });

        Schema::create('contract_vendor_scores', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('contract_id')->index();
            $table->unsignedTinyInteger('quality_score');
            $table->unsignedTinyInteger('timeliness_score');
            $table->unsignedTinyInteger('communication_score');
            $table->unsignedTinyInteger('value_score');
            $table->decimal('overall_score', 4, 2);
            $table->text('notes')->nullable();
            $table->date('scored_at')->nullable();
            $table->unsignedInteger('scored_by')->nullable();
            $table->timestamps();

            $table->foreign('contract_id')->references('id')->on('contracts')->onDelete('cascade');
            $table->foreign('scored_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_vendor_scores');
        Schema::dropIfExists('contract_obligations');
    }
};
