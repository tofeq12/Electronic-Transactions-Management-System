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
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->unique()->after('name');
            $table->string('phone')->nullable()->after('email');
            $table->string('role')->default('employee')->after('phone');
            $table->string('status')->default('active')->after('role');
            $table->foreignId('college_id')->nullable()->after('status')
                ->constrained('colleges')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->after('college_id')
                ->constrained('departments')->nullOnDelete();
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('college_id');
            $table->dropConstrainedForeignId('department_id');
            $table->dropIndex(['status']);
            $table->dropUnique(['username']);
            $table->dropColumn(['username', 'phone', 'role', 'status']);
        });
    }
};
