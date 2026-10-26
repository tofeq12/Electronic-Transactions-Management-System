<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 8 — audit notes. activity_logs already stored who/what; `note`
 * carries the security-relevant detail of an edit (e.g. which parts of a
 * user changed: role, status, password reset). Nullable and additive:
 * existing rows and every current reader keep working untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->string('note')->nullable()->after('target');
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropColumn('note');
        });
    }
};
