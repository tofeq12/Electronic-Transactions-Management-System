<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4: administrator-managed transaction types. The four legacy types
 * (conversion/purchase/bills/contract) are inserted by the companion seed
 * migration as is_system rows; transactions.type keeps storing slugs, so
 * existing records are untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trx_types', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('name_ar', 100);
            $table->string('name_en', 100)->nullable();
            $table->text('description')->nullable();
            // JSON list of required document titles shown to the submitter;
            // non-empty ⇒ at least one attachment must accompany submission.
            $table->json('required_documents')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trx_types');
    }
};
