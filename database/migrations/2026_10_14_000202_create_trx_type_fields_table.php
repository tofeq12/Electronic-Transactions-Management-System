<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4: dynamic form fields per transaction type. Configuration rows
 * only — no transaction data references them (values live in
 * transactions.form_data keyed by field id), so deleting a field never
 * touches historical records.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trx_type_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trx_type_id')->constrained('trx_types')->cascadeOnDelete();
            $table->string('label_ar', 150);
            $table->string('label_en', 150)->nullable();
            $table->string('field_type', 20); // text|textarea|number|date|select|checkbox|file
            $table->boolean('is_required')->default(false);
            $table->json('options')->nullable(); // select: [{ar, en}, ...]
            $table->json('validation')->nullable(); // {min, max, min_length, max_length}
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trx_type_fields');
    }
};
