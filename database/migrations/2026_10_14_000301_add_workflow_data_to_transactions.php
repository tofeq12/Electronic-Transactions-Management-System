<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5 — immutable workflow snapshot per transaction (additive, nullable:
 * old rows and types without a workflow keep the single-step behaviour).
 *
 * Shape: {id, version, started_at, current_step, total,
 *         steps: [{name, target_type, target_dept, target_role, target_user}],
 *         completed: [{step, by, at, decision, reason}]}
 */
return new class extends Migration
{
    public function up(): void
    {
        // Additive + nullable: plain ADD COLUMN works on MySQL (JSON) and
        // SQLite (TEXT) alike; every existing row keeps NULL (single-step
        // behaviour untouched).
        Schema::table('transactions', function (Blueprint $table) {
            $table->json('workflow_data')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('workflow_data');
        });
    }
};
