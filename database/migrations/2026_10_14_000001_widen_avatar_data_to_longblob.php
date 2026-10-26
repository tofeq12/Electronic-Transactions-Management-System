<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Root-cause fix for the profile-photo upload failure
 * ("Malformed UTF-8 characters, possibly incorrectly encoded"):
 *
 * The original avatar migration used $table->binary('avatar_data'), which in
 * MySQL creates a plain BLOB — only 65,535 bytes. My earlier tests used tiny
 * (78-byte) PNGs, so the limit never showed; any real photo (>64 KB) failed
 * with "1406 Data too long for column 'avatar_data'". The raw photo bytes were
 * part of the failed statement's bindings, so Laravel's JSON error renderer
 * could not encode the exception message and replaced it with the misleading
 * UTF-8 error the user saw.
 *
 * This forward-only migration widens the column to LONGBLOB (4 GB), matching
 * the documented contract. Existing rows keep their bytes (MODIFY preserves
 * data). SQLite (used only by the test suite) has no size limit for BLOB, so
 * nothing needs to change there.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return; // SQLite BLOBs are unbounded — no ALTER needed.
        }

        DB::statement('ALTER TABLE users MODIFY avatar_data LONGBLOB NULL');
    }

    public function down(): void
    {
        // Forward-only: intentionally empty. Never shrink the column back
        // (that could truncate stored photos).
    }
};
