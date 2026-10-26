<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5 — administrator-managed approval workflows. One workflow per
 * transaction type (active flag + version). Editing steps bumps `version`;
 * transactions keep an immutable JSON snapshot (transactions.workflow_data)
 * taken at submission, so later edits never alter an in-flight path.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trx_workflows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trx_type_id')->constrained('trx_types')->cascadeOnDelete();
            $table->string('name', 150);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('trx_workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained('trx_workflows')->cascadeOnDelete();
            $table->unsignedInteger('step_order');
            $table->string('name_ar', 150);
            $table->string('name_en', 150)->nullable();
            // department | role | user — who acts at this step.
            $table->string('target_type', 16);
            $table->foreignId('target_dept_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('target_role', 50)->nullable();
            $table->foreignId('target_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['workflow_id', 'step_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trx_workflow_steps');
        Schema::dropIfExists('trx_workflows');
    }
};
