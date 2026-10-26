<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * User profile extensions (forward-only, additive):
 * - locale:   per-user interface language preference (ar|en), default ar.
 * - avatar_path: private-disk path of the uploaded profile photo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar_path', 191)->nullable()->after('department_id');
            $table->string('locale', 2)->default('ar')->after('avatar_path');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['avatar_path', 'locale']);
        });
    }
};
