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
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('sender')->nullable()->after('date');
            $table->string('recipient')->nullable()->after('sender');
            $table->decimal('amount', 15, 2)->nullable()->after('recipient');
            $table->text('description')->nullable()->after('amount');
            $table->enum('priority', ['normal', 'urgent'])->default('normal')->after('description');
            $table->foreignId('created_by')->nullable()->after('dept_id')
                ->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->after('created_by')
                ->constrained('users')->nullOnDelete();

            $table->unique('transaction_number');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('assigned_to');
            $table->dropUnique(['transaction_number']);
            $table->dropIndex(['status']);
            $table->dropColumn(['sender', 'recipient', 'amount', 'description', 'priority']);
        });
    }
};
