<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4/6 groundwork (additive, data-preserving):
 * - transactions.type/status move from engine ENUMs to VARCHAR so that
 *   administrator-defined type slugs and the dedicated "returned" state
 *   (Phase 6) can be stored. Existing values are copied unchanged; the
 *   server-side validation (FormRequests + TransactionTypes) keeps
 *   enforcing the allowed sets.
 * - New nullable JSON column transactions.form_data holds dynamic
 *   per-type field values (Phase 4). NULL for every existing record.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->json('form_data')->nullable();
        });

        if (DB::getDriverName() === 'sqlite') {
            // SQLite cannot ALTER a CHECK constraint — rebuild the table
            // keeping every column, row and foreign key intact.
            Schema::create('transactions_rebuild', function (Blueprint $table) {
                $table->id();
                $table->string('transaction_number');
                $table->string('subject');
                $table->string('type', 64);
                $table->date('date');
                $table->string('sender')->nullable();
                $table->string('recipient')->nullable();
                $table->decimal('amount', 15, 2)->nullable();
                $table->text('description')->nullable();
                $table->string('priority', 16)->default('normal');
                $table->string('status', 16);
                $table->foreignId('dept_id')->nullable()->constrained('departments')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
                $table->json('form_data')->nullable();
                $table->timestamps();

                $table->unique('transaction_number');
                $table->index('status');
            });

            DB::statement('INSERT INTO transactions_rebuild (id, transaction_number, subject, type, date, sender, recipient, amount, description, priority, status, dept_id, created_by, assigned_to, form_data, created_at, updated_at)
                SELECT id, transaction_number, subject, type, date, sender, recipient, amount, description, priority, status, dept_id, created_by, assigned_to, form_data, created_at, updated_at FROM transactions');

            Schema::drop('transactions');
            Schema::rename('transactions_rebuild', 'transactions');

            return;
        }

        DB::statement('ALTER TABLE transactions MODIFY type VARCHAR(64) NOT NULL');
        DB::statement('ALTER TABLE transactions MODIFY status VARCHAR(16) NOT NULL');
    }

    public function down(): void
    {
        // Forward-only: widening is intentionally irreversible.
    }
};
