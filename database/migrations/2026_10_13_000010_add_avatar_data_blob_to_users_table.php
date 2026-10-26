<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Store the actual profile-photo bytes inside MySQL (forward-only, additive):
 * - avatar_data: LONGBLOB holding the real image bytes (nullable).
 * - avatar_mime: the image MIME type recorded at upload time (nullable).
 *
 * The existing avatar_path column and any files on the private disk are left
 * untouched; serveAvatar() falls back to avatar_path (legacy files) and then
 * to the shared default avatar, so nothing is lost during the transition.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->binary('avatar_data')->nullable()->after('avatar_path');
            $table->string('avatar_mime', 40)->nullable()->after('avatar_data');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['avatar_data', 'avatar_mime']);
        });
    }
};
